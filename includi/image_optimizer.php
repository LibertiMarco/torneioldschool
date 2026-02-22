<?php

if (!function_exists('optimize_image_file')) {
    /**
     * Ottimizza un file immagine in-place: ridimensiona se supera le dimensioni massime,
     * applica compressione e mantiene l'estensione originale. Usa GD.
     *
     * @param string $path percorso assoluto del file da ottimizzare
     * @param array  $options [
     *   maxWidth?  int,
     *   maxHeight? int,
     *   quality?   int (0-100, usato per JPEG/WEBP),
     *   maxBytes?  int  (taglio "soft": se il file rimane oltre questa soglia l'operazione viene ignorata)
     * ]
     */
    function optimize_image_file(string $path, array $options = []): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $info = @getimagesize($path);
        if (!$info || empty($info['mime'])) {
            return false;
        }
        $mime = strtolower($info['mime']);
        $supportsAlpha = in_array($mime, ['image/png', 'image/webp'], true);
        $loaders = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        $savers = [
            'image/jpeg' => static function ($resource, $dest, int $quality): bool {
                return imagejpeg($resource, $dest, max(10, min(100, $quality)));
            },
            'image/png' => static function ($resource, $dest, int $quality): bool {
                // quality per PNG è 0-9 (0 meno compressione). convertiamo 0-100 in 0-9
                $level = (int)round((100 - max(0, min(100, $quality))) / 11.1111);
                return imagepng($resource, $dest, max(0, min(9, $level)));
            },
            'image/webp' => static function ($resource, $dest, int $quality): bool {
                if (!function_exists('imagewebp')) {
                    return false;
                }
                return imagewebp($resource, $dest, max(10, min(100, $quality)));
            },
        ];

        if (!isset($loaders[$mime], $savers[$mime])) {
            // Evita GIF/SVG o formati non gestiti per non rompere i file
            return false;
        }
        $loaderFn = $loaders[$mime];
        if (!function_exists($loaderFn)) {
            return false;
        }

        $origSize = @filesize($path) ?: 0;
        $maxBytes = (int)($options['maxBytes'] ?? (12 * 1024 * 1024)); // 12MB soft limit
        $maxW = (int)($options['maxWidth'] ?? 1920);
        $maxH = (int)($options['maxHeight'] ?? 1920);
        $quality = (int)($options['quality'] ?? 82);

        // Evita fatal error di memoria quando l'immagine Ã¨ troppo grande per GD.
        $shorthandToBytes = static function ($value): int {
            if ($value === null || $value === '' || $value === false) {
                return -1;
            }
            $value = trim((string)$value);
            if ($value === '-1') {
                return -1; // memory_limit illimitato
            }
            $unit = strtolower(substr($value, -1));
            $number = (float)$value;
            $factor = [
                'k' => 1024,
                'm' => 1024 * 1024,
                'g' => 1024 * 1024 * 1024,
            ][$unit] ?? 1;
            return (int)($number * $factor);
        };

        $channels = (int)($info['channels'] ?? ($supportsAlpha ? 4 : 3));
        $bitsPerChannel = max(1, (int)($info['bits'] ?? 8));
        // Moltiplicatore 1.7 per overhead GD + 2MB di margine
        $estimatedBytes = (int)ceil($info[0] * $info[1] * $channels * ($bitsPerChannel / 8) * 1.7 + 2_000_000);

        $limitBytes = $shorthandToBytes(ini_get('memory_limit'));
        $usageBytes = memory_get_usage(true);
        $headroom = 16 * 1024 * 1024; // buffer addizionale per operazioni successive

        if ($limitBytes > 0 && ($usageBytes + $estimatedBytes + $headroom) > $limitBytes) {
            // Prova ad alzare il memory_limit senza superare 512M (configurabile tramite options)
            $maxRaisable = $shorthandToBytes($options['memoryLimit'] ?? '512M');
            $desired = min($maxRaisable, $usageBytes + $estimatedBytes + $headroom);
            if ($desired > $limitBytes) {
                @ini_set('memory_limit', (int)ceil($desired / (1024 * 1024)) . 'M');
                $limitBytes = $shorthandToBytes(ini_get('memory_limit'));
            }
        }

        if ($limitBytes > 0 && ($usageBytes + $estimatedBytes + $headroom) > $limitBytes) {
            // L'immagine Ã¨ troppo grande per essere caricata con l'attuale memory_limit: evita fatal.
            return false;
        }

        $src = @$loaderFn($path);
        if (!$src) {
            return false;
        }

        // Preserve alpha for PNG/WEBP even when we do not resize
        if ($supportsAlpha) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        // Auto-orient JPEG
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int)($exif['Orientation'] ?? 0);
            if (in_array($orientation, [3, 6, 8], true)) {
                if ($orientation === 3) {
                    $src = imagerotate($src, 180, 0);
                } elseif ($orientation === 6) {
                    $src = imagerotate($src, -90, 0);
                } elseif ($orientation === 8) {
                    $src = imagerotate($src, 90, 0);
                }
            }
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $targetW = $width;
        $targetH = $height;

        if (($maxW > 0 && $width > $maxW) || ($maxH > 0 && $height > $maxH)) {
            $ratio = min($maxW > 0 ? $maxW / $width : 1, $maxH > 0 ? $maxH / $height : 1);
            $targetW = max(1, (int)floor($width * $ratio));
            $targetH = max(1, (int)floor($height * $ratio));
        }

        $dst = $src;
        if ($targetW !== $width || $targetH !== $height) {
            $dst = imagecreatetruecolor($targetW, $targetH);
            if ($supportsAlpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        }

        if ($supportsAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        $tmp = $path . '.tmpopt';
        $saveFn = $savers[$mime];
        $saved = $saveFn($dst, $tmp, $quality);

        if ($dst !== $src) {
            imagedestroy($dst);
        }
        imagedestroy($src);

        if (!$saved || !is_file($tmp)) {
            @unlink($tmp);
            return false;
        }

        $optimizedSize = @filesize($tmp) ?: $origSize;

        // Se l'ottimizzazione non riduce o supera il limite soft, tieni l'originale
        if ($optimizedSize >= $origSize && $origSize > 0) {
            @unlink($tmp);
            return false;
        }
        if ($maxBytes > 0 && $optimizedSize > $maxBytes) {
            @unlink($tmp);
            return false;
        }

        // Sovrascrive in modo atomico
        $replaced = @rename($tmp, $path);
        if (!$replaced) {
            @unlink($tmp);
        }
        return $replaced;
    }
}
