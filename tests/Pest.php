<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Fake RCON Server
|--------------------------------------------------------------------------
|
| The feature tests need the fake RCON server on 127.0.0.1:25575. If no server
| listens there, start one for this test run and stop it when the run ends.
| The server writes its output to fake-server.log in the project root.
|
*/

function fakeServerIsListening(): bool {
	$socket = @fsockopen("127.0.0.1", 25575, $errorCode, $errorMessage, 0.2);

	if ($socket === false) {
		return false;
	}

	fclose($socket);
	return true;
}

if (!fakeServerIsListening()) {
	$fakeServer = proc_open(
		[PHP_BINARY, __DIR__ . "/FakeServer/fake-rcon-server.php"],
		[
			1 => ["file", __DIR__ . "/../fake-server.log", "w"],
			2 => ["redirect", 1],
		],
		$pipes,
	);

	if ($fakeServer === false) {
		throw new RuntimeException("Failed to start the fake RCON server");
	}

	register_shutdown_function(static function () use ($fakeServer): void {
		proc_terminate($fakeServer);
		proc_close($fakeServer);
	});

	$deadline = microtime(true) + 5;

	while (!fakeServerIsListening()) {
		if (microtime(true) > $deadline || !proc_get_status($fakeServer)["running"]) {
			throw new RuntimeException("The fake RCON server did not start. See fake-server.log.");
		}

		usleep(50_000);
	}
}
