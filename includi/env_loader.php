<?php
/**
 * Carica le variabili da includi/env.local.php (non versione in git)
 * e le espone via getenv()/$_ENV/$_SERVER.
 */
function tos_load_env(): array
{
    static $loaded = false;
    if ($loaded) {
        return $_ENV;
    }

    $envFile = __DIR__ . '/env.local.php';
    if (is_readable($envFile)) {
        $data = include $envFile;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === '' || $value === null) {
                    continue;
                }
                if (getenv($key) === false || getenv($key) === '') {
                    $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    putenv("$key=$stringValue");
                    $_ENV[$key] = $stringValue;
                    $_SERVER[$key] = $stringValue;
                }
            }
        }
    }

    $loaded = true;
    return $_ENV;
}

tos_load_env();
