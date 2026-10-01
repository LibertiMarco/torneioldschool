<?php
class GraphicsTemplateStorageException extends RuntimeException {}
// Basi e posizioni condivise dagli operatori, separate per torneo e formato.
function graphics_template_layout(array $layout, string $type): array
{
    $legacyScore = $type === 'ft' && isset($layout['score']) && !isset($layout['homeScore']) && !isset($layout['awayScore']);
    $keys = $type === 'ft'
        ? array_merge(['photo', 'scoreOverlay', 'homeLogo', 'awayLogo'], $legacyScore ? ['score'] : ['homeScore', 'awayScore'], ['home', 'away', 'round'])
        : ['photo', 'homeLogo', 'awayLogo', 'names', 'team', 'details'];
    $clean = [];
    foreach ($keys as $key) {
        $item = $layout[$key] ?? null;
        if ($key === 'scoreOverlay' && !is_array($item)) {
            $item = ['x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1350, 'font' => 40, 'visible' => true, 'color' => '#ffffff'];
        }
        if (!is_array($item)) {
            throw new InvalidArgumentException('Posizioni degli elementi incomplete.');
        }
        $row = ['visible' => !empty($item['visible'])];
        $ranges = ['x' => [0, 1080], 'y' => [0, 1350], 'w' => [1, 1080], 'h' => [1, 1350], 'font' => [12, 240]];
        if ($key === 'scoreOverlay') {
            $ranges['x'] = [-1080, 1080];
            $ranges['y'] = [-1350, 1350];
        }
        foreach ($ranges as $field => $limits) {
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
    if ($legacyScore) {
        $old = $clean['score'];
        $half = (int)floor($old['w'] / 2);
        $clean['homeScore'] = array_replace($old, ['w' => max(1, $half)]);
        $clean['awayScore'] = array_replace($old, ['x' => min(1080, $old['x'] + $half), 'w' => max(1, $old['w'] - $half)]);
    }
    unset($clean['score']);
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
    if (!@is_file($path)) {
        return null;
    }
    $json = @file_get_contents($path);
    if ($json === false) {
        throw new GraphicsTemplateStorageException('Impossibile leggere la base salvata.');
    }
    $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    return $data ?: null;
}

function graphics_template_write(string $path, ?array $data): void
{
    $dir = dirname($path);
    if (!@is_dir($dir) && !@mkdir($dir, 0775, true) && !@is_dir($dir)) {
        throw new GraphicsTemplateStorageException('Impossibile creare la cartella delle basi.');
    }
    if (!@is_writable($dir)) {
        throw new GraphicsTemplateStorageException('La cartella delle basi non è scrivibile.');
    }
    $temp = @tempnam($dir, 'base-');
    if ($temp === false) {
        throw new GraphicsTemplateStorageException('Impossibile salvare la base.');
    }
    try {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (@file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !@rename($temp, $path)) {
            throw new GraphicsTemplateStorageException('Impossibile salvare la base.');
        }
        clearstatcache(true, $path);
        if (@file_get_contents($path) !== $json) {
            throw new GraphicsTemplateStorageException('Verifica del salvataggio della base non riuscita.');
        }
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
}

// The local directory is protected by the site's /cache access-denial rule.
// Once used, it remains authoritative, including null records for removed bases.
function graphics_template_load(string $runtimePath, string $localPath): ?array
{
    return graphics_template_read(@is_file($localPath) ? $localPath : $runtimePath);
}

function graphics_template_store(string $runtimePath, string $localPath, ?array $data): void
{
    if (!@is_file($localPath)) {
        try {
            graphics_template_write($runtimePath, $data);
            return;
        } catch (GraphicsTemplateStorageException $error) {
            error_log('graphics_templates runtime storage: ' . $error->getMessage());
        }
    }
    try {
        graphics_template_write($localPath, $data);
    } catch (GraphicsTemplateStorageException $error) {
        throw new GraphicsTemplateStorageException('Salvataggio non riuscito: verifica spazio disponibile e permessi della cartella delle basi sul server.');
    }
}
