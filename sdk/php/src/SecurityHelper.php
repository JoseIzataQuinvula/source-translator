<?php

declare(strict_types=1);

namespace SourceTranslator;

class SecurityHelper
{
    private const LOCALE_REGEX = '/^[a-zA-Z0-9_-]+$/';

    public static function sanitizeInput(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        if (mb_strlen($text, 'UTF-8') > 10000) {
            $text = mb_substr($text, 0, 10000, 'UTF-8');
        }
        return $text;
    }

    public static function validateLocale(string $locale): string
    {
        if (preg_match(self::LOCALE_REGEX, $locale) !== 1) {
            throw new \InvalidArgumentException("Invalid locale code: {$locale}");
        }
        return $locale;
    }

    public static function isSecureUrl(string $url): bool
    {
        return strpos($url, 'https://') === 0;
    }
}
