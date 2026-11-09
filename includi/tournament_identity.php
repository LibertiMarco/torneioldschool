<?php

/** The display name may repeat; the generated page identity must stay distinct. */
function tournament_generate_slug(string $name, string $directory, callable $exists): string {
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $base = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii ?: $name), '-'));
    $base = substr($base ?: 'torneo', 0, 80);
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $slug = $base . '-' . bin2hex(random_bytes(8));
        if ($exists($slug)) continue;
        foreach ([$slug . '.php', $slug . '.html', $slug . '.htm', 'script-' . $slug . '.js'] as $file) {
            if (file_exists($directory . '/' . $file)) continue 2;
        }
        return $slug;
    }
    throw new RuntimeException('Impossibile generare il file del torneo. Riprova.');
}

/** Upgrade legacy installations without changing tournament records or other keys. */
function tournament_allow_duplicate_names(mysqli $db): void {
    $result = $db->query('SHOW INDEX FROM tornei');
    if (!$result) throw new RuntimeException('Impossibile verificare gli indici dei tornei.');
    $indexes = [];
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['Non_unique'] === 0 && $row['Key_name'] !== 'PRIMARY') {
            $indexes[$row['Key_name']][(int)$row['Seq_in_index']] = $row['Column_name'];
        }
    }
    foreach ($indexes as $name => $columns) {
        if (count($columns) !== 1 || reset($columns) !== 'nome') continue;
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        if (!$db->query("ALTER TABLE tornei DROP INDEX {$quoted}, ADD INDEX {$quoted} (nome)")) {
            throw new RuntimeException('Impossibile consentire tornei con lo stesso nome.');
        }
    }
}
