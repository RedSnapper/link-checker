<?php


return [

    /*
    |--------------------------------------------------------------------------
    | Request options
    |--------------------------------------------------------------------------
    */

    'timeout' => 30,
    'connect_timeout' => 10,
    'retries' => 1,
    'retry_delay' => 100,
    'default_headers' => [],

    'pdf' => [
        'lambda_arn' => env('AWS_PDF_TITLE_LAMBDA_ARN'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
    ],

];
