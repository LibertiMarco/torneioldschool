<?php
function tournament_rules_sections_markup(array $config): ?string {
    $sections = $config['regole_sezioni'] ?? null;
    if (!is_array($sections) || !$sections || preg_match('/<[a-z][\s\S]*>/i', (string)($config['regole_html'] ?? ''))) return null;
    $html = '';
    foreach ($sections as $section) {
        if (!is_array($section)) continue;
        $title = htmlspecialchars((string)($section['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = htmlspecialchars((string)($section['body'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '<div class="regola"><h3>' . $title . '</h3><p>' . nl2br($body) . '</p></div>';
    }
    return $html ?: null;
}
