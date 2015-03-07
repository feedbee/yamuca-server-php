<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Feedbee\Yamuca\ServerApp;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

$options = [
    'quiet' => false,
    'debug' => false,
];
$opts = getopt('qd', ['quiet', 'debug']);
if (isset($opts['q']) || isset($opts['quiet'])) {
    $options['quiet'] = true;
} else if (isset($opts['d']) || isset($opts['debug'])) {
    $options['debug'] = true;
}

$configFilePath = realpath(__DIR__ . '/../config.php');
if (!file_exists($configFilePath)) {
    die('Config file does not exist. Read README.md for details.');
}
$config = require $configFilePath;

require dirname(__DIR__) . '/vendor/autoload.php';

$logger = new Logger('main-logger');
$logLevel = $options['debug'] ? Logger::DEBUG : Logger::INFO;
$logHandler = $options['quiet'] ? new NullHandler($logLevel) : new StreamHandler('php://stderr', $logLevel);
$logger->pushHandler($logHandler);

$logger->debug('Create server');
$serverApp = new ServerApp;
$serverApp->setLogger($logger);
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $serverApp
        )
    ),
    $config['listen']['port'],
    $config['listen']['interface']
);
$serverApp->run($server->loop);

$logger->info("Run server: {$config['listen']['interface']}:{$config['listen']['port']}");
$server->run();