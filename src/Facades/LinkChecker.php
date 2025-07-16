<?php

namespace RedSnapper\LinkChecker\Facades;

use Illuminate\Support\Facades\Facade;
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;
/**
 * @method static \Illuminate\Support\Collection check(array $urls, array $options = [])
 * @see \RedSnapper\LinkChecker\Contracts\LinkCheckerInterface
 */
class LinkChecker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return LinkCheckerInterface::class; // Resolves to the correct interface binding
    }
}