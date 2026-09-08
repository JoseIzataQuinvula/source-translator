<?php

declare(strict_types=1);

namespace SourceTranslator;

class SourceTranslator
{
    private Cache $cache;
    private array $providers;

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
        $this->providers = [
            new GoogleProvider(),
            new BingProvider(),
        ];
    }

    public function translate(string $text, string $sourceLang, string $targetLang): array
    {
        $start = microtime(true);

        $cached = $this->cache->get($text, $targetLang);
        if ($cached !== null) {
            return [
                'translated_text' => $cached['translated_text'],
                'source_lang' => $cached['source_lang'],
                'target_lang' => $cached['target_lang'],
                'provider' => $cached['provider'],
                'cached' => true,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->translate($text, $sourceLang, $targetLang);

                // Validate: don't cache if translation is identical to source (different languages)
                $normalizedResult = mb_strtolower(trim($result['translated_text']));
                $normalizedText = mb_strtolower(trim($text));
                
                if ($normalizedResult !== $normalizedText || $sourceLang === $targetLang) {
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
                    'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                ];
            } catch (\Exception $e) {
                error_log("Provider {$provider->getName()} failed: " . $e->getMessage());
                continue;
            }
        }

        throw new \RuntimeException('All translation providers failed');
    }

    public function translateBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $request) {
            try {
                $results[] = $this->translate(
                    $request['text'],
                    $request['source_lang'],
                    $request['target_lang']
                );
            } catch (\Exception $e) {
                error_log("Batch translation failed: " . $e->getMessage());
            }
        }

        return $results;
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
