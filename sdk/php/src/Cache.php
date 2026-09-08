<?php

declare(strict_types=1);

namespace SourceTranslator;

class Cache
{
    private string $filePath;
    private int $maxAge;
    private array $data = [];

    public function __construct(?string $cacheDir = null, int $maxAge = 86400)
    {
        $dir = $cacheDir ?? getenv('HOME') . '/.source-translator';
        $this->filePath = $dir . '/cache.json';
        $this->maxAge = $maxAge;
        $this->load();
    }

    private function load(): void
    {
        if (!file_exists($this->filePath)) {
            $this->data = [];
            return;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            $this->data = [];
            return;
        }

        $decoded = json_decode($content, true);
        $this->data = is_array($decoded) ? $decoded : [];
    }

    private function save(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function computeHash(string $text, string $targetLang): string
    {
        return hash('sha256', $text . $targetLang);
    }

    public function get(string $text, string $targetLang): ?array
    {
        $hash = $this->computeHash($text, $targetLang);

        if (!isset($this->data[$hash])) {
            return null;
        }

        $entry = $this->data[$hash];

        if ((time() - $entry['created_at']) > $this->maxAge) {
            unset($this->data[$hash]);
            $this->save();
            return null;
        }

        return $entry;
    }

    public function set(
        string $text,
        string $sourceLang,
        string $targetLang,
        string $translation,
        string $provider
    ): void {
        $hash = $this->computeHash($text, $targetLang);

        $this->data[$hash] = [
            'text_hash' => $hash,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'original_text' => $text,
            'translated_text' => $translation,
            'provider' => $provider,
            'created_at' => time(),
        ];

        $this->save();
    }

    public function stats(): array
    {
        $providers = [];
        foreach ($this->data as $entry) {
            $providers[$entry['provider']] = true;
        }

        return [
            'total_entries' => count($this->data),
            'providers_used' => array_keys($providers),
        ];
    }
}
