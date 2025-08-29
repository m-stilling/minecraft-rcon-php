<?php namespace Stilling\MinecraftRcon;

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

	public function connect(): void {
		$this->socket = fsockopen($this->host, $this->port, $errorCode, $errorMessage, $this->timeout);

		if (!$this->socket) {
			throw new \Exception("Failed to connect: ($errorCode) $errorMessage");
		}

		stream_set_timeout($this->socket, 3);

		$this->authorize();
	}

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
		throw new \Exception("Failed to authorize");
	}

	public function disconnect(): void {
		if ($this->socket) {
			fclose($this->socket);
		}
	}

	public function isConnected(): bool {
		return $this->authorized;
	}

	public function sendCommand($command): string {
		if (!$this->isConnected()) {
			throw new \Exception("Not authorized");
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
				throw new \RuntimeException("Timeout while reading");
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

	protected function readBytes(int $length): string {
		$data = "";
		$start = microtime(true);

		while (strlen($data) < $length) {
			$duration = microtime(true) - $start;

			if ($duration > $this->timeout) {
				throw new \RuntimeException("Timeout while reading");
			}

			$chunk = fread($this->socket, $length - strlen($data));

			if ($chunk === false || $chunk === "") {
				throw new \RuntimeException("Socket closed or error while reading");
			}

			$data .= $chunk;
		}

		return $data;
	}

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
