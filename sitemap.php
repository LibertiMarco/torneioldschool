<?php
declare(strict_types=1);

require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/content_sections.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = rtrim(seo_base_url(), '/');
$siteSection = content_current_section();
$isEsportSite = $siteSection === 'esport';
$urls = [];

function sitemap_file_lastmod(string $path): ?string
{
    return file_exists($path) ? date('c', (int)filemtime($path)) : null;
}

function sitemap_add(array &$urls, string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.7'): void
{
    $urls[] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
}

sitemap_add($urls, $baseUrl . '/', sitemap_file_lastmod(__DIR__ . ($isEsportSite ? '/esport.php' : '/index.php')), 'daily', '1.0');
sitemap_add(
    $urls,
    $baseUrl . ($isEsportSite ? '/tornei-esport.php' : '/tornei.php'),
    sitemap_file_lastmod(__DIR__ . ($isEsportSite ? '/tornei-esport.php' : '/tornei.php')),
    'daily',
    '0.8'
);
sitemap_add($urls, $baseUrl . '/blog.php', sitemap_file_lastmod(__DIR__ . '/blog.php'), 'daily', '0.8');
if (!$isEsportSite) {
    sitemap_add($urls, $baseUrl . '/classifica_giocatori.php', sitemap_file_lastmod(__DIR__ . '/classifica_giocatori.php'), 'daily', '0.7');
}
sitemap_add($urls, $baseUrl . '/chisiamo.php', sitemap_file_lastmod(__DIR__ . '/chisiamo.php'), 'monthly', '0.6');
sitemap_add($urls, $baseUrl . '/contatti.php', sitemap_file_lastmod(__DIR__ . '/contatti.php'), 'yearly', '0.5');
sitemap_add($urls, $baseUrl . '/privacy.php', sitemap_file_lastmod(__DIR__ . '/privacy.php'), 'yearly', '0.4');
sitemap_add($urls, $baseUrl . '/cookie.php', sitemap_file_lastmod(__DIR__ . '/cookie.php'), 'yearly', '0.4');
sitemap_add($urls, $baseUrl . '/note_legali.php', sitemap_file_lastmod(__DIR__ . '/note_legali.php'), 'yearly', '0.4');

$torneoPages = glob(__DIR__ . '/tornei/*.php') ?: [];
$allowedTournamentFiles = [];
$tournamentSectionFilterReady = false;
if (isset($conn) && !$conn->connect_error && ensure_tornei_section_column($conn)) {
    $tournamentStmt = $conn->prepare('SELECT filetorneo FROM tornei WHERE sezione = ?');
    if ($tournamentStmt) {
        $tournamentStmt->bind_param('s', $siteSection);
        if ($tournamentStmt->execute()) {
            $tournamentSectionFilterReady = true;
            $tournamentResult = $tournamentStmt->get_result();
            while ($tournamentRow = $tournamentResult->fetch_assoc()) {
                $fileName = basename((string)($tournamentRow['filetorneo'] ?? ''));
                if ($fileName !== '') {
                    $allowedTournamentFiles[$fileName] = true;
                }
            }
        }
        $tournamentStmt->close();
    }
}
$torneoExclusions = [
    'partita_eventi.php',
    'TorneoTemplate.php',
    'TorneoTemplateEsport.php',
];

foreach ($torneoPages as $file) {
    $slug = basename((string)$file);
    if (in_array($slug, $torneoExclusions, true)) {
        continue;
    }
    if ($tournamentSectionFilterReady && !isset($allowedTournamentFiles[$slug])) {
        continue;
    }
    sitemap_add(
        $urls,
        $baseUrl . '/tornei/' . $slug,
        sitemap_file_lastmod((string)$file),
        'weekly',
        '0.6'
    );
}

if (isset($conn) && !$conn->connect_error) {
    $blogSectionReady = ensure_blog_post_section_column($conn);
    $blogSql = 'SELECT id, titolo, data_pubblicazione FROM blog_post'
        . ($blogSectionReady ? ' WHERE sezione = ?' : '')
        . ' ORDER BY data_pubblicazione DESC';
    $blogStmt = $conn->prepare($blogSql);
    if ($blogStmt) {
        if ($blogSectionReady) {
            $blogStmt->bind_param('s', $siteSection);
        }
        if ($blogStmt->execute()) {
            $blogQuery = $blogStmt->get_result();
            while ($row = $blogQuery->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $titolo = trim($row['titolo'] ?? '');
            $lastmod = null;
            if (!empty($row['data_pubblicazione'])) {
                $timestamp = strtotime($row['data_pubblicazione']);
                if ($timestamp) {
                    $lastmod = date('c', $timestamp);
                }
            }
            $path = $titolo !== '' ? '/articolo.php?titolo=' . rawurlencode($titolo) : '/articolo.php?id=' . $id;
            sitemap_add(
                $urls,
                $baseUrl . $path,
                $lastmod,
                'weekly',
                '0.7'
            );
            }
        }
        $blogStmt->close();
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url['loc'], ENT_QUOTES, 'UTF-8') ?></loc>
<?php if (!empty($url['lastmod'])): ?>
    <lastmod><?= $url['lastmod']; ?></lastmod>
<?php endif; ?>
<?php if (!empty($url['changefreq'])): ?>
    <changefreq><?= $url['changefreq']; ?></changefreq>
<?php endif; ?>
<?php if (!empty($url['priority'])): ?>
    <priority><?= $url['priority']; ?></priority>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
