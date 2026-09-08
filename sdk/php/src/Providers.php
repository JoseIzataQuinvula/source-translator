<?php

declare(strict_types=1);

namespace SourceTranslator;

interface TranslationProvider
{
    public function translate(string $text, string $sourceLang, string $targetLang): array;
    public function getName(): string;
}

class GoogleProvider implements TranslationProvider
{
    public function getName(): string
    {
        return 'Google';
    }

    public function translate(string $text, string $sourceLang, string $targetLang): array
    {
        $url = sprintf(
            'https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s',
            urlencode($sourceLang),
            urlencode($targetLang),
            urlencode($text)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("Google Translate HTTP error: {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data[0])) {
            throw new \RuntimeException('Invalid Google Translate response');
        }

        $translated = '';
        foreach ($data[0] as $sentence) {
            if (isset($sentence[0])) {
                $translated .= $sentence[0];
            }
        }

        if (empty($translated)) {
            throw new \RuntimeException('Empty translation from Google');
        }

        return [
            'translated_text' => $translated,
            'provider' => $this->getName(),
        ];
    }
}

class BingProvider implements TranslationProvider
{
    public function getName(): string
    {
        return 'Bing';
    }

    public function translate(string $text, string $sourceLang, string $targetLang): array
    {
        $postData = http_build_query([
            'from' => $sourceLang,
            'to' => $targetLang,
            'text' => $text,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.bing.com/ttranslatev3');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("Bing Translate HTTP error: {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data[0]['translations'][0]['text'])) {
            throw new \RuntimeException('Invalid Bing Translate response');
        }

        $translated = $data[0]['translations'][0]['text'];

        if (empty($translated)) {
            throw new \RuntimeException('Empty translation from Bing');
        }

        return [
            'translated_text' => $translated,
            'provider' => $this->getName(),
        ];
    }
}
