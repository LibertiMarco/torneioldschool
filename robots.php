<?php
declare(strict_types=1);

require_once __DIR__ . '/includi/seo.php';

header('Content-Type: text/plain; charset=utf-8');

$baseUrl = rtrim(seo_base_url(), '/');
$adminDisallow = [
    '/admin_dashboard.php',
    '/api/gestione_blog.php',
    '/api/gestione_blog_new.php',
    '/api/gestione_giocatori.php',
    '/api/gestione_partite.php',
    '/api/gestione_squadre.php',
    '/api/gestione_tornei.php',
    '/api/gestione_utenti.php',
];

echo "User-agent: *\n";
echo "Allow: /\n\n";

foreach ($adminDisallow as $path) {
    echo "Disallow: {$path}\n";
}

echo "\nSitemap: {$baseUrl}/sitemap.xml\n";
