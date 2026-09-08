<?php

declare(strict_types=1);

namespace SourceTranslator;

class NativeEngine
{
    private IndexedEngine $indexedEngine;
    private string $cacheFile;
    private string $pendingFile;

    public function __construct(?string $baseDir = null)
    {
        $dir = $baseDir ?? __DIR__;
        $this->indexedEngine = new IndexedEngine($dir);
        $this->cacheFile = $dir . '/cache_traducoes.json';
        $this->pendingFile = $dir . '/pending_translations.json';
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'pt'): array
    {
        $cleanText = trim($text);

        // 1. Try indexed dictionary (instant, offline)
        $indexedResult = $this->indexedEngine->translate($cleanText, $sourceLang, $targetLang);

        if ($indexedResult['found']) {
            return [
                'translated_text' => $indexedResult['translated_text'],
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'provider' => 'native_dictionary',
                'cached' => false,
                'fallback' => false,
                'offline' => true,
                'latency_ms' => 0,
            ];
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
                'latency_ms' => 0,
            ];
        }

        // 3. Try web providers
        $webResult = $this->fetchFromWeb($cleanText, $sourceLang, $targetLang);
        if ($webResult !== null) {
            // Save to cache
            $cache[$hashKey] = $webResult;
            $this->saveCache($cache);

            return [
                'translated_text' => $webResult,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'provider' => 'web_provider',
                'cached' => false,
                'fallback' => false,
                'offline' => false,
                'latency_ms' => 0,
            ];
        }

        // 4. All failed - add to pending queue and return original
        $this->addToPending($cleanText, $sourceLang, $targetLang);

        return [
            'translated_text' => $cleanText,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'provider' => 'queue_fallback',
            'cached' => false,
            'fallback' => true,
            'offline' => true,
            'latency_ms' => 0,
        ];
    }

    private function fetchFromWeb(string $text, string $source, string $target): ?string
    {
        $url = sprintf(
            'https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s',
            urlencode($source),
            urlencode($target),
            urlencode($text)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data[0][0][0])) {
            return null;
        }

        $translated = $data[0][0][0];

        // Validate translation is not identical to source
        if (mb_strtolower(trim($translated)) === mb_strtolower(trim($text)) && $source !== $target) {
            return null;
        }

        return $translated;
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

    public function getIndexedStats(): array
    {
        return $this->indexedEngine->getStats();
    }
}
