(function ($) {

    var showAlert = function (text) {
        var block = $($('#alert-template').html());
        $('#messages').append(block);
        $('.text', block).text(text);
        block.parent().foundation('alert', 'reflow');
    };

    if(typeof(WebSocket) != "function") {
        showAlert("Your browser doesn't support HTML5 Web Sockets. " +
            "This application will not work without it");
    }
    if(typeof(JSON) != "object" || typeof(JSON.stringify) != "function") {
        showAlert("Your browser doesn't support JSON.stringify. " +
            "This application will not work without it");
    }


    var $connectBtn = $('#connect_btn'),
        $playPauseBtn = $('#play-pause_btn'),
        $nextBtn = $('#next_btn'),
        $previousBtn = $('#previous_btn'),
        $volumeUpBtn = $('#volume-up_btn'),
        $volumeDownBtn = $('#volume-down_btn'),
        $muteBtn = $('#mute_btn'),
        $controlButtons = $('[data-enable-if-connected] button'),
        $controlInputs = $('[data-disable-if-connected] input');

    var $keyInput = $('#key_input');
    var $serverInput = $('#server_input');

    if (window.location.search) {
        var k = window.location.search.match(/[\?&]key=([^&]+)/i);
        if (typeof(k) == 'object' && k.length > 1) {
            $keyInput.val(k[1]);
        }
        k = window.location.search.match(/[\?&]server=([^&]+)/i);
        if (typeof(k) == 'object' && k.length > 1) {
            $serverInput.val(k[1]);
        }
    }

    var ws;

    var sendCommand = function (command) {
        ws.send(JSON.stringify({command: command}));
    };

    var connect = function () {
        var interval,
            lastActivity;

        try {
            ws = new WebSocket($serverInput.val());
        } catch (e) {
            showAlert(e.message);
            console.log(e.message);
            return;
        }
        ws.onopen = function () {
            console.log('WS connected');
            $connectBtn.text('Connected. Disconnect');
            $controlButtons.attr('disabled', false);
            $controlInputs.attr('disabled', true);
            var data = {key: $keyInput.val()};
            ws.send(JSON.stringify(data));
            lastActivity = Date.now();

            interval = setInterval(function () {
                if (lastActivity < Date.now() - 60 * 1000 * 2) { // 2 minutes
                    console.log('Close connection due to inactivity');
                    ws.close();
                }
            }, 2000);
        };
        ws.onclose = function (e) {
            console.log('WS connection closed', e);
            $connectBtn.text('Connect');
            $controlButtons.attr('disabled', true);
            $controlInputs.attr('disabled', false);
            clearInterval(interval);
        };
        ws.onerror = function (e) {
            showAlert("Connection closed with error");
            console.log('WS error occurred', e);
            $connectBtn.text('Connect');
            $controlButtons.attr('disabled', true);
            $controlInputs.attr('disabled', false);
        };
        ws.onmessage = function (e) {
            lastActivity = Date.now();
            var message = e.data;
            console.log('Message received', message);

            var parsedMessage = JSON.parse(message);
            if (parsedMessage.ping) {
                ws.send(JSON.stringify({ping: "pong"}));
                console.log('Ping received, pong sent');
            }
        };
    };

    var disconnect = function () {
        ws.close();
    };

    $connectBtn.click(function () {
        if ($(this).text() == 'Connect') {
            connect();
        } else {
            disconnect();
        }
    });

    $playPauseBtn.click(function () {
        sendCommand('togglePlay');
    });

    $nextBtn.click(function () {
        sendCommand('next');
    });

    $previousBtn.click(function () {
        sendCommand('previous');
    });

    $volumeUpBtn.click(function () {
        sendCommand('volumeUp');
    });

    $volumeDownBtn.click(function () {
        sendCommand('volumeDown');
    });

    $muteBtn.click(function () {
        sendCommand('mute');
    });

    $('#main').show();

})(jQuery);