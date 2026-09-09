<?php
function handlePackages($cmd, &$history, $localesDir) {
    if ($cmd === '@pacotes list') {
        $history[] = ['out' => '=== PACOTES DISPONIVEIS (GitHub) ===', 'type' => 'info'];
        $history[] = ['out' => '', 'type' => 'skip'];

        $langs = ['en', 'pt-AO', 'pt-BR', 'es', 'fr'];
        $total = 0;
        $found = 0;
        $packageData = [];

        foreach ($langs as $lang) {
            $url = "https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/{$lang}.json";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($content)) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $remoteCount = count($data);
                    $total += $remoteCount;
                    $found++;

                    $localFile = $localesDir . "{$lang}.json";
                    file_put_contents($localFile, $content);
                    $localDate = date('d/m/Y');

                    $packageData[$lang] = [
                        'count' => $remoteCount,
                        'date' => $localDate,
                    ];
                }
            }
        }

        foreach ($packageData as $lang => $info) {
            $translations = [];
            foreach ($packageData as $otherLang => $otherInfo) {
                if ($otherLang !== $lang) {
                    $translations[] = "@{$otherLang} ({$otherInfo['count']} traducoes {$otherInfo['date']})";
                }
            }
            $transStr = !empty($translations) ? " traducoes: " . implode('; ', $translations) : "";
            $history[] = ['out' => "  @{$lang}  ({$info['count']} palavras {$info['date']}){$transStr}", 'type' => 'ok'];
        }

        $history[] = ['out' => '', 'type' => 'skip'];
        $history[] = ['out' => "Total: {$found} idiomas, {$total} palavras", 'type' => 'info'];
        return true;
    }

    if ($cmd === '@pacotes locais list') {
        $files = glob($localesDir . '*.json');
        $skipLangs = ['en_pt-AO', 'pt-AO_en', 'pt-BR_en', 'en_pt-BR'];
        $total = 0;
        $localData = [];

        foreach ($files as $f) {
            $lang = pathinfo($f, PATHINFO_FILENAME);
            if (in_array($lang, $skipLangs)) continue;
            $data = json_decode(file_get_contents($f), true) ?: [];
            $count = count($data);
            $total += $count;
            $modified = date('d/m/Y', filemtime($f));
            $localData[$lang] = [
                'count' => $count,
                'date' => $modified,
            ];
        }

        if (empty($localData)) {
            $history[] = ['out' => 'Nenhum pacote local encontrado.', 'type' => 'skip'];
        } else {
            $history[] = ['out' => '=== PACOTES LOCAIS ===', 'type' => 'info'];

            foreach ($localData as $lang => $info) {
                $translations = [];
                foreach ($localData as $otherLang => $otherInfo) {
                    if ($otherLang !== $lang) {
                        $translations[] = "@{$otherLang} ({$otherInfo['count']} traducoes {$otherInfo['date']})";
                    }
                }
                $transStr = !empty($translations) ? " traducoes: " . implode('; ', $translations) : "";
                $history[] = ['out' => "  @{$lang}  ({$info['count']} palavras {$info['date']}){$transStr}", 'type' => 'ok'];
            }

            $history[] = ['out' => "", 'type' => 'skip'];
            $history[] = ['out' => "Total: " . count($localData) . " idiomas, {$total} palavras", 'type' => 'info'];
        }
        return true;
    }

    if (preg_match('/^@([\w-]+)\s+download$/i', $cmd, $m)) {
        $lang = $m[1];
        $file = $localesDir . "{$lang}.json";
        $exists = file_exists($file);

        if ($exists) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            $count = count($data);
            $date = date('d/m/Y H:i', filemtime($file));
            $history[] = ['out' => "[OK] Pacote {$lang}.json ja existe. ({$count} termos, {$date})", 'type' => 'skip'];
        } else {
            $cdnUrl = "https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/{$lang}.json";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cdnUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($content)) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    file_put_contents($file, $content);
                    $history[] = ['out' => "[OK] Pacote {$lang}.json baixado! (" . count($data) . " termos)", 'type' => 'ok'];
                } else {
                    $history[] = ['out' => "Formato invalido no CDN.", 'type' => 'err'];
                }
            } else {
                file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $history[] = ['out' => "Pacote remoto nao encontrado. Criado {$lang}.json vazio.", 'type' => 'skip'];
            }
        }
        return true;
    }

    if (preg_match('/^@([\w-]+)\s+update$/i', $cmd, $m)) {
        $lang = $m[1];
        $file = $localesDir . "{$lang}.json";
        $cdnUrl = "https://raw.githubusercontent.com/JoseIzataQuinvula/source-translator/main/sdk/php/locales/{$lang}.json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cdnUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                $existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
                $newCount = count($data);
                $oldCount = is_array($existing) ? count($existing) : 0;
                file_put_contents($file, $content);
                $history[] = ['out' => "[OK] Pacote {$lang}.json atualizado! ({$oldCount} -> {$newCount} termos)", 'type' => 'ok'];
            } else {
                $history[] = ['out' => "Formato invalido no CDN.", 'type' => 'err'];
            }
        } else {
            $history[] = ['out' => "Nao foi possivel atualizar. Verifique a conexao.", 'type' => 'err'];
        }
        return true;
    }

    if (preg_match('/^@(\w[\w-]*)\s+create$/i', $cmd, $m)) {
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'root') {
            $history[] = ['out' => "ERRO: Apenas ROOT pode criar pacotes.", 'type' => 'err'];
        } else {
            $lang = $m[1];
            $file = $localesDir . "{$lang}.json";
            if (!file_exists($file)) {
                $pkg = [
                    '_metadata' => [
                        'package' => $lang,
                        'version' => '1.0.0',
                        'author' => 'Jose Izata Quinvula',
                        'project' => 'DUCK STACK',
                    ],
                ];
                file_put_contents($file, json_encode($pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $history[] = ['out' => "[OK] Pacote {$lang}.json criado com metadata!", 'type' => 'ok'];
            } else {
                $history[] = ['out' => "Pacote {$lang}.json ja existe.", 'type' => 'skip'];
            }
        }
        return true;
    }

    if (preg_match('/^@(\w[\w-]*)\s+edit\s+(\d+)\s+(\S+)\s+(.+)$/i', $cmd, $m)) {
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'root') {
            $history[] = ['out' => "ERRO: Apenas ROOT pode editar pacotes.", 'type' => 'err'];
        } else {
            $lang = $m[1];
            $id = $m[2];
            $key = $m[3];
            $value = trim($m[4], '"\'');

            $file = $localesDir . "{$lang}.json";
            if (!file_exists($file)) {
                $history[] = ['out' => "Pacote {$lang}.json nao existe. Use @{$lang} download primeiro.", 'type' => 'err'];
            } else {
                $data = json_decode(file_get_contents($file), true) ?: [];
                $data[$id] = $value;
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $history[] = ['out' => "[OK] {$lang}.json: #{$id} = {$value}", 'type' => 'ok'];
            }
        }
        return true;
    }

    if (preg_match('/^pkg:create\s+(\w[\w-]*)$/', $cmd, $m)) {
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'root') {
            $history[] = ['out' => "ERRO: Apenas ROOT pode criar pacotes.", 'type' => 'err'];
        } else {
            $lang = $m[1];
            $file = $localesDir . "{$lang}.json";
            if (!file_exists($file)) {
                file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $history[] = ['out' => "Pacote {$lang}.json criado!", 'type' => 'ok'];
            } else {
                $history[] = ['out' => "Pacote {$lang}.json ja existe.", 'type' => 'skip'];
            }
        }
        return true;
    }

    return false;
}
