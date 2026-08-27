<?php

namespace App\Domain\Content\Services;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><table><thead><tbody><tr><th><td><img>';

    /** Removes executable/unsafe HTML while preserving a small editorial formatting allow-list. */
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $clean = strip_tags($html, self::ALLOWED_TAGS);
        $clean = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(javascript:|data:text\/html)[^"\']*\2/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/<img([^>]*)src\s*=\s*(["\'])https?:\/\/[^"\']+\2([^>]*)>/iu', '<img$1$3>', $clean) ?? $clean;

        return trim($clean);
    }
}
