<?php
/**
 * Source Translator - Pure Terminal
 */

session_start();

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['en', 'pt-AO', 'pt'], __DIR__ . '/sdk/php');
$history = $_SESSION['history'] ?? [];

// PRG: Process POST then redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = trim($_POST['cmd']);
    
    if ($cmd === 'clear') {
        $_SESSION['history'] = [];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $history[] = ['cmd' => $cmd];
    
    if ($cmd === 'help') {
        $history[] = ['out' => 'Comandos:', 'type' => 'info'];
        $history[] = ['out' => '  @idioma texto @idioma', 'type' => 'info'];
        $history[] = ['out' => '  stats', 'type' => 'info'];
        $history[] = ['out' => '  clear', 'type' => 'info'];
    } elseif ($cmd === 'stats') {
        $stats = $engine->getStats();
        $history[] = ['out' => "Palavras: {$stats['total_words']}", 'type' => 'info'];
        $history[] = ['out' => "Idiomas: " . implode(', ', $stats['active_languages']), 'type' => 'info'];
        $history[] = ['out' => "Missing: {$stats['missing_words']}", 'type' => 'info'];
    } elseif (preg_match('/^@(\w[\w-]*)\s+(.+?)\s+@(\w[\w-]*)$/u', $cmd, $m)) {
        $result = $engine->translate(trim($m[2], '"\''), $m[3], $m[1]);
        
        $labels = [0=>'OK', 101=>'LANG?', 103=>'CACHE', 104=>'WEB', 105=>'OFFLINE', 106=>'SAME', 107=>'DNT', 108=>'SKIP', 109=>'DICT'];
        $types = [0=>'ok', 101=>'err', 103=>'ok', 104=>'ok', 105=>'err', 106=>'skip', 107=>'skip', 108=>'skip', 109=>'ok'];
        
        $label = $labels[$result['status']] ?? '?';
        $type = $types[$result['status']] ?? 'info';
        
        $history[] = ['out' => "[{$label}] {$result['translated_text']}", 'type' => $type];
        $history[] = ['out' => "  {$result['provider']} {$result['latency_ms']}ms", 'type' => 'dim'];
    } else {
        $history[] = ['out' => 'Formato invalido. Use: @idioma texto @idioma', 'type' => 'err'];
    }
    
    $_SESSION['history'] = $history;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>source-translator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0c0c0c; color: #aaa; font-family: 'Consolas', monospace; font-size: 14px; padding: 20px; height: 100vh; display: flex; flex-direction: column; }
        #terminal { flex: 1; overflow-y: auto; padding-bottom: 10px; }
        .line { margin: 2px 0; white-space: pre-wrap; word-break: break-all; }
        .dim { color: #444; }
        .ok { color: #5cdc5c; }
        .err { color: #e06c75; }
        .skip { color: #e5c07b; }
        .info { color: #61afef; }
        .prompt-line { display: flex; align-items: center; margin-top: 10px; }
        .prompt { color: #5cdc5c; margin-right: 8px; }
        .cursor { display: inline-block; width: 8px; height: 16px; background: #5cdc5c; animation: blink 1s step-end infinite; vertical-align: middle; }
        @keyframes blink { 50% { opacity: 0; } }
        .helper-bar { margin-top: 15px; padding-top: 10px; border-top: 1px solid #222; display: flex; gap: 15px; font-size: 11px; color: #444; }
        .helper-bar span { padding: 2px 6px; background: #1a1a1a; border-radius: 3px; }
    </style>
</head>
<body>

<div id="terminal">
    <div class="line info">source-translator v1.0.0</div>
    <div class="line dim">----------------------------------------</div>
    
    <?php foreach ($history as $item): ?>
        <?php if (isset($item['cmd'])): ?>
            <div class="line"><span class="dim">$</span> <?= htmlspecialchars($item['cmd']) ?></div>
        <?php else: ?>
            <div class="line <?= $item['type'] ?>"><?= htmlspecialchars($item['out']) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<form method="POST" id="input-form" style="display:none;">
    <input type="text" name="cmd" id="cmd-input">
</form>

<div class="prompt-line">
    <span class="prompt">$</span>
    <span id="display"></span><span class="cursor"></span>
</div>

<div class="helper-bar">
    <span>@pt @pt-AO @en</span>
    <span>help</span>
    <span>stats</span>
    <span>clear</span>
</div>

<script>
const display = document.getElementById('display');
const input = document.getElementById('cmd-input');
const form = document.getElementById('input-form');
let buffer = '';

document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        input.value = buffer;
        form.submit();
    } else if (e.key === 'Backspace') {
        buffer = buffer.slice(0, -1);
        display.textContent = buffer;
    } else if (e.key.length === 1) {
        buffer += e.key;
        display.textContent = buffer;
    }
});
</script>

</body>
</html>
