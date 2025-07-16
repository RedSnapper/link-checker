<?php

namespace RedSnapper\LinkChecker\Extractor;

use Aws\Lambda\LambdaClient;
use Illuminate\Http\Client\Response;
use RedSnapper\LinkChecker\Contracts\TitleExtractorInterface;
use Throwable;

class PdfTitleExtractor implements TitleExtractorInterface
{
    public function __construct(
        protected LambdaClient $lambdaClient,
        protected array $config
    ) {}

    public function supports(Response $response): bool
    {
        $contentType = $response->header('Content-Type');
        return str_contains(strtolower($contentType ?? ''), 'application/pdf');
    }

    public function extract(Response $response, string $originalUrl): ?string
    {

        try {
            $result = $this->lambdaClient->invoke([
                'FunctionName' => $this->config['lambda_arn'],
                'Payload' => json_encode(['url' => $originalUrl]),
            ]);

            $payload = json_decode($result->get('Payload')->getContents(), true);
            $body = isset($payload['body']) ? json_decode($payload['body'], true) : null;

            if (($payload['statusCode'] ?? 500) !== 200) {
                return null;
            }

            return $body['title'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
