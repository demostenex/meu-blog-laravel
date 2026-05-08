<?php

namespace App\Services;

use App\Contracts\AiService;

abstract class AbstractAiService implements AiService
{
    public function translateHtml(string $html): string
    {
        $urls    = [];
        $counter = 0;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        foreach (['href', 'src', 'data-src'] as $attr) {
            foreach ($xpath->query("//*[@{$attr}]") as $node) {
                $counter++;
                $key        = "TRANSURL{$counter}";
                $urls[$key] = $node->getAttribute($attr);
                $node->setAttribute($attr, $key);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $withPlaceholders = '';
        foreach ($body->childNodes as $node) {
            $withPlaceholders .= $dom->saveHTML($node);
        }

        $translated = $this->translateText($withPlaceholders);

        foreach ($urls as $key => $url) {
            $translated = str_replace($key, $url, $translated);
        }

        return $translated;
    }
}
