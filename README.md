# Laravel Link Checker

A robust and flexible package for checking the status of URLs within your Laravel application. It concurrently checks a list of URLs, follows redirects, and intelligently extracts page titles from both HTML pages and PDF documents.

PDF title extraction is handled via a configurable AWS Lambda function, allowing for powerful, serverless processing of PDF metadata. The package is designed with a clean, extensible architecture, making it easy to add new title extraction strategies in the future.

## Installation

You can install the package via composer:

```bash
composer require rs/link-checker
```

## Configuration

First, publish the configuration file to your application's `config` directory:

```bash
php artisan vendor:publish --provider="RedSnapper\LinkChecker\LinkCheckerServiceProvider" --tag="link-checker-config"
```

This will create a `config/link-checker.php` file.

### PDF Title Extraction (Optional)

To enable title extraction from PDF documents, you must configure your AWS credentials and specify the ARN of your deployed Lambda function in your `.env` file.

The package will automatically use the official AWS SDK for PHP, which looks for credentials in the following order:

1.  IAM Role (Recommended for production environments like EC2 or ECS)
2.  Environment variables

```dotenv
# .env

# Your default AWS region
AWS_REGION=eu-west-1

# Credentials (only needed if not using an IAM Role)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key

# The full ARN of your PDF Title Extractor Lambda function
AWS_PDF_TITLE_LAMBDA_ARN=arn:aws:lambda:us-east-1:123456789012:function:PdfTitleExtractor
```

If `AWS_PDF_TITLE_LAMBDA_ARN` is not set, the package will gracefully skip PDF title extraction without causing errors.

## Usage

### Using the Facade

You can use the `LinkChecker` facade to check an array of URLs. The `check` method returns a `Collection` of `LinkCheckResult` objects.

```php
use RedSnapper\LinkChecker\Facades\LinkChecker;

$urls = [
    '[https://www.google.com](https://www.google.com)', // Will extract HTML title
    '[https://example.com/document.pdf](https://example.com/document.pdf)', // Will extract PDF title (if configured)
    '[https://example.com/broken-link](https://example.com/broken-link)', // Will report a 404 error
];

$results = LinkChecker::check($urls);

foreach ($results as $result) {
    echo "URL: " . $result->originalUrl . "\n";
    echo "Status: " . $result->status . "\n"; // e.g., 'ok', 'client_error'
    echo "Status Code: " . $result->statusCode . "\n"; // e.g., 200, 404
    echo "Title: " . $result->title . "\n"; // e.g., 'Google', 'My Awesome PDF Title'
    echo "Error: " . $result->errorMessage . "\n\n";
}
```

### Using Dependency Injection

For developers who prefer dependency injection over facades, you can type-hint the `LinkCheckerInterface` in your class constructor or method. Laravel's service container will automatically resolve the correct implementation.

```php
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;

class YourService
{
    protected $linkChecker;

    public function __construct(LinkCheckerInterface $linkChecker)
    {
        $this->linkChecker = $linkChecker;
    }

    public function checkSomeLinks()
    {
        $urls = ['[https://example.com](https://example.com)'];
        
        $results = $this->linkChecker->check($urls);
        
        // ... do something with the results
    }
}
```

### Passing Custom Options

The `check` method accepts an optional second argument for custom options, such as headers and a referrer. These options will be used for both the initial URL check and will be passed to the PDF extractor Lambda for maximum authenticity.

```php
use RedSnapper\LinkChecker\Facades\LinkChecker;

$pageUrl = '[https://www.my-client.com/links-page](https://www.my-client.com/links-page)';
$pdfUrl = '[https://example.com/document.pdf](https://example.com/document.pdf)';

$results = LinkChecker::check(
    [$pdfUrl],
    [
        'referrer' => $pageUrl,
        'headers' => ['X-Custom-Request-ID' => '12345-abcde']
    ]
);

// The Lambda function will be invoked with both the Referer and X-Custom-Request-ID headers.
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email param@redsnapper.net instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
