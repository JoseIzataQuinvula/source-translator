<?php
/**
 * Source Translator - Demonstracao em PHP Local
 * Modelo: Cache Local + Motor Web Gratuito
 */

function traduzirTexto($texto, $idiomaDestino = 'en', $idiomaOrigem = 'pt') {
    if (empty(trim($texto))) {
        return ['texto' => '', 'cached' => false];
    }

    $cacheFile = __DIR__ . '/cache_traducoes.json';
    $cache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    $chaveHash = md5($texto . '_' . $idiomaOrigem . '_' . $idiomaDestino);

    if (isset($cache[$chaveHash])) {
        return [
            'texto' => $cache[$chaveHash],
            'cached' => true
        ];
    }

    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=" 
           . urlencode($idiomaOrigem) 
           . "&tl=" . urlencode($idiomaDestino) 
           . "&dt=t&q=" . urlencode($texto);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

    $resposta = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($resposta, true);
    $textoTraduzido = isset($dados[0][0][0]) ? $dados[0][0][0] : $texto;

    $cache[$chaveHash] = $textoTraduzido;
    file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return [
        'texto' => $textoTraduzido,
        'cached' => false
    ];
}

$resultado = null;
$textoOriginal = '';
$idiomaDestino = 'en';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto'])) {
    $textoOriginal = $_POST['texto'];
    $idiomaDestino = $_POST['idioma'];
    $resultado = traduzirTexto($textoOriginal, $idiomaDestino);
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
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 4px; margin-top: 10px; }
        .badge svg { width: 14px; height: 14px; }
        .badge-cache { background: #28a745; color: #fff; }
        .badge-cache svg { fill: #fff; }
        .badge-web { background: #ffc107; color: #000; }
        .badge-web svg { fill: #000; }
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
    <p><small>Teste de traducao local com armazenamento em cache JSON.</small></p>

    <form method="POST" action="index.php">
        <label for="texto">Texto original (em Portugues):</label>
        <textarea id="texto" name="texto" placeholder="Digite o texto do seu site aqui..."><?php echo htmlspecialchars($textoOriginal); ?></textarea>

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
        <div class="result-box">
            <strong>Resultado da Traducao:</strong>
            <p><?php echo htmlspecialchars($resultado['texto']); ?></p>
            
            <?php if ($resultado['cached']): ?>
                <span class="badge badge-cache">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    Veio do Cache Local (Instantaneo)
                </span>
            <?php else: ?>
                <span class="badge badge-web">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Traduzido da Web & Salvo no Cache
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
