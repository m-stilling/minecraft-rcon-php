# Minecraft RCON Client

[![tests](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/m-stilling/minecraft-rcon-php/actions/workflows/tests.yml)

Lightweight Minecraft RCON client supporting multi-packet responses. Based on [PHP-Minecraft-Rcon](https://github.com/thedudeguy/PHP-Minecraft-Rcon) by [thedudeguy](https://github.com/thedudeguy).

```
composer require stilling/minecraft-rcon
```

```php
use Stilling\MinecraftRcon\Rcon;

$rcon = new Rcon(
    "mc.example.com",
    "25565",
    "super-secret-password",
);
$rcon->connect();
$response = $rcon->sendCommand("data get entity @e[limit=1]");

var_dump($response);
// string(792) "Pig has the following entity data: {variant: "minecraft:temperate", DeathTime: 0s, OnGround: 1b, LeftHanded: 0b, AbsorptionAmount: 0.0f, Invulnerable: 0b, Brain: {memories: {}}, Age: 0, Rotation: [225.00148f, 0.0f], HurtByTimestamp: 0, attributes: [{modifiers: [{amount: 0.05006394748174687d, operation: "add_multiplied_base", id: "minecraft:random_spawn_bonus"}], base: 16.0d, id: "minecraft:follow_range"}, {base: 0.25d, id: "minecraft:movement_speed"}], ForcedAge: 0, fall_distance: 0.0d, Air: 300s, UUID: [I; 2036213014, 1672495779, -2115405465, 2041861210], Fire: 0s, Motion: [0.0d, -0.0784000015258789d, 0.0d], Pos: [-128.75220754374612d, 71.0d, -69.24781649456469d], Health: 10.0f, CanPickUpLoot: 0b, HurtTime: 0s, FallFlying: 0b, PersistenceRequired: 0b, InLove: 0, PortalCooldown: 0}"

$rcon->disconnect();
```
