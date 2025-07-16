<?php

namespace RedSnapper\LinkChecker\Contracts;

use Illuminate\Support\Collection;
use RedSnapper\LinkChecker\LinkCheckResult;

interface LinkCheckerInterface
{
    /**
     * Check an array of URLs and return their status and additional information.
     *
     * @param  array  $urls  An array of URLs to check.
     * @param  array  $options  Optional: ['timeout' => int, 'retries' => int]
     * @return Collection<LinkCheckResult>
     */
    public function check(array $urls, array $options = []): Collection;
}
