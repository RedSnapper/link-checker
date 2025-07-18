<?php

namespace RedSnapper\LinkChecker\Extractor;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RedSnapper\LinkChecker\Contracts\TitleExtractorInterface;
use Throwable;
use voku\helper\HtmlDomParser;

class HtmlTitleExtractor implements TitleExtractorInterface
{
    public function supports(Response $response): bool
    {
        $contentType = $response->header('Content-Type');
        return str_contains(strtolower($contentType ?? ''), 'text/html');
    }

    public function extract(Response $response, string $originalUrl, array $options = []): ?string
    {
        $htmlContent = $response->body();
        if (empty($htmlContent)) {
            return null;
        }

        try {
            $dom = HtmlDomParser::str_get_html($htmlContent);
            $titleElement = $dom->find('title', 0);

            if ($titleElement) {
                $title = $titleElement->text();
                $title = Str::squish(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                return $title !== '' ? $title : null;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
