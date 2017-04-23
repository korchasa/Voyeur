<?php

/**
 * This file is used for the tests, but can also serve as an example of a WebSocket\Server.
 */

require('./vendor/autoload.php');

use WebSocket\Server;

// Setting timeout to 200 seconds to make time for all tests and manual runs.
$server = new Server([
    'port' => 12345,
    'timeout' => 200
]);

echo $server->getPort(), "\n";

while ($connection = $server->accept()) {
    $test_id = $server->getPath();
    $test_id = substr($test_id, 1);

    try {
        while(1) {
            $message = $server->receive();
            echo "Received $message\n\n";

            if ($message === 'exit') {
                echo microtime(true), " Client told me to quit.  Bye bye.\n";
                echo microtime(true), " Close response: ", $server->close(), "\n";
                echo microtime(true), " Close status: ", $server->getCloseStatus(), "\n";
                save_coverage_data($test_id);
                exit;
            }

            elseif ($auth = $server->getHeader('Authorization')) {
                $server->send("$auth - $message", 'text', false);
            }
            else {
                $server->send($message, 'text', false);
            }
        }
    }
    catch (WebSocket\ConnectionException $e) {
        echo "\n", microtime(true), " Client died: $e\n";
    }
}