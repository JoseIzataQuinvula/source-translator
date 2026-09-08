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

    // Status codes
    const STATUS_SUCCESS = 0;
    const STATUS_LANGUAGE_NOT_SUPPORTED = 101;
    const STATUS_TRANSLATION_MISSING = 102;
    const STATUS_CACHE_HIT = 103;
    const STATUS_WEB_TRANSLATED = 104;
    const STATUS_OFFLINE_FALLBACK = 105;
    const STATUS_SAME_LANGUAGE = 106;
    const STATUS_DO_NOT_TRANSLATE = 107;
    const STATUS_NO_TRANSLATE_TAG = 108;
    const STATUS_SENTENCE_TRANSLATED = 109;

    private array $doNotTranslate = [
        'source translator',
        'github',
        'php',
        'json',
        'sqlite',
        'kwanza',
        'javascript',
        'typescript',
        'python',
        'docker',
        'redis',
        'postgresql',
        'mysql',
        'linux',
        'windows',
        'macos',
        'html',
        'css',
        'api',
        'rest',
        'graphql',
        'npm',
        'composer',
        'cargo',
    ];

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

        if (!file_exists($filePath)) {
            $this->downloadLanguagePackage($lang);
        }

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);

            if (is_array($data)) {
                $this->maps[$lang] = $data;

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
            return $this->buildResponse($text, $sourceLang, $targetLang, '', self::STATUS_SUCCESS, null, 0);
        }

        // RULE 0: HTML notranslate tags
        if ($this->hasNoTranslateTag($cleanText)) {
            return $this->buildResponse(
                $cleanText,
                $sourceLang,
                $targetLang,
                '',
                self::STATUS_NO_TRANSLATE_TAG,
                'Elemento marcado com translate="no" ou .notranslate.',
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        $lowerText = mb_strtolower($cleanText, 'UTF-8');
        $sourceLang = strtolower($sourceLang);
        $targetLang = strtolower($targetLang);

        // RULE A: Same language - no translation needed
        if ($sourceLang === $targetLang) {
            return $this->buildResponse(
                $cleanText,
                $sourceLang,
                $targetLang,
                '',
                self::STATUS_SAME_LANGUAGE,
                'Idiomas de origem e destino sao identicos.',
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        // RULE B: Do Not Translate list
        if (in_array($lowerText, $this->doNotTranslate)) {
            return $this->buildResponse(
                $cleanText,
                $sourceLang,
                $targetLang,
                '',
                self::STATUS_DO_NOT_TRANSLATE,
                "O termo '{$cleanText}' esta na lista de nao traduziveis.",
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        // 1. Check if target language is supported
        if (!in_array($targetLang, $this->activeLanguages)) {
            return $this->buildResponse(
                $cleanText,
                $sourceLang,
                $targetLang,
                '',
                self::STATUS_LANGUAGE_NOT_SUPPORTED,
                "O idioma '{$targetLang}' nao esta ativado no sistema. Exibindo texto original.",
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        // 2. Load language packages
        $loadedSource = $this->loadLanguage($sourceLang);
        $loadedTarget = $this->loadLanguage($targetLang);

        // RULE C: If word already exists in target language dictionary, skip
        if ($loadedTarget && isset($this->reverseMaps[$targetLang][$lowerText])) {
            return $this->buildResponse(
                $cleanText,
                $sourceLang,
                $targetLang,
                '',
                self::STATUS_SUCCESS,
                'Termo ja existe no idioma de destino.',
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        // 3. LEVEL 1: Try indexed dictionary - full sentence match (instant, offline)
        if ($loadedSource && $loadedTarget) {
            if (isset($this->reverseMaps[$sourceLang][$lowerText])) {
                $id = $this->reverseMaps[$sourceLang][$lowerText];

                if (isset($this->maps[$targetLang][$id])) {
                    return $this->buildResponse(
                        $this->maps[$targetLang][$id],
                        $sourceLang,
                        $targetLang,
                        'native_dictionary',
                        self::STATUS_SENTENCE_TRANSLATED,
                        null,
                        (int) ((microtime(true) - $start) * 1000)
                    );
                }
            }
        }

        // 4. LEVEL 2: Smart sentence segmentation - word-by-word translation
        if ($loadedSource && $loadedTarget) {
            $translated = $this->translateBySegments($cleanText, $sourceLang, $targetLang);

            if ($translated !== null) {
                return $this->buildResponse(
                    $translated,
                    $sourceLang,
                    $targetLang,
                    'native_dictionary_segments',
                    self::STATUS_SENTENCE_TRANSLATED,
                    'Traducao por segmentos do dicionario.',
                    (int) ((microtime(true) - $start) * 1000)
                );
            }
        }

        // 5. Check local cache
        $cache = $this->loadCache();
        $hashKey = md5($cleanText . '_' . $sourceLang . '_' . $targetLang);

        if (isset($cache[$hashKey])) {
            return $this->buildResponse(
                $cache[$hashKey],
                $sourceLang,
                $targetLang,
                'local_cache',
                self::STATUS_CACHE_HIT,
                null,
                (int) ((microtime(true) - $start) * 1000)
            );
        }

        // 6. Try web providers
        $providers = [
            new GoogleProvider(),
            new BingProvider(),
            new MyMemoryProvider(),
        ];

        foreach ($providers as $provider) {
            try {
                $result = $provider->translate($cleanText, $sourceLang, $targetLang);

                $normalizedResult = mb_strtolower(trim($result['translated_text']));
                $normalizedText = mb_strtolower(trim($cleanText));

                if ($normalizedResult !== $normalizedText || $sourceLang === $targetLang) {
                    $cache[$hashKey] = $result['translated_text'];
                    $this->saveCache($cache);
                }

                return $this->buildResponse(
                    $result['translated_text'],
                    $sourceLang,
                    $targetLang,
                    $result['provider'],
                    self::STATUS_WEB_TRANSLATED,
                    null,
                    (int) ((microtime(true) - $start) * 1000)
                );
            } catch (\Exception $e) {
                error_log("Provider {$provider->getName()} failed: " . $e->getMessage());
                continue;
            }
        }

        // 7. All providers failed - record missing and add to pending
        $this->recordMissing($cleanText, $sourceLang, $targetLang);
        $this->addToPending($cleanText, $sourceLang, $targetLang);

        return $this->buildResponse(
            $cleanText,
            $sourceLang,
            $targetLang,
            'offline',
            self::STATUS_OFFLINE_FALLBACK,
            "A traducao de '{$cleanText}' para '{$targetLang}' nao esta disponivel no momento. Texto original mantido.",
            (int) ((microtime(true) - $start) * 1000)
        );
    }

    private function hasNoTranslateTag(string $text): bool
    {
        $patterns = [
            '/translate\s*=\s*["\']no["\']/',
            '/data-notranslate/',
            '/class\s*=\s*["\'][^"\']*notranslate[^"\']*["\']/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function translateBySegments(string $text, string $sourceLang, string $targetLang): ?string
    {
        $pattern = '/(\s+|[^\w\s\x{00C0}-\x{00FF}]+|[\x{00C0}-\x{00FF}]+)/u';
        $tokens = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false || empty($tokens)) {
            return null;
        }

        $translated = '';
        $hasAnyTranslation = false;

        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower(trim($token), 'UTF-8');

            if (empty($lowerToken) || preg_match('/^\s+$/', $token)) {
                $translated .= $token;
                continue;
            }

            if (isset($this->reverseMaps[$sourceLang][$lowerToken])) {
                $id = $this->reverseMaps[$sourceLang][$lowerToken];

                if (isset($this->maps[$targetLang][$id])) {
                    $translated .= $this->maps[$targetLang][$id];
                    $hasAnyTranslation = true;
                    continue;
                }
            }

            $translated .= $token;
        }

        return $hasAnyTranslation ? $translated : null;
    }

    private function buildResponse(
        string $translatedText,
        string $sourceLang,
        string $targetLang,
        string $provider,
        int $statusCode,
        ?string $warning,
        int $latencyMs
    ): array {
        return [
            'translated_text' => $translatedText,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'provider' => $provider,
            'status' => $statusCode,
            'warning' => $warning,
            'latency_ms' => $latencyMs,
        ];
    }

    public function getStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            self::STATUS_SUCCESS => 'Traducao concluida com sucesso',
            self::STATUS_LANGUAGE_NOT_SUPPORTED => 'Idioma nao suportado',
            self::STATUS_TRANSLATION_MISSING => 'Traducao nao encontrada no dicionario',
            self::STATUS_CACHE_HIT => 'Recuperado do cache local',
            self::STATUS_WEB_TRANSLATED => 'Traduzido via web',
            self::STATUS_OFFLINE_FALLBACK => 'Modo offline ativo',
            self::STATUS_SAME_LANGUAGE => 'Identicos',
            self::STATUS_DO_NOT_TRANSLATE => 'Termo nao traduzivel',
            self::STATUS_NO_TRANSLATE_TAG => 'Tag HTML notranslate',
            self::STATUS_SENTENCE_TRANSLATED => 'Traducao por segmentos',
            default => 'Status desconhecido',
        };
    }

    public function hasWarning(int $statusCode): bool
    {
        return in_array($statusCode, [
            self::STATUS_LANGUAGE_NOT_SUPPORTED,
            self::STATUS_TRANSLATION_MISSING,
            self::STATUS_OFFLINE_FALLBACK,
        ]);
    }

    public function addToDoNotTranslate(string $term): void
    {
        $lowerTerm = mb_strtolower(trim($term), 'UTF-8');
        if (!in_array($lowerTerm, $this->doNotTranslate)) {
            $this->doNotTranslate[] = $lowerTerm;
        }
    }

    public function removeFromDoNotTranslate(string $term): void
    {
        $lowerTerm = mb_strtolower(trim($term), 'UTF-8');
        $this->doNotTranslate = array_filter($this->doNotTranslate, fn($t) => $t !== $lowerTerm);
    }

    public function getDoNotTranslateList(): array
    {
        return $this->doNotTranslate;
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
            'do_not_translate_count' => count($this->doNotTranslate),
        ];
    }
}
