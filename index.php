<?php
/**
 * Source Translator v1.0.0
 * 
 * @author    Jose Izata Quinvula
 * @link      https://joseizataquinvula.pages.dev/
 * @ecosystem DUCK STACK
 * @license   MIT
 */

session_start();

define('ADMIN_KEY', getenv('ST_ADMIN_KEY') ?: '9b44a78a8dd4972f0e7e7d3be86fe274');

if (isset($_GET['key']) && $_GET['key'] === ADMIN_KEY) {
    $_SESSION['is_admin'] = true;
}

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('<html><body style="background:#0c0c0c;color:#e06c75;font-family:monospace;padding:40px;"><h1>403 - Acesso Negado</h1><p>Chave necessaria: index.php?key=SUA_CHAVE</p></body></html>');
}

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
            if (!is_string($val)) continue;
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
        $history[] = ['out' => 'Source Translator v1.0.0', 'type' => 'info'];
        $history[] = ['out' => 'Created by Jose Izata Quinvula (DUCK STACK)', 'type' => 'dim'];
        $history[] = ['out' => '', 'type' => 'dim'];
        $history[] = ['out' => 'Traducao:', 'type' => 'info'];
        $history[] = ['out' => '  @idioma texto @idioma', 'type' => 'ok'];
        $history[] = ['out' => 'Pacotes:', 'type' => 'info'];
        $history[] = ['out' => '  @idioma download / pkg:list / pkg:create', 'type' => 'ok'];
        $history[] = ['out' => 'Termos:', 'type' => 'info'];
        $history[] = ['out' => '  term:find <texto>', 'type' => 'ok'];
        $history[] = ['out' => 'Sistema:', 'type' => 'info'];
        $history[] = ['out' => '  stats / clear', 'type' => 'ok'];
    } elseif ($cmd === 'stats') {
        $stats = $engine->getStats();
        $pkgs = getPackages($localesDir);
        $history[] = ['out' => "Pacotes: " . count($pkgs), 'type' => 'ok'];
        $history[] = ['out' => "Palavras: {$stats['total_words']}", 'type' => 'ok'];
        $history[] = ['out' => "Missing: {$stats['missing_words']}", 'type' => 'skip'];
        $history[] = ['out' => "DNT: {$stats['do_not_translate_count']}", 'type' => 'info'];
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
    } elseif (preg_match('/^@(\w[\w-]*)\s+download$/i', $cmd, $m)) {
        $lang = strtolower($m[1]);
        $file = $localesDir . "{$lang}.json";
        $cdnUrl = "https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/{$lang}.json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cdnUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                file_put_contents($file, $content);
                $history[] = ['out' => "[OK] Pacote {$lang}.json atualizado! (" . count($data) . " termos)", 'type' => 'ok'];
            } else {
                $history[] = ['out' => "Formato invalido no CDN.", 'type' => 'err'];
            }
        } else {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $history[] = ['out' => "Pacote remoto nao encontrado. Criado {$lang}.json vazio.", 'type' => 'skip'];
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
        body { background: #0c0c0c; color: #aaa; font-family: 'Consolas', 'Courier New', monospace; font-size: 14px; padding: 20px; height: 100vh; display: flex; flex-direction: column; }
        #terminal { flex: 1; overflow-y: auto; padding-bottom: 10px; }
        .line { margin: 2px 0; white-space: pre-wrap; word-break: break-all; }
        .dim { color: #444; }
        .ok { color: #5cdc5c; }
        .err { color: #e06c75; }
        .skip { color: #e5c07b; }
        .info { color: #61afef; }
        .prompt-line { display: flex; align-items: center; margin-top: 10px; }
        .prompt { color: #5cdc5c; margin-right: 8px; }
        .input-area { position: relative; flex: 1; min-height: 20px; }
        .ghost { position: absolute; left: 0; top: 0; color: #444; pointer-events: none; white-space: pre; font-family: inherit; font-size: inherit; z-index: 1; }
        #display { position: relative; z-index: 2; color: #f2f2f2; }
        .cursor { display: inline-block; width: 8px; height: 16px; background: #5cdc5c; animation: blink 1s step-end infinite; vertical-align: middle; }
        @keyframes blink { 50% { opacity: 0; } }
        .helper-bar { margin-top: 15px; padding-top: 10px; border-top: 1px solid #222; display: flex; gap: 15px; font-size: 11px; color: #444; }
        .helper-bar span { padding: 2px 6px; background: #1a1a1a; border-radius: 3px; }
        .author { margin-top: 10px; font-size: 10px; color: #333; }
    </style>
</head>
<body>

<div id="terminal">
    <div class="line info">source-translator v1.0.0</div>
    <div class="line dim">Created by Jose Izata Quinvula (DUCK STACK)</div>
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
    <div class="input-area">
        <span class="ghost" id="ghost"></span>
        <span id="display"></span><span class="cursor"></span>
    </div>
</div>

<div class="helper-bar">
    <span>@pt @pt-AO @en</span>
    <span>@idioma download</span>
    <span>pkg:list</span>
    <span>term:find</span>
    <span>help</span>
    <span>clear</span>
</div>

<div class="author">Jose Izata Quinvula | joseizataquinvula.pages.dev | DUCK STACK</div>

<script>
const display = document.getElementById('display');
const ghost = document.getElementById('ghost');
const input = document.getElementById('cmd-input');
const form = document.getElementById('input-form');
let buffer = '';

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
    '@pt download',
    '@en download',
    '@pt-AO download',
    '@es download',
    '@fr download',
    'pkg:list',
    'pkg:create ',
    'term:find ',
    'help',
    'stats',
    'clear',
];

function getSuggestion(text) {
    if (!text || text.length < 1) return null;
    
    const match = commands.find(c => 
        c.toLowerCase().indexOf(text.toLowerCase()) === 0 && c.length > text.length
    );
    
    return match ? match.slice(text.length) : null;
}

function updateGhost() {
    const suggestion = getSuggestion(buffer);
    if (suggestion) {
        ghost.textContent = buffer + suggestion;
    } else {
        ghost.textContent = '';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        input.value = buffer;
        form.submit();
    } else if (e.key === 'Backspace') {
        buffer = buffer.slice(0, -1);
        display.textContent = buffer;
        updateGhost();
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const suggestion = getSuggestion(buffer);
        if (suggestion) {
            buffer += suggestion;
            display.textContent = buffer;
            ghost.textContent = '';
        }
    } else if (e.key === 'ArrowRight') {
        const suggestion = getSuggestion(buffer);
        if (suggestion && display.textContent.length === buffer.length) {
            buffer += suggestion;
            display.textContent = buffer;
            ghost.textContent = '';
        }
    } else if (e.key.length === 1) {
        buffer += e.key;
        display.textContent = buffer;
        updateGhost();
    }
});
</script>

</body>
</html>
