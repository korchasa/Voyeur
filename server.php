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

$proxyHost = '127.0.0.1';
$proxyPort = 8080;

$protocol = null;

$app = function (ConnectionInterface $proxyConnection)
    use ($loop, $upstreamConnector, $wsServer, $proxyHost, $proxyPort, $httpHeadersParser) {
    $proxyConnection->on('data', function ($proxyRequestString, $proxyConnection)
        use ($upstreamConnector, $wsServer, $proxyHost, $proxyPort, $httpHeadersParser) {

        /** @var \React\Http\Request $httpRequest */
//        list($httpRequest, ) = $httpHeadersParser->parseRequest($proxyRequestString);

        $host = 'httpbin.org';
        $port = 80;
        $proxyRequest = str_replace(
            'Host: ' . '127.0.0.1' . ':' . 8080,
            'Host: ' . $host . ':' . $port,
            $proxyRequestString
        );
        $closeOnDoubleNewLine = true;

        $upstreamConnector
            ->create($host, $port)
            ->then(function (React\Stream\Stream $upstreamStream)
                use ($proxyConnection, $proxyRequest, $wsServer, $closeOnDoubleNewLine) {
                $upstreamResponseBuffer = '';
                $upstreamStream->on('data', function($upstreamResponseString)
                    use ($upstreamResponseBuffer, $closeOnDoubleNewLine, $upstreamStream) {
                    $upstreamResponseBuffer .= $upstreamResponseString;
                    var_dump($upstreamResponseBuffer);
                    if ($closeOnDoubleNewLine && "\n\n" == substr($upstreamResponseString, 2, -2)) {
                        $upstreamStream->close();
                    }
                });
                $upstreamStream->on('end', function($upstreamResponseString)
                    use ($upstreamResponseBuffer, $wsServer, $proxyRequest) {
                    die(__LINE__);
                    $upstreamResponseBuffer .= $upstreamResponseString;
                    $wsServer->write(json_encode([
                        'raw_request' => base64_encode($proxyRequest),
                        'raw_response' => base64_encode($upstreamResponseBuffer)
                    ]));
                });
                $upstreamStream->pipe($proxyConnection);
                $upstreamStream->write($proxyRequest);
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