<?php

declare(strict_types=1);

namespace SourceTranslator;

class IndexedEngine
{
    private string $localesDir;
    private array $maps = [];
    private array $reverseMaps = [];
    private array $loadedLanguages = [];

    public function __construct(?string $baseDir = null)
    {
        $dir = $baseDir ?? __DIR__;
        $this->localesDir = $dir . '/locales/';
    }

    private function loadLanguage(string $lang): void
    {
        if (isset($this->loadedLanguages[$lang])) {
            return;
        }

        $this->loadedLanguages[$lang] = true;
        $this->maps[$lang] = [];
        $this->reverseMaps[$lang] = [];

        $filePath = $this->localesDir . $lang . '.json';
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);

            if (is_array($data)) {
                $this->maps[$lang] = $data;

                // Build reverse map: Text -> ID
                foreach ($data as $id => $text) {
                    $cleanText = mb_strtolower(trim($text), 'UTF-8');
                    $this->reverseMaps[$lang][$cleanText] = $id;
                }
            }
        }
    }

    public function translate(string $text, string $sourceLang, string $targetLang): array
    {
        $cleanText = mb_strtolower(trim($text), 'UTF-8');

        // Load both language files
        $this->loadLanguage($sourceLang);
        $this->loadLanguage($targetLang);

        // Check if we have the source language
        if (empty($this->reverseMaps[$sourceLang])) {
            return [
                'translated_text' => $text,
                'found' => false,
                'mode' => 'no_source_language',
            ];
        }

        // Find the ID for the source text
        if (isset($this->reverseMaps[$sourceLang][$cleanText])) {
            $id = $this->reverseMaps[$sourceLang][$cleanText];

            // Look up the same ID in the target language
            if (isset($this->maps[$targetLang][$id])) {
                return [
                    'translated_text' => $this->maps[$targetLang][$id],
                    'id' => $id,
                    'found' => true,
                    'mode' => 'indexed_exact',
                ];
            }
        }

        // Not found - return original text
        return [
            'translated_text' => $text,
            'id' => null,
            'found' => false,
            'mode' => 'fallback_original',
        ];
    }

    public function translateBatch(array $texts, string $sourceLang, string $targetLang): array
    {
        $results = [];
        foreach ($texts as $text) {
            $results[] = $this->translate($text, $sourceLang, $targetLang);
        }
        return $results;
    }

    public function addWord(string $id, string $text, string $lang): void
    {
        $this->loadLanguage($lang);

        $this->maps[$lang][$id] = $text;
        $cleanText = mb_strtolower(trim($text), 'UTF-8');
        $this->reverseMaps[$lang][$cleanText] = $id;

        // Save to file
        $filePath = $this->localesDir . $lang . '.json';
        file_put_contents($filePath, json_encode($this->maps[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function getAvailableLanguages(): array
    {
        $languages = [];
        $files = glob($this->localesDir . '*.json');

        foreach ($files as $file) {
            $lang = basename($file, '.json');
            $languages[] = $lang;
        }

        return $languages;
    }

    public function getStats(): array
    {
        $totalWords = 0;
        $languageCount = 0;

        foreach ($this->maps as $lang => $words) {
            $totalWords += count($words);
            $languageCount++;
        }

        return [
            'languages' => $languageCount,
            'total_words' => $totalWords,
            'available' => $this->getAvailableLanguages(),
        ];
    }
}
