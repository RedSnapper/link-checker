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

    public function extract(Response $response, string $originalUrl, array $options = []): ?string
    {
        if (empty($this->lambdaClient) || empty($this->config['lambda_arn'])) {
            return null;
        }

        // Build the payload for the Lambda function, including the headers
        $payload = [
            'url' => $originalUrl,
            'headers' => $options['headers'] ?? null,
        ];

        try {
            $result = $this->lambdaClient->invoke([
                'FunctionName' => $this->config['lambda_arn'],
                'Payload' => json_encode(array_filter($payload)), // array_filter removes null headers
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
