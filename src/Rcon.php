<?php namespace Stilling\MinecraftRcon;

use Stilling\MinecraftRcon\Exceptions\AuthException;
use Stilling\MinecraftRcon\Exceptions\ConnectionException;
use Stilling\MinecraftRcon\Exceptions\TimeoutException;

class Rcon
{
	protected mixed $socket;
	protected bool $authorized = false;
	protected int $packetId = 0;

	const int PACKET_TYPE_AUTH = 3;
	const int PACKET_TYPE_AUTH_RESPONSE = 2;
	const int PACKET_TYPE_EXEC_CMD = 2;
	const int PACKET_TYPE_RESPONSE_VALUE = 0;

	public function __construct(
		protected string $host,
		protected int $port,
		protected string $password,
		protected int $timeout = 3,
	) {}

	/**
	 * @throws AuthException
	 * @throws ConnectionException
	 * @throws TimeoutException
	 */
	public function connect(): void {
		if (isset($this->socket)) {
			return;
		}

		set_error_handler(function () {}, E_WARNING);
		$this->socket = fsockopen($this->host, $this->port, $errorCode, $errorMessage, $this->timeout);
		restore_error_handler();

		if (!$this->socket) {
			unset($this->socket);
			throw new ConnectionException($errorMessage, $errorCode);
		}

		stream_set_timeout($this->socket, 3);
		$this->authorize();
	}

	/**
	 * @throws AuthException
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
		if ($this->socket) {
			fclose($this->socket);
		}
	}

	public function isConnected(): bool {
		return $this->authorized;
	}

	/**
	 * @param string $command
	 * @return string
	 * @throws ConnectionException
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

	protected function writePacket(int $packetId, int $packetType, string $packetBody): void {
		// Create packet
		$packet = pack("VV", $packetId, $packetType);
		$packet = $packet.$packetBody."\x00\x00";

		// Set packet size
		$packetSize = strlen($packet);
		$packet = pack("V", $packetSize).$packet;

		// Write packet
		fwrite($this->socket, $packet, strlen($packet));
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

			$chunk = fread($this->socket, $length - strlen($data));

			if ($chunk === false || $chunk === "") {
				throw new TimeoutException("Socket closed or error while reading");
			}

			$data .= $chunk;
		}

		return $data;
	}

	/**
	 * @return array
	 * @throws TimeoutException
	 */
	protected function readPacket(): array {
		// Read packet size
		$sizeBytes = $this->readBytes(4);
		$size = unpack("V1size", $sizeBytes)["size"];

		// Read packet
		$packetBytes = $this->readBytes($size);
		$packetData = unpack("V1id/V1type/a*body", $packetBytes);

		return $packetData;
	}
}
