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

        $src = @$loaderFn($path);
        if (!$src) {
            return false;
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
            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
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
