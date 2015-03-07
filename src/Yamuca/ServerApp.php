<?php

namespace Feedbee\Yamuca;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

/**
 * Yamuca server-side application
 *
 * TODO: access control, max connections limit, requests intensity limit
 */
class ServerApp implements MessageComponentInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ConnectionInterface[]
     */
    private $connections = [];

    /**
     * @var string[]
     */
    private $keys = [];

    /**
     * @var int[]
     */
    private $lastActivityTimes = [];

    public function run(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(59, function () {
            if (count($this->connections)) {
                $this->sendPing();
                $this->closeInactiveConnection();
            } else {
                $this->logger->debug('No connections to ping or close');
            }
        });
    }

    public function sendPing()
    {
        $this->logger->debug('Send pings');
        foreach ($this->connections as $index => $connection) {
            try
            {
                $connection->send(json_encode([
                    "ping" => "ping"
                ]));
            } catch (\Exception $e) {
                /** @noinspection PhpUndefinedFieldInspection */
                $this->logger->debug("Ping sending error to {$connection->remoteAddress}: {$e->getMessage()}");
            }
        }
    }

    public function closeInactiveConnection()
    {
        $this->logger->debug('Close inactive connection');

        $time = time() - 60 * 2; // activity interval 2 minutes
        foreach ($this->connections as $connection) {
            $index = $this->getConnectionIndex($connection);
            if (is_null($index)) {
                $this->logger->debug("Can't find connection index");
                continue;
            }

            if (!isset($this->lastActivityTimes[$index])) {
                $this->logger->debug("Connection activity is not set");
                continue;
            }

            if ($this->lastActivityTimes[$index] < $time) {
                $this->logger->debug("Close connection #$index due to inactivity");
                $connection->close();
            }
        }
    }

    public function onOpen(ConnectionInterface $connection)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug('New connection from ' . $connection->remoteAddress);
        $this->addConnection($connection);
    }

    public function onMessage(ConnectionInterface $senderConnection, $msg)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug("Message from {$senderConnection->remoteAddress}: $msg");

        $this->refresh($senderConnection);

        if (strlen($msg) > 4092) {
            $this->logger->debug('Protocol error: message is too long (' . strlen($msg) . ' bytes)');
            $senderConnection->send(json_encode(array('error' => 'Message is too long')));
            return;
        }

        $parsedMessage = json_decode($msg, true, 2);
        if (is_null($parsedMessage)) {
            $this->logger->debug("Protocol error: can't parse message: $msg");
            $senderConnection->send(json_encode(array('error' => 'Can\'t parse your message')));
            return;
        }

        $this->processMessage($senderConnection, $parsedMessage);
    }

    protected function processMessage(ConnectionInterface $senderConnection, array $message)
    {
        if (isset($message['key'])) {
            $this->processKeyMessage($senderConnection, $message);
        } else if (isset($message['command'])) {
            $this->processCommandMessage($senderConnection, $message);
        } else if (isset($message['ping'])) {
            // don't required any action as connection automatically refreshed on every message
        } else {
            $this->logger->debug('Protocol error: unknown message type');
            $senderConnection->send(json_encode(array('error' => 'Unknown message type')));
        }
    }

    protected function processKeyMessage(ConnectionInterface $senderConnection, array $message)
    {
        $this->logger->debug('Key message detected');

        if (strlen($message['key']) > 1024) {
            $this->logger->debug('Protocol error: key is too long (' . strlen($message['key']) . "): {$message['key']}");
            $senderConnection->send(json_encode(array('error' => 'Key is too long')));
            return;
        }
        $index = $this->getConnectionIndex($senderConnection);
        if (is_null($index)) {
            $this->logger->debug('Protocol error: can\'t find connection index');
            $senderConnection->send(json_encode(array('error' => 'Internal server error')));
            return;
        }
        $this->keys[$index] = $message['key'];
        $this->logger->debug("New key was set for connection #$index: {$message['key']}");
    }

    protected function processCommandMessage(ConnectionInterface $senderConnection, array $message)
    {
        $this->logger->debug('Command message detected');

        $senderIndex = $this->getConnectionIndex($senderConnection);
        if (is_null($senderIndex)) {
            $this->logger->debug('Protocol error: can\'t find connection index');
            $senderConnection->send(json_encode(array('error' => 'Internal server error')));
            return;
        }
        $senderKey = $this->keys[$senderIndex];

        foreach ($this->keys as $index => $key) {
            if ($senderIndex != $index && $key == $senderKey) {
                $this->logger->debug("Replicate message to connection #$index");
                $this->connections[$index]->send(json_encode($message, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    protected function refresh(ConnectionInterface $connection)
    {
        $index = $this->getConnectionIndex($connection);
        if (is_null($index)) {
            $this->logger->debug('Protocol error: can\'t find connection index');
            return;
        }

        $this->logger->debug("Connection #$index refreshed");

        $this->lastActivityTimes[$index] = time();
    }

    public function onClose(ConnectionInterface $connection)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug('Connection closed from ' . $connection->remoteAddress);
        $this->unsetConnection($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug("Error from {$connection->remoteAddress}: {$e->getMessage()}, {$e->getTraceAsString()}");
        $this->unsetConnection($connection);
    }

    private function addConnection(ConnectionInterface $connection)
    {
        $this->connections[] = $connection;
        $this->keys[] = null;
        $this->lastActivityTimes[] = time();

        end($this->connections);
        $index = key($this->connections);
        $this->logger->info("Connection #$index added, connected: " . count($this->connections) . ' clients');
    }

    private function getConnectionIndex(ConnectionInterface $connection)
    {
        $key = array_search($connection, $this->connections);
        return $key === false ? null : $key;
    }

    private function unsetConnection(ConnectionInterface $connection)
    {
        $index = $this->getConnectionIndex($connection);
        if (!is_null($index)) {
            unset($this->connections[$index]);
            unset($this->keys[$index]);
            $this->logger->info("Connection #$index removed, connected: " . count($this->connections) . ' clients');
        } else {
            $this->logger->debug("Trying to remove not exists connection #$index");
        }
    }
}