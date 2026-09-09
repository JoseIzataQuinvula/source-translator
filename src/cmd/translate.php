<?php
function handleTranslate($cmd, &$history, $engine) {
    if (preg_match('/^@(\w[\w-]*)\s+(.+?)\s+@(\w[\w-]*)$/u', $cmd, $m)) {
        $result = $engine->translate(trim($m[2], '"\''), $m[3], $m[1]);

        $labels = [0=>'OK', 101=>'LANG?', 103=>'CACHE', 104=>'WEB', 105=>'OFFLINE', 106=>'SAME', 107=>'DNT', 108=>'SKIP', 109=>'DICT'];
        $types = [0=>'ok', 101=>'err', 103=>'ok', 104=>'ok', 105=>'err', 106=>'skip', 107=>'skip', 108=>'skip', 109=>'ok'];

        $label = $labels[$result['status']] ?? '?';
        $type = $types[$result['status']] ?? 'info';

        $history[] = ['out' => "[{$label}] {$result['translated_text']}", 'type' => $type];
        $history[] = ['out' => "  {$result['provider']} {$result['latency_ms']}ms", 'type' => 'dim'];
        return true;
    }

    return false;
}
