<?php

namespace RedSnapper\LinkChecker\Tests;

use RedSnapper\LinkChecker\LinkCheckerServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LinkCheckerServiceProvider::class,
        ];
    }
}
