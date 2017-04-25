<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\WebSocketServer;
use React\Socket\ConnectionInterface;
use React\SocketClient\Connector;

$loop = React\EventLoop\Factory::create();
$wsServer = new WebSocketServer($loop);

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8:53', $loop);
$upstreamConnector = new Connector($loop, $dns);
$httpHeadersParser = new \React\Http\RequestHeaderParser();

$proxyHost = 'voyeur-httpbin.org';
$proxyPort = 80;

$app = function (ConnectionInterface $proxyConnection)
    use ($loop, $upstreamConnector, $wsServer, $proxyHost, $proxyPort, $httpHeadersParser) {
    $proxyConnection->on('data', function ($proxyRequestString, \React\Socket\Connection $proxyConnection)
        use ($upstreamConnector, $wsServer, $proxyHost, $proxyPort, $httpHeadersParser) {

//        /** @var \React\Http\Request $httpRequest */
//        list($httpRequest, ) = $httpHeadersParser->parseRequest($proxyRequestString);

        echo 'Request from '.$proxyConnection->getRemoteAddress().PHP_EOL;

        $host = 'httpbin.org';
        $port = 80;

        $proxyRequestString = str_replace(
            'Host: ' . $proxyHost . ($proxyPort != 80 ? ':' . $proxyPort : ''),
            'Host: ' . $host .  ($port != 80 ? ':' . $port : ''),
            $proxyRequestString
        );

        $upstreamConnector
            ->create($host, $port)
            ->then(function (React\Stream\Stream $upstreamStream)
                use ($proxyConnection, $proxyRequestString, $wsServer, $host, $port) {
                $upstreamStream->on('data', function($upstreamResponseString)
                    use ($proxyConnection, $upstreamStream, $wsServer, $proxyRequestString, $host, $port) {
                    $wsServer->write(json_encode([
                        'time' => time(),
                        'sender' => [
                            'address' => $proxyConnection->getRemoteAddress(),
                        ],
                        'destination' => [
                            'host' => $host,
                            'port' => (integer) $port,
                        ],
                        'raw_request' => base64_encode($proxyRequestString),
                        'raw_response' => base64_encode($upstreamResponseString)
                    ]));
                });
//                $upstreamStream->on('end', function() {
//                    var_dump('end');
//                });
//                $upstreamStream->on('close', function($upstreamResponseString)
//                    use ($upstreamResponseBuffer, $wsServer, $proxyRequest) {
//                    $upstreamResponseBuffer .= $upstreamResponseString;
//                    var_dump('close');
//
//                });
                $upstreamStream->pipe($proxyConnection);
                $upstreamStream->write($proxyRequestString);
            });
    });
};

$incomeSocket = new React\Socket\Server($loop);
$incomeSocket->on('connection', $app);
$incomeSocket->on('error', 'printf');
$incomeSocket->listen($proxyPort, '0.0.0.0');

$wsServer->on('error', 'printf');
$wsServer->listen(12345);

echo 'Income listen on ' . $incomeSocket->getPort() . PHP_EOL;
echo 'WebSocket listen on ' . $wsServer->getPort() . PHP_EOL;

$loop->run();