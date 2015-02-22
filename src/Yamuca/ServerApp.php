<?php

namespace Feedbee\Yamuca;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Yamuca server-side application
 * TODO: access control, max connections control
 */
class ServerApp implements MessageComponentInterface
{
    /**
     * @var ConnectionInterface[]
     */
    private $connections = [];

    /**
     * @var string[]
     */
    private $keys = [];

    public function onOpen(ConnectionInterface $connection) {
        $this->addConnection($connection);
    }

    public function onMessage(ConnectionInterface $senderConnection, $msg) {
        $data = json_decode($msg, true, 2);
        if (is_null($data)) {
            $senderConnection->send(json_encode(array('error' => 'Can\'t parse your message')));
            return;
        }

        if (isset($data['key'])) {
            if (strlen($data['key']) > 1024) {
                $senderConnection->send(json_encode(array('error' => 'Key is too long')));
                return;
            }
            $index = $this->getConnectionIndex($senderConnection);
            if (is_null($index)) {
                $senderConnection->send(json_encode(array('error' => 'Internal error: can\'t find connection index')));
                return;
            }
            $this->keys[$index] = $data['key'];

        } else if (isset($data['command'])) {
            $index = $this->getConnectionIndex($senderConnection);
            if (is_null($index)) {
                $senderConnection->send(json_encode(array('error' => 'Internal error: can\'t find connection index')));
                return;
            }
            $senderKey = $this->keys[$index];

            foreach ($this->keys as $index => $key) {
                if ($key == $senderKey) {
                    $this->connections[$index]->send($msg);
                }
            }
        } else {
            $senderConnection->send(json_encode(array('error' => 'Unknown message type')));
            return;
        }
    }

    public function onClose(ConnectionInterface $connection) {
        $this->unsetConnection($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $e) {
        $this->unsetConnection($connection);
    }

    private function addConnection(ConnectionInterface $connection) {
        $this->connections[] = $connection;
        $this->keys[] = null;
    }

    private function getConnectionIndex(ConnectionInterface $connection) {
        $key = array_search($connection, $this->connections);
        return $key === false ? null : $key;
    }

    private function unsetConnection(ConnectionInterface $connection) {
        $key = $this->getConnectionIndex($connection);
        if (!is_null($key)) {
            unset($this->connections[$key]);
            unset($this->keys[$key]);
        }
    }
}