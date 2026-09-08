<?php
/**
 * Source Translator - Testes e Demonstracao
 * Testa todas as funcionalidades do SmartEngine
 */

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['pt', 'en', 'es', 'fr'], __DIR__ . '/sdk/php');

$testes = [];
$testeIndex = 0;

function runTest($engine, &$testes, &$testeIndex, $nome, $texto, $target, $source = 'pt') {
    global $testeIndex;
    $testeIndex++;
    $resultado = $engine->translate($texto, $target, $source);
    $testes[] = [
        'id' => $testeIndex,
        'nome' => $nome,
        'input' => $texto,
        'output' => $resultado['translated_text'],
        'status' => $resultado['status'],
        'status_msg' => $engine->getStatusMessage($resultado['status']),
        'provider' => $resultado['provider'],
        'warning' => $resultado['warning'],
        'latency' => $resultado['latency_ms'],
    ];
}

// Teste 1: Traducao basica
runTest($engine, $testes, $testeIndex, 'Traducao basica PT->EN', 'bom dia', 'en', 'pt');

// Teste 2: Mesmo idioma
runTest($engine, $testes, $testeIndex, 'Mesmo idioma (pt->pt)', 'ola', 'pt', 'pt');

// Teste 3: Do Not Translate
runTest($engine, $testes, $testeIndex, 'Termo DNT (PHP)', 'PHP', 'en', 'pt');

// Teste 4: Tag notranslate HTML
runTest($engine, $testes, $testeIndex, 'Tag HTML notranslate', '<span translate="no">Source Translator</span>', 'en', 'pt');

// Teste 5: Idioma nao suportado
runTest($engine, $testes, $testeIndex, 'Idioma nao suportado (de)', 'ola', 'de', 'pt');

// Teste 6: Texto vazio
runTest($engine, $testes, $testeIndex, 'Texto vazio', '', 'en', 'pt');

// Teste 7: Frase composta (Level 2)
runTest($engine, $testes, $testeIndex, 'Frase composta (segmentos)', 'bom dia pessoal', 'en', 'pt');

// Teste 8: Palavra que ja existe no destino
runTest($engine, $testes, $testeIndex, 'Palavra ja existe no destino', 'Hello', 'en', 'pt');

// Teste 9: Traducao PT->ES
runTest($engine, $testes, $testeIndex, 'Traducao PT->ES', 'bom dia', 'es', 'pt');

// Teste 10: Traducao PT->FR
runTest($engine, $testes, $testeIndex, 'Traducao PT->FR', 'obrigado', 'fr', 'pt');

// Teste 11: Texto longo com mix
runTest($engine, $testes, $testeIndex, 'Texto longo misto', 'bom dia PHP esta funcionando', 'en', 'pt');

// Teste 12: Palavra nao encontrada
runTest($engine, $testes, $testeIndex, 'Palavra ausente', 'xilogravura', 'en', 'pt');

$stats = $engine->getStats();
$missing = $engine->getMissingWords();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Source Translator - Testes</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 24px; margin-bottom: 20px; color: #38bdf8; }
        .stats-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat { background: #1e293b; padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; }
        .stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; }
        .stat-value { font-size: 20px; font-weight: bold; color: #f8fafc; }
        .stat-value.warn { color: #fbbf24; }
        .test-card { background: #1e293b; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid #334155; }
        .test-card.success { border-left-color: #22c55e; }
        .test-card.info { border-left-color: #3b82f6; }
        .test-card.warning { border-left-color: #f59e0b; }
        .test-card.error { border-left-color: #ef4444; }
        .test-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .test-name { font-weight: bold; color: #f8fafc; }
        .test-id { font-size: 12px; color: #64748b; }
        .test-content { display: grid; grid-template-columns: 1fr auto 1fr; gap: 12px; align-items: center; margin-bottom: 8px; }
        .test-box { background: #0f172a; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 13px; word-break: break-all; }
        .test-arrow { color: #64748b; font-size: 18px; }
        .test-meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .badge { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .badge-success { background: #166534; color: #86efac; }
        .badge-info { background: #1e40af; color: #93c5fd; }
        .badge-warning { background: #92400e; color: #fde68a; }
        .badge-error { background: #991b1b; color: #fca5a5; }
        .badge-provider { background: #312e81; color: #c4b5fd; }
        .test-warning { margin-top: 8px; font-size: 12px; color: #fbbf24; font-style: italic; }
        .test-latency { font-size: 11px; color: #64748b; }
        .section-title { font-size: 16px; color: #94a3b8; margin: 24px 0 12px; border-bottom: 1px solid #334155; padding-bottom: 8px; }
        .missing-list { background: #1e293b; border-radius: 8px; padding: 16px; }
        .missing-item { padding: 8px 0; border-bottom: 1px solid #334155; font-size: 13px; display: flex; justify-content: space-between; }
        .missing-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Source Translator - Suite de Testes</h1>

    <div class="stats-bar">
        <div class="stat">
            <div class="stat-label">Testes</div>
            <div class="stat-value"><?= count($testes) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Palavras no Dict</div>
            <div class="stat-value"><?= $stats['total_words'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Idiomas Ativos</div>
            <div class="stat-value"><?= count($stats['active_languages']) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">DNT</div>
            <div class="stat-value"><?= $stats['do_not_translate_count'] ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Missing</div>
            <div class="stat-value warn"><?= $stats['missing_words'] ?></div>
        </div>
    </div>

    <div class="section-title">Resultados dos Testes</div>

    <?php foreach ($testes as $t): ?>
        <?php
            $class = 'info';
            if (in_array($t['status'], [0, 106, 107, 108, 109])) $class = 'success';
            elseif ($t['status'] === 101 || $t['status'] === 102) $class = 'warning';
            elseif ($t['status'] === 105) $class = 'error';
        ?>
        <div class="test-card <?= $class ?>">
            <div class="test-header">
                <span class="test-name"><?= htmlspecialchars($t['nome']) ?></span>
                <span class="test-id">#<?= $t['id'] ?></span>
            </div>
            <div class="test-content">
                <div class="test-box"><?= htmlspecialchars($t['input'] ?: '(vazio)') ?></div>
                <div class="test-arrow">&rarr;</div>
                <div class="test-box"><?= htmlspecialchars($t['output'] ?: '(vazio)') ?></div>
            </div>
            <div class="test-meta">
                <span class="badge badge-<?= $class ?>"><?= $t['status'] ?> - <?= $t['status_msg'] ?></span>
                <?php if ($t['provider']): ?>
                    <span class="badge badge-provider"><?= $t['provider'] ?></span>
                <?php endif; ?>
                <span class="test-latency"><?= $t['latency'] ?>ms</span>
            </div>
            <?php if ($t['warning']): ?>
                <div class="test-warning">"><?= htmlspecialchars($t['warning']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($missing)): ?>
        <div class="section-title">Palavras Ausentes (missing.json)</div>
        <div class="missing-list">
            <?php foreach ($missing as $m): ?>
                <div class="missing-item">
                    <span><?= htmlspecialchars($m['text']) ?> (<?= $m['source'] ?> &rarr; <?= $m['target'] ?>)</span>
                    <span>Requisicoes: <?= $m['count'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
