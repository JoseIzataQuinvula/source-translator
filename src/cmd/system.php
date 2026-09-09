<?php
function handleSystem($cmd, &$history, $userRole) {
    if ($cmd === 'clear') {
        $_SESSION['history'] = [];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($cmd === 'help') {
        $history[] = ['out' => 'Source Translator v1.0.0', 'type' => 'info'];
        $history[] = ['out' => 'Created by Jose Izata Quinvula (DUCK STACK)', 'type' => 'dim'];
        $history[] = ['out' => '', 'type' => 'dim'];
        if ($userRole === 'root') {
            $history[] = ['out' => '[ROOT] Acesso total', 'type' => 'ok'];
            $history[] = ['out' => '  @idioma create / @idioma edit', 'type' => 'ok'];
            $history[] = ['out' => '  pkg:create / term:add', 'type' => 'ok'];
        } elseif ($userRole === 'user') {
            $history[] = ['out' => '[USER] Acesso limitado', 'type' => 'skip'];
            $history[] = ['out' => '  @idioma download / @idioma update', 'type' => 'ok'];
            $history[] = ['out' => '  pkg:list / term:find', 'type' => 'ok'];
        }
        $history[] = ['out' => 'Admin:', 'type' => 'info'];
        $history[] = ['out' => '  @login usuario senha / logout', 'type' => 'skip'];
        $history[] = ['out' => 'Pacotes:', 'type' => 'info'];
        $history[] = ['out' => '  @pacotes list / @pacotes locais list', 'type' => 'ok'];
        $history[] = ['out' => '  @idioma download / @idioma update', 'type' => 'ok'];
        $history[] = ['out' => 'Traducao:', 'type' => 'info'];
        $history[] = ['out' => '  @idioma texto @idioma', 'type' => 'ok'];
        $history[] = ['out' => 'Sistema:', 'type' => 'info'];
        $history[] = ['out' => '  @login usuario senha / logout / stats / clear', 'type' => 'ok'];
        return true;
    }

    return false;
}
