<?php namespace Stilling\MinecraftRcon;

use Stilling\MinecraftRcon\Exceptions\AuthException;
use Stilling\MinecraftRcon\Exceptions\ConnectionException;
use Stilling\MinecraftRcon\Exceptions\PacketException;
use Stilling\MinecraftRcon\Exceptions\TimeoutException;

class Rcon
{
	protected mixed $socket = null;
	protected bool $authorized = false;
	protected int $packetId = 0;

	const int PACKET_TYPE_AUTH = 3;
	const int PACKET_TYPE_AUTH_RESPONSE = 2;
	const int PACKET_TYPE_EXEC_CMD = 2;
	const int PACKET_TYPE_RESPONSE_VALUE = 0;

	// Minecraft reads each RCON request into a fixed 1460-byte buffer, so the
	// whole packet (length prefix + id + type + body + two null terminators)
	// must fit within it. That caps the command body at 1446 bytes.
	const int MAX_PACKET_SIZE = 1460;

	public function __construct(
		protected string $host,
		protected int $port,
		protected string $password,
		protected int $timeout = 3,
	) {}

	/**
	 * @throws AuthException
	 * @throws ConnectionException
	 * @throws PacketException
	 * @throws TimeoutException
	 */
	public function connect(): void {
		if (isset($this->socket)) {
			throw new ConnectionException("Already connected");
		}

		set_error_handler(static fn (): bool => true, E_WARNING);
		$this->socket = fsockopen($this->host, $this->port, $errorCode, $errorMessage, $this->timeout);
		restore_error_handler();

		if (!$this->socket) {
			$this->socket = null;
			throw new ConnectionException($errorMessage, $errorCode);
		}

		stream_set_timeout($this->socket, $this->timeout);
		$this->authorize();
	}

	/**
	 * @throws AuthException
	 * @throws ConnectionException
	 * @throws PacketException
	 * @throws TimeoutException
	 */
	protected function authorize(): void {
		$this->writePacket(++$this->packetId, self::PACKET_TYPE_AUTH, $this->password);

		$responsePacket = $this->readPacket();

		if (
			$responsePacket["id"] === $this->packetId
			&& $responsePacket["type"] === self::PACKET_TYPE_AUTH_RESPONSE
		) {
			$this->authorized = true;
			return;
		}

		$this->disconnect();
		throw new AuthException("Failed to authorize");
	}

	public function disconnect(): void {
		if (isset($this->socket)) {
			fclose($this->socket);
			$this->socket = null;
		}
		$this->authorized = false;
	}

	public function isConnected(): bool {
		return $this->authorized;
	}

	/**
	 * @param string $command
	 * @return string
	 * @throws ConnectionException
	 * @throws PacketException
	 * @throws TimeoutException
	 */
	public function sendCommand(string $command): string {
		if (!$this->isConnected()) {
			throw new ConnectionException("Not connected");
		}

		// Send command packet
		$commandId = ++$this->packetId;
		$this->writePacket($commandId, self::PACKET_TYPE_EXEC_CMD, $command);

		// Send dummy packet to mark end
		$dummyId = ++$this->packetId;
		$this->writePacket($dummyId, self::PACKET_TYPE_EXEC_CMD, "");

		// Read responses
		$response = "";
		$start = microtime(true);

		while (true) {
			$duration = microtime(true) - $start;

			if ($duration > $this->timeout) {
				throw new TimeoutException("Timeout while reading");
			}

			$packet = $this->readPacket();

			if ($packet["id"] === $dummyId) {
				break;
			}

			if ($packet["id"] === $commandId && $packet["type"] === self::PACKET_TYPE_RESPONSE_VALUE) {
				$response .= rtrim($packet["body"], "\x00");
			}
		}

		return $response;
	}

	/**
	 * @throws ConnectionException
	 * @throws PacketException
	 */
	protected function writePacket(int $packetId, int $packetType, string $packetBody): void {
		// Create packet
		$packet = pack("VV", $packetId, $packetType);
		$packet = $packet.$packetBody."\x00\x00";

		// Set packet size
		$packetSize = strlen($packet);
		$packet = pack("V", $packetSize).$packet;

		// Reject packets the server cannot read. Minecraft would silently drop
		// the connection, surfacing later as a confusing read failure.
		if (strlen($packet) > self::MAX_PACKET_SIZE) {
			$maxBodySize = self::MAX_PACKET_SIZE - 14;
			throw new PacketException(
				"Packet of " . strlen($packet) . " bytes exceeds the Minecraft RCON limit of "
				. self::MAX_PACKET_SIZE . " bytes (command body may be at most {$maxBodySize} bytes)"
			);
		}

		// Write packet, handling partial writes
		$written = 0;
		$total = strlen($packet);

		while ($written < $total) {
			$bytes = fwrite($this->socket, substr($packet, $written));

			if ($bytes === false || $bytes === 0) {
				throw new ConnectionException("Failed to write to socket");
			}

			$written += $bytes;
		}
	}

	/**
	 * @param int $length
	 * @return string
	 * @throws TimeoutException
	 */
	protected function readBytes(int $length): string {
		$data = "";
		$start = microtime(true);

		while (strlen($data) < $length) {
			$duration = microtime(true) - $start;

			if ($duration > $this->timeout) {
				throw new TimeoutException("Timeout while reading");
			}

			$chunk = fread($this->socket, max(1, $length - strlen($data)));

			if ($chunk === false || $chunk === "") {
				throw new TimeoutException("Socket closed or error while reading");
			}

			$data .= $chunk;
		}

		return $data;
	}

	/**
	 * @return array{id: int, type: int, body: string}
	 * @throws ConnectionException
	 * @throws TimeoutException
	 */
	protected function readPacket(): array {
		// Read packet size
		$sizeBytes = $this->readBytes(4);
		$sizeData = unpack("V1size", $sizeBytes);

		if ($sizeData === false) {
			throw new ConnectionException("Failed to unpack packet size");
		}

		// Read packet
		$packetBytes = $this->readBytes((int) $sizeData["size"]);
		$packetData = unpack("V1id/V1type/a*body", $packetBytes);

		if ($packetData === false) {
			throw new ConnectionException("Failed to unpack packet");
		}

		return [
			"id" => (int) $packetData["id"],
			"type" => (int) $packetData["type"],
			"body" => (string) $packetData["body"],
		];
	}
}
