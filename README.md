Yamuca Server Application (PHP)
===============================

Yamuca is an [extension for Google Chrome](https://github.com/feedbee/yamuca-chrome-ext) browser that makes remote control over Yandex.Music player possible. Yamuca Server Application is a component, which connects together client application and player controller (extension). Client and controller both connect to the server for message exchange. All components (client, server and controller) are interchangeable. Their common point of interest is interaction protocol only.

Get it running
--------------

To run server application:

1. Create a copy of `config.dist.php` file and edit it if needed.
2. Install application dependencies with composer: `php composer.phar install`.
3. Run server: `php bin/server.php`.

Requirements
------------

PHP 5.4 is required. Libevent PHP extension [is recommended](http://socketo.me/docs/deploy#libevent) for performance reasons. XDebug extension should be turned off on production because of it's performance impact.

Protocol
--------

Communication protocol is JSON based. All messages must be passed as JSON object. Server doesn't distinct it's clients, both browser extension and client application are equivalent and cat pass commands to each other. To relate command sender to consumers `key` is used. Every client must setup a key before sending any commands. All passed commands will be replicated to all of the clients that set same key as message sender. So, this server can be understood as group hub.

Message types:

- `{"key": "xxx"}`: setup a key `xxx` for current connection.
- `{"command": "zzz", ...}`: send `zzz` application-level command to all consumers related to the key client was set earlier.

`command` message accepted after any `key` message only. Messages can include payload additional key-values. `key` messages can be sent multiply times, every next `key` will override previously set. Maximal key length is limited to 1024 bytes. Overall message size is limited to 4092 bytes.

If error occurred during message processing server will send response message: `{"error": "eee"}`, where `eee` is error text. Currently implemented error texts are:

- Message is too long
- Key is too long
- Can't parse your message
- Unknown message type
- Internal server error

Application-level commands are not defined in terms of server-related communication protocol. In client application, that is embedded to this server, currently implemented commands are:

- `togglePlay`
- `next`
- `previous`

Author
------

Valera Leontyev (feedbee@gmail.com).
Send questions to this email. Use [issues](https://github.com/feedbee/yamuca-server-php/issues) for error reporting.

Development state
-----------------

Application is in development state currently.