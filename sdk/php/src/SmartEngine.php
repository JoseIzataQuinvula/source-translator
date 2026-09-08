<?php

declare(strict_types=1);

namespace SourceTranslator;

class SmartEngine
{
    private string $localesDir;
    private string $missingFile;
    private string $cacheFile;
    private string $pendingFile;
    private array $maps = [];
    private array $reverseMaps = [];
    private array $loadedLanguages = [];
    private array $activeLanguages;

    public function __construct(array $activeLanguages = ['pt', 'en'], ?string $baseDir = null)
    {
        $dir = $baseDir ?? __DIR__;
        $this->localesDir = $dir . '/locales/';
        $this->missingFile = $dir . '/missing.json';
        $this->cacheFile = $dir . '/cache_traducoes.json';
        $this->pendingFile = $dir . '/pending_translations.json';
        $this->activeLanguages = array_map('strtolower', $activeLanguages);

        if (!is_dir($this->localesDir)) {
            mkdir($this->localesDir, 0755, true);
        }
    }

    private function loadLanguage(string $lang): bool
    {
        $lang = strtolower($lang);

        if (!in_array($lang, $this->activeLanguages)) {
            return false;
        }

        if (isset($this->loadedLanguages[$lang])) {
            return true;
        }

        $this->loadedLanguages[$lang] = true;
        $this->maps[$lang] = [];
        $this->reverseMaps[$lang] = [];

        $filePath = $this->localesDir . $lang . '.json';

        // Download on-demand if file doesn't exist locally
        if (!file_exists($filePath)) {
            $this->downloadLanguagePackage($lang);
        }

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
            return true;
        }

        return false;
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'pt'): array
    {
        $start = microtime(true);
        $cleanText = trim($text);

        if (empty($cleanText)) {
            return [
                'translated_text' => $text,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'provider' => 'empty',
                'cached' => false,
                'fallback' => false,
                'offline' => false,
                'latency_ms' => 0,
            ];
        }

        $lowerText = mb_strtolower($cleanText, 'UTF-8');
        $sourceLang = strtolower($sourceLang);
        $targetLang = strtolower($targetLang);

        // Load language packages
        $loadedSource = $this->loadLanguage($sourceLang);
        $loadedTarget = $this->loadLanguage($targetLang);

        // 1. Try indexed dictionary (instant, offline)
        if ($loadedSource && $loadedTarget) {
            if (isset($this->reverseMaps[$sourceLang][$lowerText])) {
                $id = $this->reverseMaps[$sourceLang][$lowerText];

                if (isset($this->maps[$targetLang][$id])) {
                    return [
                        'translated_text' => $this->maps[$targetLang][$id],
                        'source_lang' => $sourceLang,
                        'target_lang' => $targetLang,
                        'provider' => 'native_dictionary',
                        'cached' => false,
                        'fallback' => false,
                        'offline' => true,
                        'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                    ];
                }
            }
        }

        // 2. Check local cache
        $cache = $this->loadCache();
        $hashKey = md5($cleanText . '_' . $sourceLang . '_' . $targetLang);

        if (isset($cache[$hashKey])) {
            return [
                'translated_text' => $cache[$hashKey],
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'provider' => 'local_cache',
                'cached' => true,
                'fallback' => false,
                'offline' => true,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }

        // 3. Try web providers
        $providers = [
            new GoogleProvider(),
            new BingProvider(),
            new MyMemoryProvider(),
        ];

        foreach ($providers as $provider) {
            try {
                $result = $provider->translate($cleanText, $sourceLang, $targetLang);

                // Validate translation
                $normalizedResult = mb_strtolower(trim($result['translated_text']));
                $normalizedText = mb_strtolower(trim($cleanText));

                if ($normalizedResult !== $normalizedText || $sourceLang === $targetLang) {
                    // Cache valid translations
                    $cache[$hashKey] = $result['translated_text'];
                    $this->saveCache($cache);
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

        // 4. All providers failed - record missing and add to pending
        $this->recordMissing($cleanText, $sourceLang, $targetLang);
        $this->addToPending($cleanText, $sourceLang, $targetLang);

        // 5. Return original text with fallback flag
        return [
            'translated_text' => $cleanText,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'provider' => 'offline',
            'cached' => false,
            'fallback' => true,
            'offline' => true,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    private function recordMissing(string $text, string $sourceLang, string $targetLang): void
    {
        $missingData = file_exists($this->missingFile)
            ? json_decode(file_get_contents($this->missingFile), true)
            : [];

        if (!is_array($missingData)) {
            $missingData = [];
        }

        $hashKey = md5($text . '_' . $sourceLang . '_' . $targetLang);

        if (!isset($missingData[$hashKey])) {
            $missingData[$hashKey] = [
                'text' => $text,
                'source' => $sourceLang,
                'target' => $targetLang,
                'requested_at' => date('Y-m-d H:i:s'),
                'count' => 1,
            ];
        } else {
            $missingData[$hashKey]['count']++;
            $missingData[$hashKey]['requested_at'] = date('Y-m-d H:i:s');
        }

        file_put_contents(
            $this->missingFile,
            json_encode($missingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function downloadLanguagePackage(string $lang): void
    {
        // Try to download from CDN/GitHub
        $cdnUrl = "https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/{$lang}.json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cdnUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SourceTranslator/1.0');

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $content) {
            // Validate JSON before saving
            $data = json_decode($content, true);
            if (is_array($data)) {
                file_put_contents($this->localesDir . $lang . '.json', $content);
            }
        }
    }

    private function loadCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $content = file_get_contents($this->cacheFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    private function saveCache(array $cache): void
    {
        file_put_contents(
            $this->cacheFile,
            json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function addToPending(string $text, string $source, string $target): void
    {
        $pending = $this->loadPending();

        foreach ($pending as $item) {
            if ($item['text'] === $text && $item['target'] === $target) {
                return;
            }
        }

        $pending[] = [
            'text' => $text,
            'source' => $source,
            'target' => $target,
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

    public function getMissingWords(): array
    {
        if (!file_exists($this->missingFile)) {
            return [];
        }

        $content = file_get_contents($this->missingFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    public function getMissingCount(): int
    {
        return count($this->getMissingWords());
    }

    public function addLanguage(string $lang): void
    {
        $lang = strtolower($lang);
        if (!in_array($lang, $this->activeLanguages)) {
            $this->activeLanguages[] = $lang;
        }
        $this->loadLanguage($lang);
    }

    public function getActiveLanguages(): array
    {
        return $this->activeLanguages;
    }

    public function getStats(): array
    {
        $totalWords = 0;
        foreach ($this->maps as $lang => $words) {
            $totalWords += count($words);
        }

        return [
            'active_languages' => $this->activeLanguages,
            'total_words' => $totalWords,
            'missing_words' => $this->getMissingCount(),
        ];
    }
}
