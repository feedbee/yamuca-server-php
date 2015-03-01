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

Author
------

Valera Leontyev (feedbee@gmail.com).
Send questions to this email. Use [issues](https://github.com/feedbee/yamuca-server-php/issues) for error reporting.

Development state
-----------------

Application is in development state currently.