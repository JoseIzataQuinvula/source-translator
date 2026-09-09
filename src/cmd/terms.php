<?php
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

function handleTerm($cmd, &$history, $engine, $localesDir) {
    if ($cmd === 'stats') {
        $stats = $engine->getStats();
        $pkgs = getPackages($localesDir);
        $history[] = ['out' => "Pacotes: " . count($pkgs), 'type' => 'ok'];
        $history[] = ['out' => "Palavras: {$stats['total_words']}", 'type' => 'ok'];
        $history[] = ['out' => "Missing: {$stats['missing_words']}", 'type' => 'skip'];
        $history[] = ['out' => "DNT: {$stats['do_not_translate_count']}", 'type' => 'info'];
        return true;
    }

    if (preg_match('/^term:find\s+(.+)$/', $cmd, $m)) {
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
        return true;
    }

    return false;
}
