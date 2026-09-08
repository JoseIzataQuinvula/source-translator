<?php

declare(strict_types=1);

namespace SourceTranslator;

class SourceTranslator
{
    private NativeEngine $nativeEngine;
    private Cache $cache;
    private array $providers;
    private string $pendingFile;

    private const SUPPORTED_LANGUAGES = [
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'pt', 'name' => 'Portuguese'],
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

    public function __construct(?string $baseDir = null, int $maxCacheAge = 86400)
    {
        $dir = $baseDir ?? __DIR__;
        $this->nativeEngine = new NativeEngine($dir);
        $this->cache = new Cache($dir, $maxCacheAge);
        $this->pendingFile = $dir . '/pending_translations.json';
        $this->providers = [
            new GoogleProvider(),
            new BingProvider(),
            new MyMemoryProvider(),
        ];
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'pt'): array
    {
        $start = microtime(true);

        // 1. Try native dictionary first (instant, offline)
        $nativeResult = $this->nativeEngine->translate($text, $targetLang, $sourceLang);
        
        if ($nativeResult['provider'] === 'native_dictionary') {
            $nativeResult['latency_ms'] = (int) ((microtime(true) - $start) * 1000);
            return $nativeResult;
        }

        // 2. Check cache
        $cached = $this->cache->get($text, $targetLang);
        if ($cached !== null) {
            return [
                'translated_text' => $cached['translated_text'],
                'source_lang' => $cached['source_lang'],
                'target_lang' => $cached['target_lang'],
                'provider' => $cached['provider'],
                'cached' => true,
                'fallback' => false,
                'offline' => false,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }

        // 3. Try web providers with fallback
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
                    'offline' => false,
                    'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                ];
            } catch (\Exception $e) {
                error_log("Provider {$provider->getName()} failed: " . $e->getMessage());
                continue;
            }
        }

        // 4. All providers failed - add to pending queue
        $this->addToPending($text, $sourceLang, $targetLang);

        // 5. Return original text with fallback flag
        return [
            'translated_text' => $text,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'provider' => 'offline',
            'cached' => false,
            'fallback' => true,
            'offline' => true,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    public function translateBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $request) {
            $results[] = $this->translate(
                $request['text'],
                $request['target_lang'],
                $request['source_lang'] ?? 'pt'
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
            $result = $this->translate($item['text'], $item['target'], $item['source']);

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

        foreach ($pending as $item) {
            if ($item['text'] === $text && $item['target'] === $targetLang) {
                return;
            }
        }

        $pending[] = [
            'text' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'timestamp' => time(),
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

    public function nativeStats(): array
    {
        return $this->nativeEngine->getDictionaryStats();
    }
}
