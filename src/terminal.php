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

function processCommand($cmd, $engine, $localesDir, $userRole, $currentUser, $rootUser, $rootPassHash, $normalUser, $normalPassHash) {
    $history = $_SESSION['history'] ?? [];
    $history[] = ['cmd' => $cmd];

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
