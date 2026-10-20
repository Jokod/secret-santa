<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dev-only same-origin SSE proxy to the Mercure hub (HTTPS → avoids mixed content).
 *
 * Auth is the native mercureAuthorization cookie (or Authorization header); this
 * controller only forwards the stream. The hub still validates the JWT.
 *
 * In prod, FrankenPHP/Caddy reverse_proxies /.well-known/mercure (see compose.prod.yaml).
 * Symfony CLI 5.20 has no proxy_mercure yet, so local needs this PHP proxy.
 *
 * Uses ext-curl WRITEFUNCTION (not Symfony HttpClient) so PHP-FPM flushes SSE chunks
 * as soon as the hub emits them.
 */
final class MercureHubProxyController extends AbstractController
{
    public function __construct(
        private readonly string $mercureUrl,
    ) {
    }

    #[Route('/.well-known/mercure', name: 'mercure_hub_proxy', methods: ['GET'])]
    public function __invoke(Request $request): StreamedResponse
    {
        $query = $request->getQueryString();
        $hubUrl = $this->mercureUrl.($query ? '?'.$query : '');
        $authorization = $request->headers->get('Authorization');
        $cookie = $request->headers->get('Cookie');

        $response = new StreamedResponse(function () use ($hubUrl, $authorization, $cookie): void {
            $this->streamFromHub($hubUrl, $authorization, $cookie);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Content-Encoding', 'identity');

        return $response;
    }

    /**
     * @param int $timeoutSeconds 0 = no timeout (production SSE). Tests pass a small positive value.
     *
     * @internal Exposed for unit tests that drive the curl stream path.
     */
    public function streamFromHub(string $hubUrl, ?string $authorization, ?string $cookie, int $timeoutSeconds = 0): void
    {
        $this->disableResponseBuffering();

        // @codeCoverageIgnoreStart
        if (!\function_exists('curl_init')) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $ch = curl_init($hubUrl);
        // @codeCoverageIgnoreStart
        if ($ch === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $headers = [
            'Accept: text/event-stream',
            'Accept-Encoding: identity',
        ];
        if ($authorization) {
            $headers[] = 'Authorization: '.$authorization;
        }
        if ($cookie) {
            $headers[] = 'Cookie: '.$cookie;
        }

        curl_setopt_array($ch, [
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1,
            \CURLOPT_TCP_NODELAY => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_TIMEOUT => $timeoutSeconds,
            \CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk): int {
                echo $chunk;
                flush();

                return \strlen($chunk);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function disableResponseBuffering(): void
    {
        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg') {
            return;
        }

        // @codeCoverageIgnoreStart
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
        // @codeCoverageIgnoreEnd
    }
}
