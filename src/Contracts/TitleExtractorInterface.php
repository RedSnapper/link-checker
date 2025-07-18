<?php

namespace RedSnapper\LinkChecker\Contracts;

use Illuminate\Http\Client\Response;

interface TitleExtractorInterface
{
    /**
     * Check if this extractor can handle the given response.
     */
    public function supports(Response $response): bool;

    /**
     * Extract the title.
     *
     * @param array $options Optional data, may contain headers.
     */
    public function extract(Response $response, string $originalUrl, array $options = []): ?string;
}
