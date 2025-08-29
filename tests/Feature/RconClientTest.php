<?php

use Stilling\MinecraftRcon\Exceptions\AuthException;
use Stilling\MinecraftRcon\Exceptions\ConnectionException;
use Stilling\MinecraftRcon\Exceptions\TimeoutException;
use Stilling\MinecraftRcon\Rcon;

test("single packet response", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"1234",
		1,
	);
	$rcon->connect();

	expect($rcon->sendCommand("echo hello"))->toEqual("hello");

	$rcon->disconnect();
});

test("multi packet response", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"1234",
		1,
	);
	$rcon->connect();
	$message = str_repeat("hello", 100);

	expect($rcon->sendCommand("echo $message"))->toEqual($message);

	$rcon->disconnect();
});

test("throws if sending command before connecting", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"1234",
		1,
	);
	expect(fn () => $rcon->sendCommand("echo hello"))->toThrow(ConnectionException::class);
});

test("throws if cant connect", function () {
	$rcon = new Rcon(
		"127.0.0.2",
		"25576",
		"1234",
		1,
	);
	expect(fn () => $rcon->connect())->toThrow(ConnectionException::class);
});

test("throws if password is incorrect", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"12345",
		1,
	);
	expect(fn () => $rcon->connect())->toThrow(AuthException::class);;
});

test("throws on timeout", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"1234",
		$timeout = 1,
	);
	$rcon->connect();

	expect(fn () => $rcon->sendCommand("force-timeout $timeout"))->toThrow(TimeoutException::class);;
});

test("stop", function () {
	$rcon = new Rcon(
		"127.0.0.1",
		"25575",
		"1234",
		1,
	);
	$rcon->connect();

	expect($rcon->sendCommand("stop"))->toEqual("Server stopped");

	$rcon->disconnect();
});
