<?php

namespace App;

use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Http\RequestHeaderParser;
use React\Http\Response;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use React\Stream\WritableStreamInterface;

class WebSocketServer extends Server implements EventEmitterInterface, WritableStreamInterface
{
    /**
     * @var ConnectionInterface[]
     */
    protected $connections = [];

    /**
     * @param array $options
     *   Associative array containing:
     *   - timeout:  Set the socket timeout in seconds.  Default: 5
     *   - port:     Chose port for listening.
     */
    public function __construct(LoopInterface $loop, $context = [])
    {
        parent::__construct($loop, $context);
    }

    public function handleConnection($socket)
    {
        $that = $this;

        $this->on('connection', function (ConnectionInterface $conn) use ($that) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing
            echo "WebSocket client connected\n";
            $parser = new RequestHeaderParser();
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser, $that) {
                // attach remote ip to the request as metadata
                $request->remoteAddress = $conn->getRemoteAddress();

                // forward pause/resume calls to underlying connection
                $request->on('pause', array($conn, 'pause'));
                $request->on('resume', array($conn, 'resume'));

                $key = $this->resolveConnectionKey($request);
                if ($key && !isset($this->connections[$key])) {
                    $that->handleFirstRequest($conn, $request, $bodyBuffer);
                    $this->connections[$key] = $conn;
                }

                $conn->removeListener('data', array($parser, 'feed'));
                $conn->on('end', function () use ($request) {
                    $request->emit('end');
                });
                $conn->on('data', function ($data) use ($request) {
                    $request->emit('data', array($data));
                });
            });

            $listener = array($parser, 'feed');
            $conn->on('data', $listener);
            $parser->on('error', function() use ($conn, $listener, $that) {
                $conn->removeListener('data', $listener);
                $that->emit('error', func_get_args());
            });
        });

        parent::handleConnection($socket);
    }

    public function resolveConnectionKey(Request $request)
    {
        return $request->getHeader('Sec-WebSocket-Key')[0];
    }

    public function handleFirstRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $key = $this->resolveConnectionKey($request);

        if ($key) {
            $response_key = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            $response->writeHead(101, [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $response_key
            ]);
        }

        $this->emit('request', array($request, $response));

        if ($bodyBuffer !== '') {
            $request->emit('data', array($bodyBuffer));
        }
    }

    public function encodePayload($payload)
    {
        // while we have data to send
        // Binary string for header.
        $frameHeadBinStr = '';
        // Write FIN, final fragment bit.
        $frameHeadBinStr .= '1';
        // RSV 1, 2, & 3 false and unused.
        $frameHeadBinStr .= '000';
        // Opcode 'text'
        $frameHeadBinStr .= sprintf('%04b', 1);
        // Use masking?
        $frameHeadBinStr .= '0';
        // 7 bits of payload length...
        $payload_length = strlen($payload);

        if ($payload_length > 65535) {
            $frameHeadBinStr .= decbin(127);
            $frameHeadBinStr .= sprintf('%064b', $payload_length);
        }
        elseif ($payload_length > 125) {
            $frameHeadBinStr .= decbin(126);
            $frameHeadBinStr .= sprintf('%016b', $payload_length);
        }
        else {
            $frameHeadBinStr .= sprintf('%07b', $payload_length);
        }

        $frame = '';

        // Write frame head to frame.
        foreach (str_split($frameHeadBinStr, 8) as $binstr) {
            $frame .= chr(bindec($binstr));
        }

        // Append payload to frame:
        for ($i = 0; $i < $payload_length; $i++) {
            $frame .= $payload[$i];
        }

        return $frame;
    }

    public function isWritable()
    {
        foreach($this->connections as $connection) {
            if ($connection->isWritable()) {
                return true;
            }
        }

        return false;
    }

    public function write($data)
    {
        if (!$this->connections) {
            return;
        }
        $encodedData = $this->encodePayload($data);
        foreach($this->connections as $connection) {
            if ($connection->isWritable()) {
                $connection->write($encodedData);
            }
        }
    }

    public function end($data = null)
    {
        if ($data) {
            $encodedData = $this->encodePayload($data);
            foreach($this->connections as $connection) {
                $connection->end($encodedData);
            }
        } else {
            foreach($this->connections as $connection) {
                $connection->end();
            }
        }
    }

    public function close()
    {
        foreach($this->connections as $connection) {
            $connection->close();
        }
    }
}
