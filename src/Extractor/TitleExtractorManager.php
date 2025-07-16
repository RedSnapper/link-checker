<?php

namespace RedSnapper\LinkChecker\Extractor;

use Illuminate\Http\Client\Response;
use RedSnapper\LinkChecker\Contracts\TitleExtractorInterface;

class TitleExtractorManager
{
    /** @var TitleExtractorInterface[] */
    protected array $extractors = [];

    public function __construct(array $extractors = [])
    {
        $this->extractors = $extractors;
    }

    public function extract(Response $response, string $originalUrl): ?string
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($response)) {
                return $extractor->extract($response, $originalUrl);
            }
        }

        return null;
    }
}