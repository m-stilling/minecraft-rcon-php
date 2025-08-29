<?php

use Tests\FakeServer\FakeServer;

require_once __DIR__ . "/../../vendor/autoload.php";

$fakeServer = new FakeServer();
$fakeServer->listen();
