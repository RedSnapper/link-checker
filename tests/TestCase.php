<?php

namespace RedSnapper\LinkCheck\Tests;

use RedSnapper\LinkCheck\LinkCheckServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LinkCheckServiceProvider::class,
        ];
    }
}