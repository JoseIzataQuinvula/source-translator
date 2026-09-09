<?php
function handleAuth($cmd, &$history, $rootUser, $rootPassHash, $normalUser, $normalPassHash) {
    if (preg_match('/^@login\s+(\S+)\s+(\S+)$/i', $cmd, $m)) {
        $user = $m[1];
        $pass = $m[2];

        if ($user === $rootUser && password_verify($pass, $rootPassHash)) {
            $_SESSION['user_role'] = 'root';
            $_SESSION['current_user'] = $rootUser;
            $history[] = ['out' => "[ROOT] Bem-vindo, {$rootUser}! Acesso total liberado.", 'type' => 'ok'];
        } elseif ($user === $normalUser && password_verify($pass, $normalPassHash)) {
            $_SESSION['user_role'] = 'user';
            $_SESSION['current_user'] = $normalUser;
            $history[] = ['out' => "[USER] Bem-vindo, {$normalUser}! Acesso limitado.", 'type' => 'skip'];
        } else {
            $history[] = ['out' => "ERRO: Credenciais invalidas.", 'type' => 'err'];
        }
        return true;
    }

    if ($cmd === 'logout') {
        $_SESSION['user_role'] = null;
        $_SESSION['current_user'] = null;
        $history[] = ['out' => "Logout realizado.", 'type' => 'skip'];
        return true;
    }

    if ($cmd === '@user') {
        if (isset($_SESSION['current_user']) && $_SESSION['current_user']) {
            $history[] = ['out' => "{$_SESSION['current_user']}$", 'type' => 'ok'];
        } else {
            $history[] = ['out' => "Nao esta logado.", 'type' => 'skip'];
        }
        return true;
    }

    return false;
}
