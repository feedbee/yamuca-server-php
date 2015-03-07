<?php

namespace Feedbee\Yamuca;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
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

    public function onOpen(ConnectionInterface $connection) {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug('New connection from ' . $connection->remoteAddress);
        $this->addConnection($connection);
    }

    public function onMessage(ConnectionInterface $senderConnection, $msg) {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug("Message from {$senderConnection->remoteAddress}: $msg");

        if (strlen($msg) > 4092) {
            $this->logger->debug('Protocol error: message is too long (' . strlen($msg) . ' bytes)');
            $senderConnection->send(json_encode(array('error' => 'Message is too long')));
            return;
        }

        $data = json_decode($msg, true, 2);
        if (is_null($data)) {
            $this->logger->debug("Protocol error: can't parse message: $msg");
            $senderConnection->send(json_encode(array('error' => 'Can\'t parse your message')));
            return;
        }

        if (isset($data['key'])) {
            $this->logger->debug('Key message detected');

            if (strlen($data['key']) > 1024) {
                $this->logger->debug('Protocol error: key is too long (' . strlen($data['key']) . "): $msg");
                $senderConnection->send(json_encode(array('error' => 'Key is too long')));
                return;
            }
            $index = $this->getConnectionIndex($senderConnection);
            if (is_null($index)) {
                $this->logger->debug('Protocol error: can\'t find connection index');
                $senderConnection->send(json_encode(array('error' => 'Internal server error')));
                return;
            }
            $this->keys[$index] = $data['key'];
            $this->logger->debug("New key was set for connection #$index: {$data['key']}");

        } else if (isset($data['command'])) {
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
                    $this->connections[$index]->send($msg);
                }
            }
        } else {
            $this->logger->debug('Protocol error: unknown message type');
            $senderConnection->send(json_encode(array('error' => 'Unknown message type')));
        }
    }

    public function onClose(ConnectionInterface $connection) {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug('Connection closed from ' . $connection->remoteAddress);
        $this->unsetConnection($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $e) {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->logger->debug("Error from {$connection->remoteAddress}: {$e->getMessage()}, {$e->getTraceAsString()}");
        $this->unsetConnection($connection);
    }

    private function addConnection(ConnectionInterface $connection) {
        $this->connections[] = $connection;
        $this->keys[] = null;

        end($this->connections);
        $index = key($this->connections);
        $this->logger->info("Connection #$index added, connected: " . count($this->connections) . ' clients');
    }

    private function getConnectionIndex(ConnectionInterface $connection) {
        $key = array_search($connection, $this->connections);
        return $key === false ? null : $key;
    }

    private function unsetConnection(ConnectionInterface $connection) {
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