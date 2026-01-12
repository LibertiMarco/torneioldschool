<?php
if (!function_exists('seo_base_url')) {
    if (!defined('ASSET_VERSION')) {
        define('ASSET_VERSION', '20251220');
    }
    if (!defined('GA_MEASUREMENT_ID')) {
        $envGaId = getenv('GA_MEASUREMENT_ID') ?: '';
        // Fallback to the production property so GA works even if the env var is missing.
        $defaultGaId = 'G-VZ982XSRRN';
        define('GA_MEASUREMENT_ID', $envGaId !== '' ? $envGaId : $defaultGaId);
    }
    if (!defined('GA_DEBUG_MODE')) {
        // Abilita log di debug con ?ga_debug=1 o variabile d'ambiente GA_DEBUG=1
        $debugFlag = (isset($_GET['ga_debug']) && $_GET['ga_debug'] !== '0') || getenv('GA_DEBUG') === '1';
        define('GA_DEBUG_MODE', $debugFlag);
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
        $defaultOg = $base . '/img/logo_old_school_1200.png';
        $image = $meta['image'] ?? $defaultOg;
        $type = $meta['type'] ?? 'website';
        $siteName = $meta['site_name'] ?? 'Tornei Old School';
        $siteAltName = $meta['site_alternate_name'] ?? null;
        $icon = $meta['icon'] ?? ($base . '/img/logo_old_school.png');
        $appleIcon = $meta['apple_icon'] ?? ($base . '/img/logo_old_school.png');

        echo "<title>" . seo_clean($title) . "</title>\n";
        echo '<meta name="description" content="' . seo_clean($description) . '">' . "\n";
        echo '<meta name="robots" content="max-image-preview:large">' . "\n";
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
        echo '<script data-cfasync="false" src="https://cmp.gatekeeperconsent.com/min.js"></script>
  <script data-cfasync="false" src="https://the.gatekeeperconsent.com/cmp.min.js"></script><script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
  <script>
    window.ezstandalone = window.ezstandalone || {};
    ezstandalone.cmd = ezstandalone.cmd || [];
  </script>' . "\n";

        render_analytics_bootstrap();

        // Schema markup di base per brand/logo e sito
        $baseRoot = rtrim($base, '/');
        $logoForSchema = $meta['logo'] ?? $image;

        $orgSchema = seo_org_schema([
            'name' => $siteName,
            'url' => $baseRoot . '/',
            'logo' => $logoForSchema,
            '@type' => 'SportsOrganization',
            'sport' => $meta['sport'] ?? 'Calcio a 5, calcio a 6 e calciotto (8)',
            'alternateName' => $siteAltName,
        ]);
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $baseRoot . '/#website',
            'url' => $baseRoot . '/',
            'name' => $siteName,
            'publisher' => ['@id' => $orgSchema['@id'] ?? ($baseRoot . '/#organization')],
        ];
        if (!empty($siteAltName)) {
            $websiteSchema['alternateName'] = $siteAltName;
        }

        render_jsonld($orgSchema);
        render_jsonld($websiteSchema);
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
        $type = $data['@type'] ?? $data['type'] ?? 'SportsOrganization';
        $sameAs = $data['sameAs'] ?? [];
        $alternateName = $data['alternateName'] ?? $data['site_alternate_name'] ?? null;
        $id = $data['@id'] ?? $data['id'] ?? (rtrim($url, '/') . '/#organization');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $id,
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
        ];

        if ($type === 'SportsOrganization' && $sport !== '') {
            $schema['sport'] = $sport;
        }

        if (is_array($sameAs) && !empty($sameAs)) {
            $schema['sameAs'] = array_values($sameAs);
        }
        if (!empty($alternateName)) {
            $schema['alternateName'] = $alternateName;
        }

        return $schema;
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

        $debug = GA_DEBUG_MODE ? 'true' : 'false';
        echo '<script>window.__GA_MEASUREMENT_ID=' . json_encode($safeId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.__GA_DEBUG__=window.__GA_DEBUG__||' . $debug . ';</script>' . "\n";

        // Load GA4 with consent defaults (cookieless until consent is granted). If a manual gtag
        // snippet already ran, this script skips re-configuring to avoid duplicate pageviews.
        echo '<script>
(function() {
  var gaId = ' . json_encode($safeId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
  var gaDebug = !!window.__GA_DEBUG__;
  if (gaDebug) console.info("[GA] bootstrap start", { id: gaId });
  if (!gaId) { if (gaDebug) console.warn("[GA] missing measurement id"); return; }
  var dl = window.dataLayer = window.dataLayer || [];
  var alreadyConfigured = Array.isArray(dl) && dl.some(function (entry) {
    return entry && entry[0] === "config" && entry[1] === gaId;
  });
  window.gtag = window.gtag || function gtag(){ dl.push(arguments); };
  window.gtag("consent", "default", {
    analytics_storage: "granted",
    ad_storage: "denied",
    ad_user_data: "denied",
    ad_personalization: "denied",
    functionality_storage: "granted",
    security_storage: "granted"
  });
  if (alreadyConfigured) { if (gaDebug) console.warn("[GA] config already present in dataLayer, skipping duplicate config"); return; }
  var s = document.createElement("script");
  s.async = true;
  s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(gaId);
  s.onload = function(){ if (gaDebug) console.info("[GA] gtag.js loaded"); };
  s.onerror = function(){ if (gaDebug) console.error("[GA] gtag.js failed to load", s.src); };
  document.head.appendChild(s);
  window.gtag("js", new Date());
  window.gtag("config", gaId, {
    anonymize_ip: true,
    allow_ad_personalization_signals: false,
    transport_type: "beacon"
  });
  if (gaDebug) window.gtag("event", "ga_diagnostic", { status: "config_sent", id: gaId, ts: Date.now() });
})();
</script>' . "\n";
    }
}
