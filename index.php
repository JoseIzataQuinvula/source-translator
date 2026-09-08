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
        $history[] = ['out' => '  @idioma update / pkg:list / pkg:create', 'type' => 'ok'];
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
    } elseif (preg_match('/^@(\w[\w-]*)\s+update$/i', $cmd, $m)) {
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background-color: #0b0c10; 
            color: #45a29e; 
            font-family: 'Courier New', monospace; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
        }

        .header { 
            color: #66fcf1; 
            margin-bottom: 10px; 
        }

        .output-container { 
            flex: 1; 
            overflow-y: auto; 
            margin-bottom: 20px; 
            font-size: 14px; 
            line-height: 1.5; 
        }

        .line { margin: 2px 0; white-space: pre-wrap; word-break: break-all; }
        .dim { color: #555e68; }
        .ok { color: #45a29e; }
        .err { color: #e06c75; }
        .skip { color: #e5c07b; }
        .info { color: #61afef; }

        .terminal-input-wrapper { 
            position: relative; 
            width: 100%; 
            display: flex;
            align-items: center;
            background: #1f2833;
            border: 1px solid #45a29e;
            border-radius: 4px;
            padding: 10px 14px;
        }

        .terminal-input-wrapper:focus-within {
            border-color: #66fcf1;
            box-shadow: 0 0 8px rgba(102, 252, 241, 0.3);
        }

        .prompt-symbol {
            color: #66fcf1;
            margin-right: 8px;
            font-weight: bold;
            user-select: none;
        }

        .field-container {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .ghost-text {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            color: #555e68;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            pointer-events: none;
            white-space: pre;
            display: flex;
            align-items: center;
        }

        .cmd-input {
            width: 100%;
            background: transparent;
            border: none;
            color: #66fcf1;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            outline: none;
            position: relative;
            z-index: 2;
        }

        .cmd-input::placeholder { color: #555e68; }

        .footer { 
            margin-top: 8px; 
            font-size: 11px; 
            color: #444; 
        }
    </style>
</head>
<body>

<div class="header">
    <div>source-translator v1.0.0</div>
    <div style="color:#555e68;">Created by Jose Izata Quinvula (DUCK STACK)</div>
    <div style="color:#333;">----------------------------------------</div>
</div>

<div class="output-container">
    <?php foreach ($history as $item): ?>
        <?php if (isset($item['cmd'])): ?>
            <div class="line"><span class="dim">$ </span><span style="color:#ccc;"><?= htmlspecialchars($item['cmd']) ?></span></div>
        <?php else: ?>
            <div class="line <?= $item['type'] ?>"><?= htmlspecialchars($item['out']) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="terminal-input-wrapper">
    <span class="prompt-symbol">$</span>
    <div class="field-container">
        <div class="ghost-text" id="ghostText"></div>
        <form method="POST" style="width: 100%; display: flex;" id="cliForm">
            <input 
                type="text" 
                name="cmd" 
                id="cmdInput" 
                class="cmd-input" 
                placeholder="Digite um comando..." 
                autocomplete="off" 
                autofocus
            >
        </form>
    </div>
</div>

<div class="footer">
    Jose Izata Quinvula | joseizataquinvula.pages.dev | DUCK STACK
</div>

<script>
const dictionary = [
    '@pt update', '@en update', '@pt-AO update', '@es update', '@fr update',
    '@en hello @pt-AO', '@en goodbye @pt-AO', '@en good morning @pt-AO',
    '@en good night @pt-AO', '@en thank you @pt-AO', '@en how are you @pt-AO',
    '@pt-AO bom dia @en', '@pt-AO obrigado @en', '@pt-AO ate logo @en',
    '@pt bom dia @en', '@pt obrigado @en', '@pt ate mais @en',
    'pkg:list', 'pkg:create ', 'term:find ', 'help', 'stats', 'clear'
];

const input = document.getElementById('cmdInput');
const ghost = document.getElementById('ghostText');
let currentSuggestion = '';

input.addEventListener('input', function() {
    const val = this.value;

    if (!val) {
        ghost.textContent = '';
        currentSuggestion = '';
        return;
    }

    const words = val.split(' ');
    const lastWord = words[words.length - 1];

    if (!lastWord) {
        ghost.textContent = val;
        currentSuggestion = '';
        return;
    }

    const match = dictionary.find(item => {
        const lower = item.toLowerCase();
        const inputLower = val.toLowerCase();
        return lower.startsWith(inputLower) && item.length > val.length;
    });

    if (match) {
        currentSuggestion = match.slice(val.length);
        ghost.textContent = val + currentSuggestion;
    } else {
        const wordMatch = dictionary.find(item => {
            return item.toLowerCase().startsWith(lastWord.toLowerCase()) && item.length > lastWord.length;
        });

        if (wordMatch) {
            const completion = wordMatch.slice(lastWord.length);
            currentSuggestion = completion;
            ghost.textContent = val + completion;
        } else {
            ghost.textContent = '';
            currentSuggestion = '';
        }
    }
});

input.addEventListener('keydown', function(e) {
    if ((e.key === 'Tab' || e.key === 'ArrowRight') && currentSuggestion) {
        if (this.selectionStart === this.value.length) {
            e.preventDefault();
            this.value = this.value + currentSuggestion;
            ghost.textContent = '';
            currentSuggestion = '';
        }
    }
});
</script>

</body>
</html>
