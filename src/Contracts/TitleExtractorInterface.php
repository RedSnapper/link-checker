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
     */
    public function extract(Response $response, string $originalUrl): ?string;
}
