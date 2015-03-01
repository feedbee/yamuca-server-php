(function ($) {

    if(typeof(WebSocket) != "function") {
        $('#problem').text("Your browser doesn't support HTML5 Web Sockets. " +
            "This application will not work without it")
            .show();
    }
    if(typeof(JSON) != "object" || typeof(JSON.stringify) != "function") {
        $('#problem').text("Your browser doesn't support JSON.stringify. " +
        "This application will not work without it")
            .show();
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
        ws = new WebSocket($serverInput.val());
        ws.onopen = function () {
            $connectBtn.text('Connected. Disconnect');
            $controlButtons.attr('disabled', false);
            $controlInputs.attr('disabled', true);
            var data = {key: $keyInput.val()};
            ws.send(JSON.stringify(data));
        };
        ws.close = function () {
            $connectBtn.text('Connect');
            $controlButtons.attr('disabled', true);
            $controlInputs.attr('disabled', false);
        };
        ws.onerror = function () {
            $connectBtn.text('Connect');
            $controlButtons.attr('disabled', true);
            $controlInputs.attr('disabled', false);
        };
        ws.onmessage = function (e) {
            var message = e.data;
            console.log(message);
            // nothing to do currently
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