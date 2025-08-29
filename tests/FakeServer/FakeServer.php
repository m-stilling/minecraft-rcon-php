<?php namespace Tests\FakeServer;

use Stilling\MinecraftRcon\Rcon;

class FakeServer {
	protected mixed $server;
	protected bool $shouldStop = false;

	public function __construct(
		protected string $host = "127.0.0.1",
		protected string $port = "25575",
	) {}

	public function listen(): void {
		$this->server = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);

		if (!$this->server) {
			fwrite(STDERR, "Error: $errstr ($errno)\n");
			exit(1);
		}

		echo "Fake RCON server listening on {$this->host}:{$this->port}\n";

		while ($conn = @stream_socket_accept($this->server, -1)) {
			echo "Client connected\n";

			while (!feof($conn)) {
				$sizeData = fread($conn, 4);
				if (strlen($sizeData) < 4) break;

				$size = unpack('Vsize', $sizeData)['size'];
				$data = fread($conn, $size);

				if (strlen($data) < $size) break;

				$req = unpack('V1id/V1type/a*body', $data);
				$req["body"] = trim($req["body"], "\x00");

				echo "Received packet id={$req['id']} type={$req['type']} body={$req['body']}\n";

				if ($req["type"] === Rcon::PACKET_TYPE_AUTH) {
					$this->handleAuth($conn, $req);;
				} elseif ($req["type"] === Rcon::PACKET_TYPE_EXEC_CMD) {
					$this->handleExecCmd($conn, $req);
				} else {
					echo "Unknown packet type {$req['type']}\n";
				}
			}

			fclose($conn);

			if ($this->shouldStop) {
				echo "Stopping server...\n";
				fclose($this->server);
				exit(0);
			}
		}

		fclose($this->server);
	}

	protected function handleAuth($conn, array $req): void {
		$this->sendResponse($conn, $req["id"], Rcon::PACKET_TYPE_AUTH_RESPONSE, "OK");
	}

	protected function handleExecCmd($conn, array $req): void {
		if ($req["body"] === "stop") {
			$this->shouldStop = true;
			$this->sendResponse($conn, $req["id"], Rcon::PACKET_TYPE_RESPONSE_VALUE, "Server stopped");
			return;
		}

		if (str_starts_with($req["body"], "echo ")) {
			$this->sendResponse($conn, $req["id"], Rcon::PACKET_TYPE_RESPONSE_VALUE, substr($req["body"], 5));
			return;
		}

		$this->sendResponse($conn, $req["id"], Rcon::PACKET_TYPE_RESPONSE_VALUE, "Unknown command: {$req["body"]}");
	}

	protected function sendResponse($conn, int $reqId, int $type, string $body = ""): void {
		$chunks = str_split($body, 100);
		$chunkCount = count($chunks);

		echo "Response chunk count: $chunkCount\n";

		foreach ($chunks as $chunk) {
			// RCON body is null-terminated
			$responseBody = $chunk . "\x00\x00";
			// id + type + body
			$respSize = 4 + 4 + strlen($responseBody);

			$packet  = pack('V', $respSize);
			$packet .= pack('V', $reqId);
			$packet .= pack('V', $type);
			$packet .= $responseBody;

			fwrite($conn, $packet);
		}
	}
}
