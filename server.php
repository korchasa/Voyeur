<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\WebSocketServer;
use React\HttpClient\Response;
use React\Socket\ConnectionInterface;
use React\SocketClient\Connector;

$loop = React\EventLoop\Factory::create();
$wsServer = new WebSocketServer($loop);

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8:53', $loop);
$upstreamConnector = new Connector($loop, $dns);
$httpHeadersParser = new \React\Http\RequestHeaderParser();
$clientFile = __DIR__.'/client.html';

$proxyPort = 80;

$app = function (ConnectionInterface $proxyConnection)
    use ($loop, $upstreamConnector, $wsServer, $proxyPort, $httpHeadersParser, $clientFile) {
    $proxyConnection->on('data', function ($proxyRequestString, \React\Socket\Connection $proxyConnection)
        use ($upstreamConnector, $wsServer, $proxyPort, $httpHeadersParser, $clientFile) {

        /** @var \React\Http\Request $httpRequest */
        list($httpRequest, ) = $httpHeadersParser->parseRequest($proxyRequestString);

        if ('/keyhole' === $httpRequest->getPath()) {
            $proxyConnection->write(<<<EOD
HTTP/1.1 200 Ok
Content-Type: text/html; charset=UTF-8

EOD
);
            $proxyConnection->end(file_get_contents($clientFile));
            return;
        }

        $host = $httpRequest->getHeader('Host')[0];
        $port = 80;

        printf("Request from %s to %s:%d\n", $proxyConnection->getRemoteAddress(), $host, $port);

//        $proxyRequestString = str_replace(
//            'Host: ' . $proxyHost . ($proxyPort != 80 ? ':' . $proxyPort : ''),
//            'Host: ' . $host .  ($port != 80 ? ':' . $port : ''),
//            $proxyRequestString
//        );

        $upstreamConnector
            ->create($host, $port)
            ->then(function (React\Stream\Stream $upstreamStream)
                use ($proxyConnection, $proxyRequestString, $wsServer, $host, $port) {

                echo "handlers\n";

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
                $upstreamStream->on('end', function() {
                    var_dump('end');
                });
                $upstreamStream->on('close', function($upstreamResponseString)
                    use ($upstreamResponseBuffer, $wsServer, $proxyRequest) {
                    $upstreamResponseBuffer .= $upstreamResponseString;
                    var_dump('close');

                });
                $upstreamStream->pipe($proxyConnection);
                $upstreamStream->write($proxyRequestString);
            }, function(\Throwable $e) {
                printf("Error: %s\n",$e->getMessage());
            });
    });
};

$incomeSocket = new React\Socket\Server($loop);
$incomeSocket->on('connection', $app);
$incomeSocket->on('error', 'printf');
$incomeSocket->listen($proxyPort, '0.0.0.0');

$wsServer->on('error', 'printf');
$wsServer->listen(12345, '0.0.0.0');

echo 'Income listen on ' . $incomeSocket->getPort() . PHP_EOL;
echo 'WebSocket listen on ' . $wsServer->getPort() . PHP_EOL;

$loop->run();