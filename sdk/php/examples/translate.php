<?php
/**
 * Source Translator - PHP Example
 * 
 * Usage:
 *   php examples/translate.php "Hello, World!" en pt-BR
 *   php examples/translate.php "Olá mundo" pt-AO en
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SourceTranslator\SourceTranslator;

// Get arguments from command line
$text = $argv[1] ?? 'Hello, World!';
$sourceLang = $argv[2] ?? 'en';
$targetLang = $argv[3] ?? 'pt-BR';

echo "Initializing Source Translator...\n";
$translator = new SourceTranslator();

echo "Translating: \"{$text}\"\n";
echo "From: {$sourceLang} -> To: {$targetLang}\n\n";

try {
    $result = $translator->translate($text, $sourceLang, $targetLang);

    echo "Result:\n";
    echo "  Translation: \"{$result['translated_text']}\"\n";
    echo "  Provider: {$result['provider']}\n";
    echo "  Cached: " . ($result['cached'] ? 'true' : 'false') . "\n";
    echo "  Latency: {$result['latency_ms']}ms\n\n";

    echo "Cache Stats:\n";
    $stats = $translator->cacheStats();
    echo "  Total entries: {$stats['total_entries']}\n";
    echo "  Providers used: " . implode(', ', $stats['providers_used']) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
