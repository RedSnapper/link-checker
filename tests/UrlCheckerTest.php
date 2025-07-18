<?php

use Aws\Lambda\LambdaClient;
use Aws\Result;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;
use RedSnapper\LinkChecker\LinkCheckResult;
use Mockery as m;


it('checks a successful HTML page and extracts its title', function () {
    // Arrange
    $url = 'https://example.com/page';
    $htmlContent = '<html><head><title>My Awesome Page &amp; More</title></head><body><h1>Hello</h1></body></html>';

    Http::fake([
        $url => Http::response($htmlContent, 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);

    $checker = app(LinkCheckerInterface::class);

    // Act
    $results = $checker->check([$url]);

    // Assert
    Http::assertSent(fn (Request $request) => $request->url() === $url);

    expect($results)->toHaveCount(1);
    $result = $results->first();

    expect($result)->toBeInstanceOf(LinkCheckResult::class)
        ->and($result->originalUrl)->toBe($url)
        ->and($result->status)->toBe('ok')
        ->and($result->statusCode)->toBe(200)
        ->and($result->reasonPhrase)->toBe('OK')
        ->and($result->effectiveUrl)->toBe($url)
        ->and($result->title)->toBe('My Awesome Page & More')
        ->and($result->errorMessage)->toBeNull();
});

test('it handles redirects correctly', function () {
    $originalUrl = 'https://old.example.com/old-page';
    $redirectedUrl = 'https://new.example.com/new-page';
    $finalContent = '<html><head><title>New Page</title></head><body></body></html>';

    // Fake the responses in sequence for the original URL
    Http::fake([
        $originalUrl => Http::sequence()
            // First, return a 302 Found redirect to the new URL
            ->push(null, 302, ['Location' => $redirectedUrl])
    ]);

    Http::fake([
        $redirectedUrl => Http::response($finalContent, 200, ['Content-Type' => 'text/html']),
        // Ensure the redirected URL also has a faked response.
        // This is crucial because Http client will make a *new* request to the Location header.
    ]);


    $checker = app(LinkCheckerInterface::class);

    // Act
    $results = $checker->check([$originalUrl]);

    // Assert
    // Verify that requests were sent to both the original and the redirected URL
    Http::assertSentInOrder([
        fn (Request $request) => $request->url() === $originalUrl, // The initial request
        fn (Request $request) => $request->url() === $redirectedUrl, // The request after following redirect
    ]);

    expect($results)->toHaveCount(1);
    $result = $results->first();

    expect($result)
        ->originalUrl->toBe($originalUrl)
        ->status->toBe('ok')
        ->statusCode->toBe(200)
        ->effectiveUrl->toBe($redirectedUrl)
        ->title->toBe('New Page')
        ->errorMessage->toBeNull();
});

test('it handles client errors correctly', function () {

    $url = "https://example.com/not-found";
    Http::fake([
        $url => Http::response(null, 404),
    ]);

    $checker = app(LinkCheckerInterface::class);
    $results = $checker->check([$url]);

    expect($results)->toHaveCount(1)
        ->and($results->first())
            ->toBeInstanceOf(LinkCheckResult::class)
            ->status->toBe('client_error')
            ->statusCode->toBe(404)
            ->reasonPhrase->toBe('Not Found');

});

test('it handles server errors correctly', function () {

    $url = "https://example.com/server-error";

    Http::fake([
        $url => Http::response(null, 500),
    ]);

    $checker = app(LinkCheckerInterface::class);
    $results = $checker->check([$url]);

    expect($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(LinkCheckResult::class)
        ->status->toBe('server_error')
        ->statusCode->toBe(500)
        ->reasonPhrase->toBe('Internal Server Error')
        ->errorMessage->toBe('HTTP Error: 500 Internal Server Error');
});

test('it handles connection errors correctly', function () {

    $url = "https://non-existent-domain.example";

    Http::fake([
        $url => Http::failedConnection()
    ]);

    $checker = app(LinkCheckerInterface::class);
    $results = $checker->check([$url]);

    expect($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(LinkCheckResult::class)
        ->and($results->first()->status)->toBe('connection_error')
        ->and($results->first()->errorMessage)->toContain('Could not connect');
});



test('it can check multiple URLs in parallel', function () {
    Http::fake([
        'example.com' => Http::response('<html><head><title>Example Domain</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        'example.org' => Http::response('<html><head><title>Another Domain</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        'example.net/not-found' => Http::response(null, 404),
    ]);

    $checker = app(LinkCheckerInterface::class);
    $results = $checker->check([
        'https://example.com',
        'https://example.org',
        'https://example.net/not-found',
    ]);

    expect($results)->toHaveCount(3)
        ->and($results[0]->status)->toBe('ok')
        ->and($results[0]->title)->toBe('Example Domain')
        ->and($results[1]->status)->toBe('ok')
        ->and($results[1]->title)->toBe('Another Domain')
        ->and($results[2]->status)->toBe('client_error')
        ->and($results[2]->statusCode)->toBe(404);
});

it('applies custom options for timeout, connect timeout, retries, retry delay', function () {
    // Arrange
    $url = 'https://slow.example.com';
    $htmlContent = '<html><head><title>Slow Page</title></head><body></body></html>';

    Http::fake([
        $url => Http::response($htmlContent, 200),
    ]);

    $customOptions = [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retries' => 3,
        'retry_delay' => 500, // 500ms delay
        'headers' => [
            'X-Custom-Header' => 'CustomValue',
        ],
        'referrer' => 'https://my-app.com',
    ];

    $checker = app(LinkCheckerInterface::class);

    // Act
    $results = $checker->check([$url], $customOptions);

    // Assert
    Http::assertSent(function (Request $request) use ($url, $customOptions) {
        // Assert that the request was made to the correct URL
        expect($request->url())->toBe($url);

        // Assert that custom headers are applied (including Referer)
        expect($request->hasHeader('X-Custom-Header'))->toBeTrue();
        expect($request->header('X-Custom-Header')[0])->toBe('CustomValue');
        expect($request->hasHeader('Referer'))->toBeTrue();
        expect($request->header('Referer')[0])->toBe($customOptions['referrer']);

        return true;
    });

    expect($results)->toHaveCount(1);
    expect($results->first()->status)->toBe('ok');
});

it('handles malformed HTML gracefully and returns null for title', function () {
    // Arrange
    $url = 'https://bad-html.com';
    $malformedHtml = '<html><head><title>Valid Title</title><body><div><p>Unclosed tag here.'; // Missing closing tags, bad structure

    Http::fake([
        $url => Http::response($malformedHtml, 200, ['Content-Type' => 'text/html']),
    ]);

    $checker = app(LinkCheckerInterface::class);

    // Act
    $results = $checker->check([$url]);

    // Assert
    expect($results)->toHaveCount(1);
    $result = $results->first();

    expect($result->originalUrl)->toBe($url);
    expect($result->status)->toBe('ok');
    expect($result->title)->toBe('Valid Title'); // simple_html_dom should still find it
});

it('can call a lambda function to extract the title', function () {
    // 1. Arrange: Configure the package to use the PDF extractor
    config()->set('link-checker.pdf.lambda_arn', 'arn:aws:lambda:us-east-1:123456789012:function:test-function');

    // Set a default header to test merging logic
    config()->set('link-checker.default_headers', ['User-Agent' => 'Test User Agent']);

    // Define the final, merged headers we expect to be passed to the Lambda

    // 2. Arrange: Mock the LambdaClient
    $this->mock(LambdaClient::class, function ($mock) {



        // This is the expected response from our Python Lambda
        $lambdaResponsePayload = [
            'statusCode' => 200,
            'body' => json_encode([
                'status' => 'success',
                'title' => 'My Awesome PDF Title'
            ])
        ];

        // We need to build a mock AWS Result object to return
        $stream = m::mock(Stream::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode($lambdaResponsePayload));
        $mockAwsResult = m::mock(Result::class);
        $mockAwsResult->shouldReceive('get')->with('Payload')->andReturn($stream);

        // Tell the mock client to expect a call to 'invoke' and return our mock result
        $mock->shouldReceive('invoke')
            ->once()
            ->with(m::on(function ($arg) {
                $payload = json_decode($arg['Payload'], true);

                $expectedHeaders = [
                    'User-Agent' => 'Test User Agent', // From default config
                    'Referer' => 'https://my-test-page.com' // From options
                ];

                // Assert that the payload sent to Lambda contains the correct URL and merged headers
                expect($payload['url'])->toBe('https://example.com/document.pdf')
                    ->and($payload['headers']['User-Agent'])->toBe('Test User Agent')
                    ->and($payload['headers']['X-Custom-Header'])->toBe('CustomValue')
                    ->and($payload['headers']['Referer'])->toBe('https://my-test-page.com');

                return true; // Return true to indicate the arguments are valid
            }))
            ->andReturn($mockAwsResult);
    });

    // 3. Arrange: Fake the initial HTTP response to identify the URL as a PDF
    $pdfUrl = 'https://example.com/document.pdf';
    Http::fake([
        $pdfUrl => Http::response(null, 200, ['Content-Type' => 'application/pdf']),
    ]);

    // 4. Act: Run the checker
    $checker = app(LinkCheckerInterface::class);
    $results = $checker->check([$pdfUrl],[
        'referrer' => 'https://my-test-page.com',
        'headers' => ['X-Custom-Header' => 'CustomValue']
    ]);

    // 5. Assert
    expect($results)->toHaveCount(1);
    $result = $results->first();

    expect($result->status)->toBe('ok')
        ->and($result->title)->toBe('My Awesome PDF Title');
});
