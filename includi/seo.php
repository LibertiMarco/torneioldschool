<?php
if (!function_exists('seo_base_url')) {
    if (!defined('ASSET_VERSION')) {
        define('ASSET_VERSION', '20251204');
    }
    if (!defined('GA_MEASUREMENT_ID')) {
        $envGaId = getenv('GA_MEASUREMENT_ID') ?: '';
        define('GA_MEASUREMENT_ID', $envGaId);
    }

    function asset_url(string $path, ?string $version = null): string
    {
        $ver = $version ?? ASSET_VERSION;
        $separator = (strpos($path, '?') === false) ? '?' : '&';
        return $path . $separator . 'v=' . rawurlencode($ver);
    }

    function seo_base_url(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    function seo_current_url(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return rtrim(seo_base_url(), '/') . $uri;
    }

    function seo_clean(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    function seo_trim(string $text, int $length = 160): string
    {
        $clean = trim(strip_tags($text));
        $lenFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        if ($lenFn($clean) > $length) {
            return rtrim($substrFn($clean, 0, $length - 1)) . '...';
        }
        return $clean;
    }

    function render_seo_tags(array $meta = []): void
    {
        $base = seo_base_url();
        $title = trim($meta['title'] ?? 'Tornei Old School');
        $description = seo_trim($meta['description'] ?? 'Tornei, classifiche e partite raccontate in tempo reale da Tornei Old School.');
        $url = $meta['url'] ?? seo_current_url();
        $canonical = $meta['canonical'] ?? $url;
        $image = $meta['image'] ?? ($base . '/img/logo_old_school.png');
        $type = $meta['type'] ?? 'website';
        $siteName = $meta['site_name'] ?? 'Tornei Old School';
        $icon = $meta['icon'] ?? ($base . '/img/logo_old_school.png');
        $appleIcon = $meta['apple_icon'] ?? ($base . '/img/logo_old_school.png');

        echo "<title>" . seo_clean($title) . "</title>\n";
        echo '<meta name="description" content="' . seo_clean($description) . '">' . "\n";
        echo '<link rel="canonical" href="' . seo_clean($canonical) . '">' . "\n";
        echo '<link rel="icon" type="image/png" href="' . seo_clean($icon) . '">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . seo_clean($appleIcon) . '">' . "\n";

        echo '<meta property="og:type" content="' . seo_clean($type) . '">' . "\n";
        echo '<meta property="og:title" content="' . seo_clean($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . seo_clean($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . seo_clean($url) . '">' . "\n";
        echo '<meta property="og:image" content="' . seo_clean($image) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . seo_clean($siteName) . '">' . "\n";
        echo '<meta property="og:locale" content="it_IT">' . "\n";

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . seo_clean($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . seo_clean($description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . seo_clean($image) . '">' . "\n";

        render_analytics_bootstrap();
    }

    function render_jsonld(array $schema): void
    {
        if (!$schema) {
            return;
        }
        echo "<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }

    function seo_breadcrumb_schema(array $items): array
    {
        $list = [];
        $pos = 1;
        foreach ($items as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $list[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $name,
                'item' => $item['url'] ?? seo_current_url(),
            ];
        }

        if (!$list) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    function seo_org_schema(array $data = []): array
    {
        $base = seo_base_url();
        $logo = $data['logo'] ?? ($base . '/img/logo_old_school.png');
        $url = $data['url'] ?? $base;
        $name = $data['name'] ?? 'Tornei Old School';
        $sport = $data['sport'] ?? 'Calcio';

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsOrganization',
            'name' => $name,
            'url' => $url,
            'sport' => $sport,
            'logo' => $logo,
        ];
    }

    function seo_event_schema(array $data): array
    {
        if (empty($data['name']) || empty($data['startDate'])) {
            return [];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $data['name'],
            'startDate' => $data['startDate'],
            'location' => [
                '@type' => 'Place',
                'name' => $data['location'] ?? '',
            ],
            'homeTeam' => [
                '@type' => 'SportsTeam',
                'name' => $data['homeTeam'] ?? '',
            ],
            'awayTeam' => [
                '@type' => 'SportsTeam',
                'name' => $data['awayTeam'] ?? '',
            ],
            'url' => $data['url'] ?? seo_current_url(),
        ];

        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        if (!empty($data['location_address'])) {
            $schema['location']['address'] = $data['location_address'];
        }

        return $schema;
    }

    function render_analytics_bootstrap(): void
    {
        $id = trim(GA_MEASUREMENT_ID ?? '');
        if ($id === '') {
            return;
        }
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $id);
        if ($safeId === '') {
            return;
        }

        echo '<script>window.__GA_MEASUREMENT_ID=' . json_encode($safeId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
    }
}
