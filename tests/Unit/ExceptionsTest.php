<?php

use Stilling\MinecraftRcon\Exceptions\AuthException;
use Stilling\MinecraftRcon\Exceptions\ConnectionException;
use Stilling\MinecraftRcon\Exceptions\PacketException;
use Stilling\MinecraftRcon\Exceptions\RconException;
use Stilling\MinecraftRcon\Exceptions\TimeoutException;

test("all exceptions extend RconException", function (string $exception) {
	expect(new $exception())->toBeInstanceOf(RconException::class);
})->with([
	AuthException::class,
	ConnectionException::class,
	PacketException::class,
	TimeoutException::class,
]);
