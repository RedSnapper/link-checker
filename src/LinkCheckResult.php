<?php

namespace RedSnapper\LinkChecker;

class LinkCheckResult
{
    public function __construct(
        public string $originalUrl,
        public string $status,
        public ?int $statusCode = null,
        public ?string $reasonPhrase = null,
        public ?string $effectiveUrl = null,
        public ?string $errorMessage = null,
        public ?string $title = null,
    ) {}

}
