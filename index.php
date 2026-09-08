<?php
/**
 * Source Translator - Terminal Interface
 */

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SmartEngine;

$engine = new SmartEngine(['en', 'pt-AO'], __DIR__ . '/sdk/php');
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto'])) {
    $texto = $_POST['texto'];
    $target = $_POST['target'];
    $source = $_POST['source'];
    $resultado = $engine->translate($texto, $target, $source);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>source-translator ~ $</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0c0c0c; color: #cccccc; font-family: 'Consolas', 'Courier New', monospace; font-size: 14px; padding: 20px; min-height: 100vh; }
        .terminal { max-width: 700px; }
        .line { margin-bottom: 8px; white-space: pre-wrap; word-break: break-all; }
        .prompt { color: #5cdc5c; }
        .cmd { color: #f2f2f2; }
        .output { color: #cccccc; }
        .success { color: #5cdc5c; }
        .warning { color: #e5c07b; }
        .error { color: #e06c75; }
        .info { color: #61afef; }
        .dim { color: #555555; }
        .separator { color: #333333; margin: 15px 0; }
        .input-line { display: flex; align-items: center; gap: 10px; margin-top: 15px; }
        .input-line input, .input-line select { background: #1a1a1a; border: 1px solid #333; color: #f2f2f2; font-family: inherit; font-size: 14px; padding: 6px 10px; border-radius: 3px; }
        .input-line input[type="text"] { flex: 1; }
        .input-line select { width: auto; }
        .input-line button { background: #333; border: 1px solid #555; color: #f2f2f2; font-family: inherit; font-size: 14px; padding: 6px 15px; border-radius: 3px; cursor: pointer; }
        .input-line button:hover { background: #444; }
        .help { color: #555555; font-size: 12px; margin-top: 10px; }
    </style>
</head>
<body>

<div class="terminal">
    <div class="line"><span class="info">source-translator</span> <span class="dim">v1.0.0</span></div>
    <div class="line"><span class="dim">----------------------------------------</span></div>

    <?php if ($resultado): ?>
        <div class="line"><span class="prompt">$</span> <span class="cmd">traduzir "<?= htmlspecialchars($_POST['texto']) ?>" <?= $_POST['source'] ?> -> <?= $_POST['target'] ?></span></div>

        <?php if ($resultado['status'] === 0): ?>
            <div class="line"><span class="success">[OK]</span> <?= htmlspecialchars($resultado['translated_text']) ?></div>
        <?php elseif ($resultado['status'] === 106): ?>
            <div class="line"><span class="warning">[SKIP]</span> <?= htmlspecialchars($resultado['translated_text']) ?> <span class="dim">(mesmo idioma)</span></div>
        <?php elseif ($resultado['status'] === 107): ?>
            <div class="line"><span class="warning">[DNT]</span> <?= htmlspecialchars($resultado['translated_text']) ?> <span class="dim">(nao traduzivel)</span></div>
        <?php elseif ($resultado['status'] === 108): ?>
            <div class="line"><span class="warning">[SKIP]</span> <?= htmlspecialchars($resultado['translated_text']) ?> <span class="dim">(tag notranslate)</span></div>
        <?php elseif ($resultado['status'] === 101): ?>
            <div class="line"><span class="error">[ERR]</span> <?= htmlspecialchars($resultado['translated_text']) ?> <span class="dim">(idioma nao suportado)</span></div>
        <?php elseif ($resultado['status'] === 105): ?>
            <div class="line"><span class="error">[OFFLINE]</span> <?= htmlspecialchars($resultado['translated_text']) ?></div>
        <?php else: ?>
            <div class="line"><span class="info">[<?= $resultado['provider'] ?>]</span> <?= htmlspecialchars($resultado['translated_text']) ?></div>
        <?php endif; ?>

        <div class="line"><span class="dim">   status=<?= $resultado['status'] ?> provider=<?= $resultado['provider'] ?: 'none' ?> latency=<?= $resultado['latency_ms'] ?>ms</span></div>
        <div class="line separator"></div>
    <?php endif; ?>

    <div class="line"><span class="prompt">$</span> <span class="cmd">help</span></div>
    <div class="line output">Comandos: traduzir &lt;texto&gt; &lt;de&gt; &lt;para&gt;</div>
    <div class="line output">Idiomas:  en, pt-AO</div>
    <div class="line separator"></div>

    <form method="POST">
        <div class="input-line">
            <span class="prompt">$</span>
            <input type="text" name="texto" placeholder="digite o texto..." required autofocus>
            <select name="source">
                <option value="en">en</option>
                <option value="pt-AO">pt-AO</option>
            </select>
            <span class="dim">-></span>
            <select name="target">
                <option value="pt-AO">pt-AO</option>
                <option value="en">en</option>
            </select>
            <button type="submit">executar</button>
        </div>
    </form>

    <div class="help">Enter para traduzir | en=english pt-AO=portugues angola</div>
</div>

</body>
</html>
