# Minecraft RCON Client

[![tests](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml) [![Packagist Version](https://img.shields.io/packagist/v/stilling/minecraft-rcon)](https://packagist.org/packages/stilling/minecraft-rcon)

Lightweight Minecraft RCON client supporting multi-packet responses. Based on [PHP-Minecraft-Rcon](https://github.com/thedudeguy/PHP-Minecraft-Rcon) by [thedudeguy](https://github.com/thedudeguy).

```
composer require stilling/minecraft-rcon
```

The package requires PHP 8.3 or later.

## Quick start

```php
use Stilling\MinecraftRcon\Rcon;

$rcon = new Rcon("mc.example.com", 25575, "super-secret-password");
$rcon->connect();

$rcon->sendCommand("list");
// "There are 2 of a max of 20 players online: Alex, Steve"

$rcon->disconnect();
```

`sendCommand()` returns the response from the server as a string.

## Constructor arguments

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `host` | `string` | | The server hostname or IP address. |
| `port` | `int` | | The RCON port. The Minecraft default is `25575`. |
| `password` | `string` | | The RCON password (`rcon.password` in `server.properties`). |
| `timeout` | `int` | `3` | The time in seconds to wait for the connection and for each response. |

## Connection state

`isConnected()` returns `true` after `connect()` succeeds. It returns `false` after `disconnect()`, and after the client finds that the connection is closed or not usable.

## Errors

All exceptions extend `Stilling\MinecraftRcon\Exceptions\RconException`.

| Exception | Thrown when |
| --- | --- |
| `AuthException` | The server rejects the password. |
| `ConnectionException` | The client cannot connect, `connect()` is called on a connected client, `sendCommand()` is called before `connect()`, or the server closes the connection. |
| `PacketException` | The command is too long (see [Command length limit](#command-length-limit)), or the server sends a packet with an invalid size. |
| `TimeoutException` | The server does not respond within the timeout. |

After an `AuthException`, a closed connection, or an invalid packet from the server, the client is disconnected. Call `connect()` to connect again.

```php
use Stilling\MinecraftRcon\Exceptions\RconException;
use Stilling\MinecraftRcon\Rcon;

$rcon = new Rcon("mc.example.com", 25575, "super-secret-password", timeout: 5);

try {
    $rcon->connect();
    $players = $rcon->sendCommand("list");
} catch (RconException $e) {
    // Log the error or show it to the user.
} finally {
    $rcon->disconnect();
}
```

`disconnect()` is safe to call when the client is not connected.

## Command length limit

A command can be at most **1446 bytes**. For a longer command, `sendCommand()` throws a `PacketException` and does not send the command.

## Development

```
composer test
composer analyse
```

`composer test` starts a fake RCON server on `127.0.0.1:25575` and stops it when the tests end. If a server already listens on that port, the tests use it. The fake server writes its output to `fake-server.log`.

## See also

[`stilling/snbt-parser`](https://packagist.org/packages/stilling/snbt-parser) reads SNBT into PHP values. Use it to parse the output of commands such as `data get ...`.
