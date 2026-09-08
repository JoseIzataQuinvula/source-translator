<?php

declare(strict_types=1);

namespace SourceTranslator;

class SourceTranslator
{
    private Cache $cache;
    private array $providers;
    private string $pendingFile;

    private const SUPPORTED_LANGUAGES = [
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)'],
        ['code' => 'pt-AO', 'name' => 'Portuguese (Angola)'],
        ['code' => 'pt-PT', 'name' => 'Portuguese (Portugal)'],
        ['code' => 'es', 'name' => 'Spanish'],
        ['code' => 'fr', 'name' => 'French'],
        ['code' => 'de', 'name' => 'German'],
        ['code' => 'ja', 'name' => 'Japanese'],
        ['code' => 'ko', 'name' => 'Korean'],
        ['code' => 'zh-CN', 'name' => 'Chinese (Simplified)'],
        ['code' => 'ru', 'name' => 'Russian'],
        ['code' => 'ar', 'name' => 'Arabic'],
    ];

    public function __construct(?string $cacheDir = null, int $maxCacheAge = 86400)
    {
        $this->cache = new Cache($cacheDir, $maxCacheAge);
        $this->pendingFile = ($cacheDir ?? getenv('HOME') . '/.source-translator') . '/pending_translations.json';
        $this->providers = [
            new GoogleProvider(),
            new BingProvider(),
            new MyMemoryProvider(),
        ];
    }

    public function translate(string $text, string $sourceLang, string $targetLang): array
    {
        $start = microtime(true);

        // 1. Check cache first (works offline)
        $cached = $this->cache->get($text, $targetLang);
        if ($cached !== null) {
            return [
                'translated_text' => $cached['translated_text'],
                'source_lang' => $cached['source_lang'],
                'target_lang' => $cached['target_lang'],
                'provider' => $cached['provider'],
                'cached' => true,
                'fallback' => false,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }

        // 2. Try each provider with fallback
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->translate($text, $sourceLang, $targetLang);

                // Validate translation
                $normalizedResult = mb_strtolower(trim($result['translated_text']));
                $normalizedText = mb_strtolower(trim($text));

                if ($normalizedResult !== $normalizedText || $sourceLang === $targetLang) {
                    // Cache valid translations
                    $this->cache->set(
                        $text,
                        $sourceLang,
                        $targetLang,
                        $result['translated_text'],
                        $result['provider']
                    );
                }

                return [
                    'translated_text' => $result['translated_text'],
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                    'provider' => $result['provider'],
                    'cached' => false,
                    'fallback' => false,
                    'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                ];
            } catch (\Exception $e) {
                error_log("Provider {$provider->getName()} failed: " . $e->getMessage());
                continue;
            }
        }

        // 3. All providers failed - save to pending queue for later
        $this->addToPending($text, $sourceLang, $targetLang);

        // 4. Return original text with fallback flag (no ugly error)
        return [
            'translated_text' => $text,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'provider' => 'offline',
            'cached' => false,
            'fallback' => true,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    public function translateBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $request) {
            $results[] = $this->translate(
                $request['text'],
                $request['source_lang'],
                $request['target_lang']
            );
        }

        return $results;
    }

    public function processPending(): int
    {
        $pending = $this->loadPending();
        if (empty($pending)) {
            return 0;
        }

        $processed = 0;
        $remaining = [];

        foreach ($pending as $item) {
            $result = $this->translate($item['text'], $item['source_lang'], $item['target_lang']);

            if (!$result['fallback']) {
                $processed++;
            } else {
                $remaining[] = $item;
            }
        }

        $this->savePending($remaining);
        return $processed;
    }

    private function addToPending(string $text, string $sourceLang, string $targetLang): void
    {
        $pending = $this->loadPending();

        // Check if already pending
        foreach ($pending as $item) {
            if ($item['text'] === $text && $item['target_lang'] === $targetLang) {
                return;
            }
        }

        $pending[] = [
            'text' => $text,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'added_at' => time(),
        ];

        $this->savePending($pending);
    }

    private function loadPending(): array
    {
        if (!file_exists($this->pendingFile)) {
            return [];
        }

        $content = file_get_contents($this->pendingFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    private function savePending(array $pending): void
    {
        $dir = dirname($this->pendingFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->pendingFile,
            json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function getPendingCount(): int
    {
        return count($this->loadPending());
    }

    public static function getLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    public function cacheStats(): array
    {
        return $this->cache->stats();
    }
}
