<?php

use Stilling\MinecraftRcon\Rcon;

test("short response", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"",
	);
	$rcon->connect();

	expect($rcon->sendCommand("echo hello"))->toEqual("hello");

	$rcon->disconnect();
});

test("long response", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"",
	);
	$rcon->connect();
	$message = str_repeat("hello", 100);

	expect($rcon->sendCommand("echo $message"))->toEqual($message);

	$rcon->disconnect();
});

test("stop", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"",
	);
	$rcon->connect();

	expect($rcon->sendCommand("stop"))->toEqual("Server stopped");

	$rcon->disconnect();
});
