<?php
// Basi e posizioni condivise dagli operatori, separate per torneo e formato.
function graphics_template_layout(array $layout, string $type): array
{
    $keys = $type === 'ft'
        ? ['photo', 'homeLogo', 'awayLogo', 'score', 'home', 'away', 'round']
        : ['photo', 'homeLogo', 'awayLogo', 'names', 'team', 'details'];
    $clean = [];
    foreach ($keys as $key) {
        $item = $layout[$key] ?? null;
        if (!is_array($item)) {
            throw new InvalidArgumentException('Posizioni degli elementi incomplete.');
        }
        $row = ['visible' => !empty($item['visible'])];
        foreach (['x' => [0, 1080], 'y' => [0, 1350], 'w' => [1, 1080], 'h' => [1, 1350], 'font' => [12, 240]] as $field => $limits) {
            $value = $item[$field] ?? null;
            if (!is_numeric($value) || !is_finite((float)$value) || $value < $limits[0] || $value > $limits[1]) {
                throw new InvalidArgumentException('Posizione o dimensione non valida.');
            }
            $row[$field] = (int)$value;
        }
        if (!is_string($item['color'] ?? null) || !preg_match('/^#[0-9a-f]{6}$/i', $item['color'])) {
            throw new InvalidArgumentException('Colore non valido.');
        }
        $row['color'] = $item['color'];
        $clean[$key] = $row;
    }
    return $clean;
}

function graphics_template_image(string $bytes): string
{
    if (strlen($bytes) > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('La base deve pesare al massimo 8 MB.');
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
        throw new InvalidArgumentException('Carica una base PNG, JPG o WebP valida.');
    }
    if ($info[0] * $info[1] > 16000000) {
        throw new InvalidArgumentException('La base deve avere al massimo 16 megapixel.');
    }
    return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
}

function graphics_template_read(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    return $data ?: null;
}

function graphics_template_write(string $path, ?array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossibile creare la cartella delle basi.');
    }
    $temp = tempnam($dir, 'base-');
    if ($temp === false) {
        throw new RuntimeException('Impossibile salvare la base.');
    }
    try {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $path)) {
            throw new RuntimeException('Impossibile salvare la base.');
        }
    } finally {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
}
