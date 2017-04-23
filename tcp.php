<?php declare(strict_types=1);

use Devristo\Phpws\Server\WebSocketServer;
use React\Socket\ConnectionInterface;
use React\SocketClient\Connector;
use Rx\Observer\CallbackObserver;

require __DIR__.'/vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dnsResolver = $dnsResolverFactory->createCached('8.8.8.8:53', $loop);
$connector = new Connector($loop, $dnsResolver);

$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);

$app = function (ConnectionInterface $connection) use ($loop, $connector) {
    $connection->on('data', function($data) use ($connection, $connector) {
        $connector->create('127.0.0.1', 80)
            ->then(function (React\Stream\Stream $stream) use ($connection, $data) {
                $stream->pipe($connection);
                $stream->write($data);
            });
    });
};

$socket->on('connection', $app);
$socket->on('error', 'printf');
$socket->listen(8080);

echo 'Listening on ' . $socket->getPort() . PHP_EOL;

$loop->addPeriodicTimer(0.5, function() use($server, $logger){
    $time = new DateTime();
    $string = $time->format("Y-m-d H:i:s");
    $logger->notice("Broadcasting time to all clients: $string");
    foreach($server->getConnections() as $client)
        $client->sendString($string);
});


// Bind the server
$server->bind();

$loop->run();