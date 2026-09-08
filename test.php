<?php
/**
 * Source Translator - Teste via CLI
 * Execute: php test.php
 */

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['pt', 'en', 'es', 'fr'], __DIR__ . '/sdk/php');

$passed = 0;
$failed = 0;

function test($engine, $name, $input, $target, $source, $expectedStatus) {
    global $passed, $failed;

    $result = $engine->translate($input, $target, $source);

    $status = $result['status'] === $expectedStatus ? 'PASS' : 'FAIL';

    if ($status === 'PASS') {
        $passed++;
        echo "\033[32m[PASS]\033[0m {$name}\n";
    } else {
        $failed++;
        echo "\033[31m[FAIL]\033[0m {$name}\n";
        echo "  Expected: {$expectedStatus}\n";
        echo "  Got: {$result['status']}\n";
    }

    echo "  Input:    {$input}\n";
    echo "  Output:   {$result['translated_text']}\n";
    echo "  Status:   {$result['status']} - " . $engine->getStatusMessage($result['status']) . "\n";

    if ($result['warning']) {
        echo "  Warning:  {$result['warning']}\n";
    }

    echo "\n";
}

echo "=== Source Translator - Testes CLI ===\n\n";

// Testes
test($engine, 'Traducao basica PT->EN', 'bom dia', 'en', 'pt', 109);
test($engine, 'Mesmo idioma', 'ola', 'pt', 'pt', 106);
test($engine, 'DNT - PHP', 'PHP', 'en', 'pt', 107);
test($engine, 'DNT - GitHub', 'GitHub', 'en', 'pt', 107);
test($engine, 'Tag HTML notranslate', '<span translate="no">Source Translator</span>', 'en', 'pt', 108);
test($engine, 'Idioma nao suportado', 'ola', 'de', 'pt', 101);
test($engine, 'Texto vazio', '', 'en', 'pt', 0);
test($engine, 'Palavra ausente', 'xilogravura', 'en', 'pt', 103);
test($engine, 'PT->ES', 'obrigado', 'es', 'pt', 109);
test($engine, 'PT->FR', 'obrigado', 'fr', 'pt', 109);

// Frase composta
$result = $engine->translate('bom dia pessoal', 'en', 'pt');
if ($result['status'] === 109) {
    $passed++;
    echo "\033[32m[PASS]\033[0m Frase composta (segmentos)\n";
} else {
    $failed++;
    echo "\033[31m[FAIL]\033[0m Frase composta (segmentos)\n";
}
echo "  Input:    bom dia pessoal\n";
echo "  Output:   {$result['translated_text']}\n";
echo "  Status:   {$result['status']}\n\n";

// Stats
$stats = $engine->getStats();
echo "=== Estatisticas ===\n";
echo "Palavras no dicionario: {$stats['total_words']}\n";
echo "Idiomas ativos: " . count($stats['active_languages']) . "\n";
echo "Termos DNT: {$stats['do_not_translate_count']}\n";
echo "Palavras ausentes: {$stats['missing_words']}\n\n";

// Resultado final
echo "=== Resultado ===\n";
echo "Passaram: {$passed}\n";
echo "Falharam: {$failed}\n";
$total = $passed + $failed;
$porcentagem = $total > 0 ? round(($passed / $total) * 100) : 0;

if ($failed === 0) {
    echo "\033[32mTodos os testes passaram! ({$porcentagem}%)\033[0m\n";
} else {
    echo "\033[31mAlguns testes falharam. ({$porcentagem}%)\033[0m\n";
}
