<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Feedbee\Yamuca\ServerApp;

$configFilePath = realpath(__DIR__ . '/../config.php');
if (!file_exists($configFilePath)) {
    die('Config file does not exist. Read README.md for details.');
}
$config = require $configFilePath;

require dirname(__DIR__) . '/vendor/autoload.php';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ServerApp
        )
    ),
    $config['listen']['port'],
    $config['listen']['interface']
);

$server->run();