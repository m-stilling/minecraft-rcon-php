<?php

use Stilling\MinecraftRcon\Rcon;

require_once __DIR__ . "/../vendor/autoload.php";

$rcon = new Rcon(
	"127.0.0.1",
	25575,
	"super-secret-password",
);
$rcon->connect();

$response = $rcon->sendCommand("data get entity @e[limit=1,type=pig]");
var_dump($response);

$rcon->disconnect();
