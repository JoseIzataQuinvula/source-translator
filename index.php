<?php
/**
 * Source Translator - Terminal
 * 
 * Comando: @idioma texto @idioma
 * Exemplo: @pt "bom dia" @en
 */

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['en', 'pt-AO', 'pt'], __DIR__ . '/sdk/php');
$output = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = trim($_POST['cmd']);
    
    if ($cmd === 'help') {
        $output[] = ['type' => 'info', 'text' => 'Comandos: @idioma texto @idioma'];
        $output[] = ['type' => 'info', 'text' => 'Exemplo:  @pt "bom dia" @en'];
        $output[] = ['type' => 'info', 'text' => 'Idiomas:  pt, pt-AO, en'];
        $output[] = ['type' => 'info', 'text' => 'Stats:    stats'];
    } elseif ($cmd === 'stats') {
        $stats = $engine->getStats();
        $output[] = ['type' => 'info', 'text' => 'Palavras: ' . $stats['total_words']];
        $output[] = ['type' => 'info', 'text' => 'Idiomas:  ' . implode(', ', $stats['active_languages'])];
        $output[] = ['type' => 'info', 'text' => 'Missing:  ' . $stats['missing_words']];
    } elseif (preg_match('/^@(\w[\w-]*)\s+(.+?)\s+@(\w[\w-]*)$/u', $cmd, $m)) {
        $source = $m[1];
        $text = trim($m[2], '"\'');
        $target = $m[3];
        
        $result = $engine->translate($text, $target, $source);
        
        $statusMap = [
            0 => ['OK', 'success'],
            101 => ['LANG?', 'error'],
            103 => ['CACHE', 'info'],
            104 => ['WEB', 'info'],
            105 => ['OFFLINE', 'error'],
            106 => ['SAME', 'warning'],
            107 => ['DNT', 'warning'],
            108 => ['SKIP', 'warning'],
            109 => ['DICT', 'success'],
        ];
        
        [$label, $type] = $statusMap[$result['status']] ?? ['?', 'info'];
        
        $output[] = ['type' => $type, 'text' => "[{$label}] {$result['translated_text']}"];
        $output[] = ['type' => 'dim', 'text' => "status={$result['status']} provider={$result['provider']} {$result['latency_ms']}ms"];
    } else {
        $output[] = ['type' => 'error', 'text' => 'Formato invalido. Use: @idioma texto @idioma'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>source-translator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0c0c0c; color: #cccccc; font-family: 'Consolas', 'Courier New', monospace; font-size: 14px; padding: 15px; }
        .header { color: #5cdc5c; margin-bottom: 15px; }
        .dim { color: #555; }
        .success { color: #5cdc5c; }
        .warning { color: #e5c07b; }
        .error { color: #e06c75; }
        .info { color: #61afef; }
        .output-line { margin: 4px 0; }
        .input-form { margin-top: 15px; display: flex; gap: 8px; }
        .input-form input { flex: 1; background: #1a1a1a; border: 1px solid #333; color: #f2f2f2; font-family: inherit; font-size: 14px; padding: 8px 10px; border-radius: 3px; }
        .input-form input:focus { outline: none; border-color: #5cdc5c; }
        .input-form button { background: #333; border: 1px solid #555; color: #f2f2f2; font-family: inherit; font-size: 14px; padding: 8px 15px; border-radius: 3px; cursor: pointer; }
        .input-form button:hover { background: #444; }
        .help { color: #555; font-size: 12px; margin-top: 10px; }
    </style>
</head>
<body>

<div class="header">source-translator v1.0.0</div>
<div class="dim">----------------------------------------</div>

<?php foreach ($output as $line): ?>
    <div class="output-line <?= $line['type'] ?>"><?= htmlspecialchars($line['text']) ?></div>
<?php endforeach; ?>

<form class="input-form" method="POST">
    <input type="text" name="cmd" placeholder="@pt texto @en" autofocus>
    <button type="submit">executar</button>
</form>

<div class="help">@idioma texto @idioma | @pt @pt-AO @en | help | stats</div>

</body>
</html>
