# Minecraft RCON Client

[![tests](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml) [![Packagist Version](https://img.shields.io/packagist/v/stilling/minecraft-rcon)](https://packagist.org/packages/stilling/minecraft-rcon) 

Lightweight Minecraft RCON client supporting multi-packet responses. Based on [PHP-Minecraft-Rcon](https://github.com/thedudeguy/PHP-Minecraft-Rcon) by [thedudeguy](https://github.com/thedudeguy).

Requires PHP 8.3 or later.

```
composer require stilling/minecraft-rcon
```

> [!TIP]
> Need to make sense of the SNBT (NBT) data these commands return? [`stilling/snbt-parser`](https://packagist.org/packages/stilling/snbt-parser) is a lightweight parser that turns the output of commands like `data get ...` straight into native PHP values.

```php
use Stilling\MinecraftRcon\Rcon;

$rcon = new Rcon(
    "mc.example.com",
    25575,
    "super-secret-password",
);
$rcon->connect();
$response = $rcon->sendCommand("data get entity @e[limit=1]");

var_dump($response);
// string(792) "Pig has the following entity data: {variant: "minecraft:temperate", DeathTime: 0s, OnGround: 1b, LeftHanded: 0b, AbsorptionAmount: 0.0f, Invulnerable: 0b, Brain: {memories: {}}, Age: 0, Rotation: [225.00148f, 0.0f], HurtByTimestamp: 0, attributes: [{modifiers: [{amount: 0.05006394748174687d, operation: "add_multiplied_base", id: "minecraft:random_spawn_bonus"}], base: 16.0d, id: "minecraft:follow_range"}, {base: 0.25d, id: "minecraft:movement_speed"}], ForcedAge: 0, fall_distance: 0.0d, Air: 300s, UUID: [I; 2036213014, 1672495779, -2115405465, 2041861210], Fire: 0s, Motion: [0.0d, -0.0784000015258789d, 0.0d], Pos: [-128.75220754374612d, 71.0d, -69.24781649456469d], Health: 10.0f, CanPickUpLoot: 0b, HurtTime: 0s, FallFlying: 0b, PersistenceRequired: 0b, InLove: 0, PortalCooldown: 0}"

$rcon->disconnect();
```

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
| `PacketException` | The command is too long (see below), or the server sends a packet with an invalid size. |
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

Minecraft reads each RCON request into a fixed 1460-byte buffer, so a command body may be at most **1446 bytes**. Longer commands are rejected with a `PacketException` rather than being sent, since the server would otherwise silently drop the connection.

## Development

```
composer test
composer analyse
```

`composer test` starts a fake RCON server on `127.0.0.1:25575` and stops it when the tests end. If a server already listens on that port, the tests use it. The fake server writes its output to `fake-server.log`.
