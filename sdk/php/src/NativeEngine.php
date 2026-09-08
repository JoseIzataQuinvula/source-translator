<?php

declare(strict_types=1);

namespace SourceTranslator;

class NativeEngine
{
    private SmartEngine $smartEngine;

    public function __construct(array $activeLanguages = ['pt', 'en'], ?string $baseDir = null)
    {
        $this->smartEngine = new SmartEngine($activeLanguages, $baseDir);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'pt'): array
    {
        return $this->smartEngine->translate($text, $targetLang, $sourceLang);
    }

    public function getMissingWords(): array
    {
        return $this->smartEngine->getMissingWords();
    }

    public function getMissingCount(): int
    {
        return $this->smartEngine->getMissingCount();
    }

    public function addLanguage(string $lang): void
    {
        $this->smartEngine->addLanguage($lang);
    }

    public function getActiveLanguages(): array
    {
        return $this->smartEngine->getActiveLanguages();
    }

    public function getStats(): array
    {
        return $this->smartEngine->getStats();
    }
}
