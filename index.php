<?php
/**
 * Source Translator - Pure Terminal + Package Manager
 */

session_start();

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$localesDir = __DIR__ . '/sdk/php/locales/';
$engine = new SmartEngine(['en', 'pt-AO', 'pt'], __DIR__ . '/sdk/php');
$history = $_SESSION['history'] ?? [];

function getPackages($dir) {
    $files = glob($dir . '*.json');
    $pkgs = [];
    foreach ($files as $f) {
        $lang = pathinfo($f, PATHINFO_FILENAME);
        $data = json_decode(file_get_contents($f), true) ?: [];
        $pkgs[$lang] = count($data);
    }
    return $pkgs;
}

function findTerm($dir, $text) {
    $lower = strtolower(trim($text));
    $files = glob($dir . '*.json');
    $results = [];
    foreach ($files as $f) {
        $lang = pathinfo($f, PATHINFO_FILENAME);
        $data = json_decode(file_get_contents($f), true) ?: [];
        foreach ($data as $id => $val) {
            if (strtolower($val) === $lower) {
                $results[] = "  [{$lang}] #{$id} = {$val}";
            }
        }
    }
    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = trim($_POST['cmd']);
    
    if ($cmd === 'clear') {
        $_SESSION['history'] = [];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $history[] = ['cmd' => $cmd];
    
    if ($cmd === 'help') {
        $history[] = ['out' => 'Traducao:', 'type' => 'info'];
        $history[] = ['out' => '  @idioma texto @idioma', 'type' => 'info'];
        $history[] = ['out' => 'Pacotes:', 'type' => 'info'];
        $history[] = ['out' => '  pkg:list', 'type' => 'info'];
        $history[] = ['out' => '  pkg:create <lang>', 'type' => 'info'];
        $history[] = ['out' => 'Termos:', 'type' => 'info'];
        $history[] = ['out' => '  term:find <texto>', 'type' => 'info'];
        $history[] = ['out' => 'Sistema:', 'type' => 'info'];
        $history[] = ['out' => '  stats / clear', 'type' => 'info'];
    } elseif ($cmd === 'stats') {
        $stats = $engine->getStats();
        $pkgs = getPackages($localesDir);
        $history[] = ['out' => "Pacotes: " . count($pkgs), 'type' => 'ok'];
        $history[] = ['out' => "Palavras: {$stats['total_words']}", 'type' => 'ok'];
        $history[] = ['out' => "Missing: {$stats['missing_words']}", 'type' => 'skip'];
    } elseif ($cmd === 'pkg:list') {
        $pkgs = getPackages($localesDir);
        if (empty($pkgs)) {
            $history[] = ['out' => 'Nenhum pacote encontrado.', 'type' => 'skip'];
        } else {
            $history[] = ['out' => 'Pacotes:', 'type' => 'info'];
            foreach ($pkgs as $lang => $count) {
                $history[] = ['out' => "  {$lang}.json  ({$count} termos)", 'type' => 'ok'];
            }
        }
    } elseif (preg_match('/^pkg:create\s+(\w[\w-]*)$/', $cmd, $m)) {
        $lang = strtolower($m[1]);
        $file = $localesDir . "{$lang}.json";
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $history[] = ['out' => "Pacote {$lang}.json criado!", 'type' => 'ok'];
        } else {
            $history[] = ['out' => "Pacote {$lang}.json ja existe.", 'type' => 'skip'];
        }
    } elseif (preg_match('/^term:find\s+(.+)$/', $cmd, $m)) {
        $text = trim($m[1], '"\'');
        $results = findTerm($localesDir, $text);
        if (empty($results)) {
            $history[] = ['out' => "Nenhum resultado para: {$text}", 'type' => 'skip'];
        } else {
            $history[] = ['out' => "Resultados:", 'type' => 'info'];
            foreach ($results as $r) {
                $history[] = ['out' => $r, 'type' => 'ok'];
            }
        }
    } elseif (preg_match('/^@(\w[\w-]*)\s+(.+?)\s+@(\w[\w-]*)$/u', $cmd, $m)) {
        $result = $engine->translate(trim($m[2], '"\''), $m[3], $m[1]);
        
        $labels = [0=>'OK', 101=>'LANG?', 103=>'CACHE', 104=>'WEB', 105=>'OFFLINE', 106=>'SAME', 107=>'DNT', 108=>'SKIP', 109=>'DICT'];
        $types = [0=>'ok', 101=>'err', 103=>'ok', 104=>'ok', 105=>'err', 106=>'skip', 107=>'skip', 108=>'skip', 109=>'ok'];
        
        $label = $labels[$result['status']] ?? '?';
        $type = $types[$result['status']] ?? 'info';
        
        $history[] = ['out' => "[{$label}] {$result['translated_text']}", 'type' => $type];
        $history[] = ['out' => "  {$result['provider']} {$result['latency_ms']}ms", 'type' => 'dim'];
    } else {
        $history[] = ['out' => 'Comando invalido. Digite help', 'type' => 'err'];
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
    <link rel="icon" type="image/png" href="duck-favicon.png">
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
        #suggestions { position: absolute; bottom: 70px; left: 20px; background: #1a1a1a; border: 1px solid #333; border-radius: 4px; padding: 4px 0; display: none; min-width: 350px; z-index: 100; }
        .suggestion { padding: 4px 12px; cursor: pointer; font-size: 13px; color: #aaa; }
        .suggestion:hover, .suggestion.active { background: #333; color: #5cdc5c; }
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

<div id="suggestions"></div>

<form method="POST" id="input-form" style="display:none;">
    <input type="text" name="cmd" id="cmd-input">
</form>

<div class="prompt-line">
    <span class="prompt">$</span>
    <span id="display"></span><span class="cursor"></span>
</div>

<div class="helper-bar">
    <span>@pt @pt-AO @en</span>
    <span>pkg:list</span>
    <span>term:find</span>
    <span>help</span>
    <span>clear</span>
</div>

<script>
const display = document.getElementById('display');
const input = document.getElementById('cmd-input');
const form = document.getElementById('input-form');
const suggestions = document.getElementById('suggestions');
let buffer = '';
let selectedIdx = -1;

const commands = [
    '@en hello @pt-AO',
    '@en goodbye @pt-AO',
    '@en good morning @pt-AO',
    '@en good night @pt-AO',
    '@en thank you @pt-AO',
    '@en how are you @pt-AO',
    '@pt-AO bom dia @en',
    '@pt-AO obrigado @en',
    '@pt-AO ate logo @en',
    '@pt bom dia @en',
    '@pt obrigado @en',
    '@pt ate mais @en',
    'pkg:list',
    'pkg:create ',
    'term:find ',
    'help',
    'stats',
    'clear',
];

function showSuggestions(filter) {
    if (!filter) {
        suggestions.style.display = 'none';
        return;
    }
    
    const matches = commands.filter(c => 
        c.toLowerCase().startsWith(filter.toLowerCase())
    );
    
    if (matches.length === 0) {
        suggestions.style.display = 'none';
        return;
    }
    
    suggestions.innerHTML = matches.map(c => 
        '<div class="suggestion" data-cmd="' + c + '">' + c + '</div>'
    ).join('');
    
    suggestions.style.display = 'block';
    selectedIdx = -1;
    
    document.querySelectorAll('.suggestion').forEach(el => {
        el.onclick = () => {
            buffer = el.dataset.cmd;
            display.textContent = buffer;
            suggestions.style.display = 'none';
        };
    });
}

function updateSelection() {
    document.querySelectorAll('.suggestion').forEach((el, i) => {
        el.classList.toggle('active', i === selectedIdx);
    });
}

document.addEventListener('keydown', (e) => {
    const items = document.querySelectorAll('.suggestion');
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIdx = Math.min(selectedIdx + 1, items.length - 1);
        updateSelection();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIdx = Math.max(selectedIdx - 1, -1);
        updateSelection();
    } else if (e.key === 'Tab' && items.length > 0) {
        e.preventDefault();
        if (selectedIdx >= 0) {
            buffer = items[selectedIdx].dataset.cmd;
        } else {
            buffer = items[0].dataset.cmd;
        }
        display.textContent = buffer;
        suggestions.style.display = 'none';
    } else if (e.key === 'Enter') {
        if (selectedIdx >= 0 && items.length > 0) {
            buffer = items[selectedIdx].dataset.cmd;
        }
        input.value = buffer;
        form.submit();
    } else if (e.key === 'Escape') {
        suggestions.style.display = 'none';
    } else if (e.key === 'Backspace') {
        buffer = buffer.slice(0, -1);
        display.textContent = buffer;
        showSuggestions(buffer);
    } else if (e.key.length === 1) {
        buffer += e.key;
        display.textContent = buffer;
        showSuggestions(buffer);
    }
});
</script>

</body>
</html>
