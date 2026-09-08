<?php
/**
 * Source Translator Engine v1.0.0
 * 
 * @author    Jose Izata Quinvula
 * @link      https://joseizataquinvula.pages.dev/
 * @ecosystem DUCK STACK
 * @license   MIT
 */

namespace SourceTranslator;

class Engine
{
    private string $localesDir;
    private string $remoteRepoUrl;
    private string $githubToken;
    private array $loadedMaps = [];
    private array $reverseMaps = [];
    private array $protectedTerms = [
        'jose izata quinvula',
        'duck stack',
        'source translator',
        'joseizataquinvula.pages.dev',
    ];

    public function __construct(string $localesDir = __DIR__ . '/../locales/')
    {
        $this->localesDir = rtrim($localesDir, '/') . '/';
        $this->remoteRepoUrl = 'https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/';
        $this->githubToken = getenv('GITHUB_API_TOKEN') ?: '';

        if (!is_dir($this->localesDir)) {
            mkdir($this->localesDir, 0777, true);
        }
    }

    public function loadPackage(string $lang): bool
    {
        $lang = strtolower($lang);
        if (isset($this->loadedMaps[$lang])) return true;

        $file = $this->localesDir . "{$lang}.json";

        if (!file_exists($file)) {
            $this->downloadRemotePackage($lang);
        }

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            $this->loadedMaps[$lang] = $data;

            foreach ($data as $id => $text) {
                $cleanText = mb_strtolower(trim($text), 'UTF-8');
                $this->reverseMaps[$lang][$cleanText] = $id;
            }
            return true;
        }

        return false;
    }

    public function translate(string $text, string $fromLang = 'en', string $toLang = 'pt'): string
    {
        $cleanText = trim($text);
        if (empty($cleanText)) return $cleanText;

        if ($fromLang === $toLang) return $cleanText;

        $lowerText = mb_strtolower($cleanText, 'UTF-8');

        if (in_array($lowerText, $this->protectedTerms)) {
            return $cleanText;
        }

        if (strpos($cleanText, 'translate="no"') !== false ||
            strpos($cleanText, 'class="notranslate"') !== false) {
            return $cleanText;
        }

        $this->loadPackage($fromLang);
        $this->loadPackage($toLang);

        if (isset($this->reverseMaps[$fromLang][$lowerText])) {
            $id = $this->reverseMaps[$fromLang][$lowerText];
            if (isset($this->loadedMaps[$toLang][$id])) {
                return $this->loadedMaps[$toLang][$id];
            }
        }

        $tokens = preg_split('/(\s+|[^\w\s\x{00C0}-\x{00FF}]+|[\x{00C0}-\x{00FF}]+)/u', $cleanText, -1, PREG_SPLIT_DELIM_CAPTURE);
        $translated = '';

        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower(trim($token), 'UTF-8');

            if (empty($lowerToken) || preg_match('/^\s+$/', $token)) {
                $translated .= $token;
                continue;
            }

            if (isset($this->reverseMaps[$fromLang][$lowerToken])) {
                $id = $this->reverseMaps[$fromLang][$lowerToken];
                if (isset($this->loadedMaps[$toLang][$id])) {
                    $translated .= $this->loadedMaps[$toLang][$id];
                    continue;
                }
            }

            $translated .= $token;
        }

        return $translated;
    }

    public function getPackages(): array
    {
        $files = glob($this->localesDir . '*.json');
        $pkgs = [];
        foreach ($files as $f) {
            $lang = pathinfo($f, PATHINFO_FILENAME);
            $data = json_decode(file_get_contents($f), true) ?: [];
            $pkgs[$lang] = count($data);
        }
        return $pkgs;
    }

    public function findTerm(string $text): array
    {
        $lower = strtolower(trim($text));
        $files = glob($this->localesDir . '*.json');
        $results = [];
        foreach ($files as $f) {
            $lang = pathinfo($f, PATHINFO_FILENAME);
            $data = json_decode(file_get_contents($f), true) ?: [];
            foreach ($data as $id => $val) {
                if (!is_string($val)) continue;
                if (strtolower($val) === $lower) {
                    $results[$lang][$id] = $val;
                }
            }
        }
        return $results;
    }

    private function downloadRemotePackage(string $lang): void
    {
        $url = $this->remoteRepoUrl . "{$lang}.json";

        $headers = ['User-Agent: SourceTranslator/1.0'];
        if (!empty($this->githubToken)) {
            $headers[] = 'Authorization: Bearer ' . $this->githubToken;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                file_put_contents($this->localesDir . "{$lang}.json", $content);
            }
        } else {
            file_put_contents($this->localesDir . "{$lang}.json", json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function publishPackage(string $lang, array $data, string $commitMessage = ''): bool
    {
        if (empty($this->githubToken)) {
            return false;
        }

        $url = "https://api.github.com/repos/JoseIzataQuinvula/source-translator/contents/sdk/php/locales/{$lang}.json";
        
        $content = base64_encode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $sha = $this->getFileSha($lang);
        
        $payload = json_encode([
            'message' => $commitMessage ?: "Update {$lang}.json via Source Translator CLI",
            'content' => $content,
            'sha' => $sha,
        ]);

        $headers = [
            'User-Agent: SourceTranslator-CLI',
            'Authorization: Bearer ' . $this->githubToken,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 || $httpCode === 201;
    }

    private function getFileSha(string $lang): ?string
    {
        $url = "https://api.github.com/repos/JoseIzataQuinvula/source-translator/contents/sdk/php/locales/{$lang}.json";
        
        $headers = [
            'User-Agent: SourceTranslator-CLI',
            'Authorization: Bearer ' . $this->githubToken,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['sha'] ?? null;
        }
        return null;
    }

    public function hasGithubToken(): bool
    {
        return !empty($this->githubToken);
    }

    public static function getHeader(): string
    {
        return "Source Translator v1.0.0 | Created by Jose Izata Quinvula (DUCK STACK)\n" .
               "Portfolio: https://joseizataquinvula.pages.dev/\n" .
               "-------------------------------------------------------------";
    }
}
