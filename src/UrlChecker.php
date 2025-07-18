<?php

namespace RedSnapper\LinkChecker;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;
use RedSnapper\LinkChecker\Extractor\TitleExtractorManager;
use Throwable;

class UrlChecker implements LinkCheckerInterface
{

    protected int $timeout;

    protected int $connectTimeout;

    protected int $tries;

    protected int $retryDelay;

    protected array $defaultHeaders = [
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
        'Accept' => '*/*',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Cache-Control' => 'no-cache',
        'DNT' => '1',
    ];

    public function __construct(protected TitleExtractorManager $titleExtractorManager,array $config = [])
    {

        $this->timeout = $config['timeout'] ?? 30;
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->tries = $config['retries'] ?? 1;
        $this->retryDelay = $config['retry_delay'] ?? 100;
        $this->defaultHeaders = array_merge($this->defaultHeaders, $config['default_headers'] ?? []);

    }

    public function check(array $urls, array $options = []): Collection
    {

        $timeout = $options['timeout'] ?? $this->timeout;
        $connectTimeout = $options['connect_timeout'] ?? $this->connectTimeout;
        $retries = $options['retries'] ?? $this->tries;
        $retryDelay = $options['retry_delay'] ?? $this->retryDelay;

        $headers = array_merge(
            $this->defaultHeaders,
            $options['headers'] ?? []
        );

        if (isset($options['referrer'])) {
            $headers['Referer'] = $options['referrer'];
        }

        // Add the final headers back into the options to be passed down
        $finalOptions = array_merge($options, ['headers' => $headers]);


        $responses = Http::pool(fn(Pool $pool) => array_map(fn($url
        ) => $pool->withHeaders(array_filter($headers))
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry($retries, $retryDelay)
            ->get($url), $urls
        ));

        return collect($responses)->map(fn($response, $index) => $this->handleResponse($response, $urls[$index],$finalOptions)

        );

    }


    protected function handleResponse($response, $url, array $options = []): LinkCheckResult
    {
        if ($response instanceof Response) {
            return $this->handleHTTPResponse($response, $url ,$options);
        }

        return $this->handleError($response, $url);
    }

    private function handleHTTPResponse(Response $response, $url, array $options = []): LinkCheckResult
    {

        $title = $response->successful() ? $this->titleExtractorManager->extract($response, $url ,$options) : null;
        $errorMessage = null;

        if (!$response->successful()) { // If not 2xx
            $errorMessage = "HTTP Error: {$response->status()}";
            $errorMessage .= $response->reason() ? " {$response->reason()}" : "";
        }

        return new LinkCheckResult(
            originalUrl: $url,
            status: $this->determineStatus($response),
            statusCode: $response->status(),
            reasonPhrase: $response->reason(),
            effectiveUrl: $response->effectiveUri(),
            errorMessage: $errorMessage,
            title: $title
        );
    }

    private function handleError($error, $url): LinkCheckResult
    {

        $status = 'error';
        $errorMessage = '';
        if ($error instanceof ConnectionException) {
            $status = 'connection_error';
            $errorMessage = 'Could not connect: '.$error->getMessage();
        } elseif ($error instanceof Throwable) {
            $errorMessage = 'An unexpected error occurred: '.$error->getMessage();
        }

        return new LinkCheckResult(
            originalUrl: $url,
            status: $status,
            effectiveUrl: $url,
            errorMessage: $errorMessage,
        );
    }

    private function determineStatus(Response $response): string
    {
        $statusCode = $response->status();
        if ($response->status() === null) {
            return 'unknown';
        }
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'ok';
        }
        if ($statusCode >= 300 && $statusCode < 400) {
            return 'redirect';
        }
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        }
        if ($statusCode >= 500 && $statusCode < 600) {
            return 'server_error';
        }

        return 'unknown';
    }


}
