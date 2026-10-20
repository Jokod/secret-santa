<?php

namespace App\Tests\Unit;

use App\Controller\MercureHubProxyController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MercureHubProxyControllerTest extends TestCase
{
    public function testReturnsEventStreamHeaders(): void
    {
        $controller = new MercureHubProxyController('http://127.0.0.1:6954/.well-known/mercure');
        $response = $controller(Request::create('/.well-known/mercure', 'GET', ['topic' => 'topic-a']));

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('text/event-stream', $response->headers->get('Content-Type'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertSame('keep-alive', $response->headers->get('Connection'));
        self::assertSame('no', $response->headers->get('X-Accel-Buffering'));
        self::assertSame('identity', $response->headers->get('Content-Encoding'));
    }

    public function testStreamCallbackIsCallableWithoutThrowingWhenHubDown(): void
    {
        $controller = new MercureHubProxyController('http://127.0.0.1:1/.well-known/mercure');
        $request = Request::create('/.well-known/mercure', 'GET', ['topic' => 't']);
        $request->headers->set('Cookie', 'mercureAuthorization=subscriber-jwt');
        $request->headers->set('Authorization', 'Bearer unused');
        $response = $controller($request);

        ob_start();
        $response->sendContent();
        self::assertSame('', (string) ob_get_clean());
    }

    public function testAcceptsRequestWithoutAuthHeaders(): void
    {
        $controller = new MercureHubProxyController('http://127.0.0.1:1/.well-known/mercure');
        $response = $controller(Request::create('/.well-known/mercure'));

        ob_start();
        $response->sendContent();
        self::assertSame('', (string) ob_get_clean());
    }

    public function testStreamsChunksFromOneShotHttpServer(): void
    {
        $addrFile = tempnam(sys_get_temp_dir(), 'santa_mercure_addr_');
        self::assertNotFalse($addrFile);

        $serverCode = <<<'PHP'
$addrFile = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0') ?: exit(1);
file_put_contents($addrFile, stream_socket_get_name($server, false));
$conn = stream_socket_accept($server, 5);
if ($conn === false) {
    exit(2);
}
stream_set_blocking($conn, false);
$deadline = microtime(true) + 2;
$request = '';
while (microtime(true) < $deadline && !str_contains($request, "\r\n\r\n")) {
    $chunk = fread($conn, 8192);
    if ($chunk !== false && $chunk !== '') {
        $request .= $chunk;
    } else {
        usleep(5_000);
    }
}
stream_set_blocking($conn, true);
$body = "data: hello-from-hub\n\n";
fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);
fclose($conn);
fclose($server);
PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $serverCode, $addrFile],
            [
                0 => ['pipe', 'r'],
                1 => ['file', sys_get_temp_dir().'/santa_mercure_server_out.log', 'a'],
                2 => ['file', sys_get_temp_dir().'/santa_mercure_server_err.log', 'a'],
            ],
            $pipes,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);

        try {
            $deadline = microtime(true) + 3.0;
            $address = '';
            while (microtime(true) < $deadline) {
                $raw = @file_get_contents($addrFile);
                if (is_string($raw) && str_contains($raw, ':')) {
                    $address = trim($raw);
                    break;
                }
                usleep(20_000);
            }
            self::assertNotSame('', $address, 'One-shot HTTP server did not publish its address');

            $controller = new MercureHubProxyController('http://'.$address.'/.well-known/mercure');
            ob_start();
            $controller->streamFromHub(
                'http://'.$address.'/.well-known/mercure?topic=t',
                'Bearer token',
                'mercureAuthorization=jwt',
                2,
            );
            $body = (string) ob_get_clean();
            self::assertStringContainsString('data: hello-from-hub', $body);
        } finally {
            proc_terminate($process);
            proc_close($process);
            @unlink($addrFile);
        }
    }
}
