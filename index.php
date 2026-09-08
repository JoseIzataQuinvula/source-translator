<?php
/**
 * Source Translator - Demonstracao em PHP Local
 * Sistema Inteligente com Avisos UX e Codigos de Status
 */

require_once __DIR__ . '/sdk/php/src/Cache.php';
require_once __DIR__ . '/sdk/php/src/Providers.php';
require_once __DIR__ . '/sdk/php/src/IndexedEngine.php';
require_once __DIR__ . '/sdk/php/src/SmartEngine.php';
require_once __DIR__ . '/sdk/php/src/NativeEngine.php';
require_once __DIR__ . '/sdk/php/src/SourceTranslator.php';

use SourceTranslator\SourceTranslator;

$translator = new SourceTranslator(['pt', 'en', 'es', 'fr']);

$resultado = null;
$textoOriginal = '';
$idiomaDestino = 'en';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto'])) {
    $textoOriginal = $_POST['texto'];
    $idiomaDestino = $_POST['idioma'];
    $resultado = $translator->translate($textoOriginal, $idiomaDestino, 'pt');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Source Translator - Demonstracao PHP</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 40px; color: #333; }
        .container { max-width: 600px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: 0 auto; }
        h1 { margin-top: 0; font-size: 22px; color: #111; display: flex; align-items: center; gap: 10px; }
        h1 svg { width: 28px; height: 28px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        textarea, select, button { width: 100%; padding: 10px; margin-top: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; }
        button { background-color: #0066cc; color: #fff; font-size: 16px; border: none; cursor: pointer; margin-top: 20px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; }
        button:hover { background-color: #0052a3; }
        button svg { width: 18px; height: 18px; fill: #fff; }
        .result-box { margin-top: 25px; padding: 15px; background: #eef6ff; border-left: 4px solid #0066cc; border-radius: 4px; }
        .native-box { margin-top: 25px; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px; }
        .fallback-box { margin-top: 25px; padding: 15px; background: #fff8e1; border-left: 4px solid #ff9800; border-radius: 4px; }
        .warning-box { margin-top: 25px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 4px; margin-top: 10px; }
        .badge svg { width: 14px; height: 14px; }
        .badge-native { background: #4caf50; color: #fff; }
        .badge-native svg { fill: #fff; }
        .badge-cache { background: #28a745; color: #fff; }
        .badge-cache svg { fill: #fff; }
        .badge-web { background: #17a2b8; color: #fff; }
        .badge-web svg { fill: #fff; }
        .badge-offline { background: #ff9800; color: #fff; }
        .badge-offline svg { fill: #fff; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-warning svg { fill: #333; }
        .stats { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-item { padding: 8px; background: #fff; border-radius: 4px; border: 1px solid #e0e0e0; }
        .stat-label { font-size: 11px; color: #666; text-transform: uppercase; }
        .stat-value { font-size: 18px; font-weight: bold; color: #333; }
        .stat-value.warning { color: #ff9800; }
        .status-code { font-family: monospace; font-size: 11px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>
        Source Translator (PHP Demo)
    </h1>
    <p><small>Sistema inteligente com avisos UX e codigos de status.</small></p>

    <form method="POST" action="index.php">
        <label for="texto">Texto original (em Portugues):</label>
        <textarea id="texto" name="texto" placeholder="Bom dia, como voce esta?"><?php echo htmlspecialchars($textoOriginal); ?></textarea>

        <label for="idioma">Traduzir para:</label>
        <select id="idioma" name="idioma">
            <option value="en" <?php if($idiomaDestino === 'en') echo 'selected'; ?>>Ingles (English)</option>
            <option value="es" <?php if($idiomaDestino === 'es') echo 'selected'; ?>>Espanhol (Espanol)</option>
            <option value="fr" <?php if($idiomaDestino === 'fr') echo 'selected'; ?>>Frances (Francais)</option>
            <option value="de" <?php if($idiomaDestino === 'de') echo 'selected'; ?>>Alemao (Deutsch)</option>
            <option value="ja" <?php if($idiomaDestino === 'ja') echo 'selected'; ?>>Japones</option>
            <option value="pt-AO" <?php if($idiomaDestino === 'pt-AO') echo 'selected'; ?>>Portugues (Angola)</option>
        </select>

        <button type="submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/>
                <path d="M12 5l7 7-7 7"/>
            </svg>
            Traduzir Agora
        </button>
    </form>

    <?php if ($resultado): ?>
        <?php if ($resultado['provider'] === 'native_dictionary'): ?>
            <div class="native-box">
                <strong>Traducao Nativa Indexada (Offline)</strong>
                <p><?php echo htmlspecialchars($resultado['translated_text']); ?></p>
                <span class="badge badge-native">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Dicionario Indexado (<?php echo $resultado['latency_ms']; ?>ms)
                </span>
                <div class="status-code">Status: <?php echo $translator->nativeStats()['total_words']; ?> palavras no dicionario</div>
            </div>
        <?php elseif ($resultado['status'] === \SourceTranslator\SmartEngine::STATUS_LANGUAGE_NOT_SUPPORTED): ?>
            <div class="warning-box">
                <strong>Aviso de Idioma</strong>
                <p><?php echo htmlspecialchars($resultado['translated_text']); ?></p>
                <span class="badge badge-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Idioma nao suportado
                </span>
                <div class="status-code">Codigo: 101 - <?php echo $resultado['warning']; ?></div>
            </div>
        <?php elseif ($resultado['status'] === \SourceTranslator\SmartEngine::STATUS_OFFLINE_FALLBACK): ?>
            <div class="fallback-box">
                <strong>Modo Offline Ativo</strong>
                <p><?php echo htmlspecialchars($resultado['translated_text']); ?></p>
                <span class="badge badge-offline">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 1l22 22"/>
                        <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                        <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                        <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
                        <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                        <line x1="12" y1="20" x2="12.01" y2="20"/>
                    </svg>
                    Offline
                </span>
                <div class="status-code">Codigo: 105 - <?php echo $resultado['warning']; ?></div>
            </div>
        <?php else: ?>
            <div class="result-box">
                <strong>Resultado da Traducao:</strong>
                <p><?php echo htmlspecialchars($resultado['translated_text']); ?></p>
                
                <?php if ($resultado['status'] === \SourceTranslator\SmartEngine::STATUS_CACHE_HIT): ?>
                    <span class="badge badge-cache">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                        Cache Local (<?php echo $resultado['latency_ms']; ?>ms)
                    </span>
                <?php else: ?>
                    <span class="badge badge-web">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                        <?php echo $resultado['provider']; ?> (<?php echo $resultado['latency_ms']; ?>ms)
                    </span>
                <?php endif; ?>
                <div class="status-code">Status: <?php echo $translator->getStatusMessage($resultado['status']); ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Dicionario Indexado</div>
                <div class="stat-value"><?php echo $translator->nativeStats()['total_words']; ?> palavras</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Idiomas Ativos</div>
                <div class="stat-value"><?php echo count($translator->getActiveLanguages()); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Cache Local</div>
                <div class="stat-value"><?php echo $translator->cacheStats()['total_entries']; ?> traducoes</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Palavras Faltantes</div>
                <div class="stat-value <?php if($translator->getMissingCount() > 0) echo 'warning'; ?>"><?php echo $translator->getMissingCount(); ?></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
