<?php
/**
 * Source Translator - Terminal Router
 *
 * @author    Jose Izata Quinvula
 * @link      https://joseizataquinvula.pages.dev/
 * @ecosystem DUCK STACK
 * @license   MIT
 */

require_once __DIR__ . '/cmd/system.php';
require_once __DIR__ . '/cmd/auth.php';
require_once __DIR__ . '/cmd/terms.php';
require_once __DIR__ . '/cmd/packages.php';
require_once __DIR__ . '/cmd/translate.php';

function addRecentCommand($cmd) {
    $recent = $_SESSION['recent_commands'] ?? [];
    $recent = array_filter($recent, fn($c) => $c !== $cmd);
    array_unshift($recent, $cmd);
    $recent = array_slice($recent, 0, 10);
    $_SESSION['recent_commands'] = array_values($recent);
}

function getRecentCommands() {
    return $_SESSION['recent_commands'] ?? [];
}

function processCommand($cmd, $engine, $localesDir, $userRole, $currentUser, $rootUser, $rootPassHash, $normalUser, $normalPassHash) {
    $history = $_SESSION['history'] ?? [];
    $history[] = ['cmd' => $cmd];

    if ($cmd === 'clear') {
        $_SESSION['history'] = [];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    addRecentCommand($cmd);

    if (handleSystem($cmd, $history, $userRole)) {
        $_SESSION['history'] = $history;
        return;
    }

    if (handleAuth($cmd, $history, $rootUser, $rootPassHash, $normalUser, $normalPassHash)) {
        $_SESSION['history'] = $history;
        return;
    }

    if (handleTerm($cmd, $history, $engine, $localesDir)) {
        $_SESSION['history'] = $history;
        return;
    }

    if (handlePackages($cmd, $history, $localesDir)) {
        $_SESSION['history'] = $history;
        return;
    }

    if (handleTranslate($cmd, $history, $engine)) {
        $_SESSION['history'] = $history;
        return;
    }

    $history[] = ['out' => 'Comando invalido. Digite help', 'type' => 'err'];
    $_SESSION['history'] = $history;
}
