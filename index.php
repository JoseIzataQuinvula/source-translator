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
require_once __DIR__ . '/src/terminal.php';

use SourceTranslator\SmartEngine;

$localesDir = __DIR__ . '/sdk/php/locales/';
$engine = new SmartEngine(['en', 'pt-AO', 'pt-BR'], __DIR__ . '/sdk/php');
$history = $_SESSION['history'] ?? [];

$userRole = $_SESSION['user_role'] ?? null;
$currentUser = $_SESSION['current_user'] ?? null;

$rootUser = getenv('ST_ROOT_USER') ?: 'quinvula';
$rootPassHash = getenv('ST_ROOT_PASS_HASH') ?: password_hash('2d00ck4q', PASSWORD_BCRYPT);

$normalUser = getenv('ST_USER_USER') ?: 'user';
$normalPassHash = getenv('ST_USER_PASS_HASH') ?: password_hash('user123', PASSWORD_BCRYPT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = trim($_POST['cmd']);
    processCommand($cmd, $engine, $localesDir, $userRole, $currentUser, $rootUser, $rootPassHash, $normalUser, $normalPassHash);
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
    <link rel="stylesheet" href="assets/css/terminal.css">
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
    <span>@pt-AO</span>
    <span>@pt-BR</span>
    <span>@en</span>
    <span>@idioma download</span>
    <span>@idioma update</span>
    <span>help</span>
    <span>term:find</span>
    <span>help</span>
    <span>clear</span>
</div>

<div class="author">Jose Izata Quinvula | joseizataquinvula.pages.dev | DUCK STACK</div>

<script src="assets/js/terminal.js"></script>

</body>
</html>
