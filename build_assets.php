<?php
/**
 * Script di utilità per minificare e concatenare CSS/JS pubblici.
 * Esegue:
 *  - style.css -> style.min.css
 *  - includi/header-interactions.js -> includi/header-interactions.min.js
 *  - includi/privacy-consent.js -> includi/privacy-consent.min.js
 *  - bundle concatenato -> includi/app.min.js
 */

$root = __DIR__;

function minify_css(string $css): string
{
    $css = preg_replace('~/\*(?!\!)(.|\n)*?\*/~', '', $css); // rimuove commenti non importanti
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

function minify_js(string $js): string
{
    // Rimuove commenti e spazi superflui in modo conservativo
    $js = preg_replace('~/\*[^!][\s\S]*?\*/~', '', $js);       // blocchi /* ... */
    $js = preg_replace('/^\s*\/\/.*$/m', '', $js);             // linee che iniziano con //
    $js = str_replace(["\r\n", "\r"], "\n", $js);
    $js = preg_replace('/[ \t]+/', ' ', $js);
    $js = preg_replace('/\s*([{};,:=()\[\]+\-<>])\s*/', '$1', $js);
    $js = preg_replace('/\n+/', "\n", $js);
    return trim($js);
}

function write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $content);
}

// CSS
$cssSource = $root . '/style.css';
$cssTarget = $root . '/style.min.css';
$css = (string) (file_exists($cssSource) ? file_get_contents($cssSource) : '');
write_file($cssTarget, minify_css($css));

// JS files to minify & bundle
$jsFiles = [
    ['src' => $root . '/includi/header-interactions.js', 'dest' => $root . '/includi/header-interactions.min.js'],
    ['src' => $root . '/includi/privacy-consent.js', 'dest' => $root . '/includi/privacy-consent.min.js'],
];

$bundle = '';
foreach ($jsFiles as $file) {
    if (!file_exists($file['src'])) {
        continue;
    }
    $code = (string) file_get_contents($file['src']);
    $min = minify_js($code);
    write_file($file['dest'], $min);
    $bundle .= $min . "\n";
}

if ($bundle !== '') {
    write_file($root . '/includi/app.min.js', trim($bundle));
}

echo "Assets minificati generati.\n";
