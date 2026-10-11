<?php
$staffCategories = [
  'arbitro' => [
    'label' => 'Arbitri',
    'description' => 'Direzione di gara affidata al nostro team di ufficiali.',
    'fallback_role' => 'Arbitro',
  ],
  'videomaker' => [
    'label' => 'Videomaker',
    'description' => 'Riprese, highlights e contenuti social dei nostri match.',
    'fallback_role' => 'Videomaker',
  ],
  'organizzazione' => [
    'label' => 'Organizzazione',
    'description' => 'Coordinamento e logistica degli eventi.',
    'fallback_role' => 'Organizzatore',
  ],
  'staff' => [
    'label' => 'Staff',
    'description' => 'Supporto in campo e fuori.',
    'fallback_role' => 'Staff',
  ],
];

$defaultStaff = [
  'arbitro' => [
    ['nome' => 'Arbitro 1', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Arbitro 2', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Arbitro 3', 'foto' => '/img/giocatori/unknown.jpg'],
  ],
  'videomaker' => [
    ['nome' => 'Videomaker 1', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Videomaker 2', 'foto' => '/img/giocatori/unknown.jpg'],
  ],
];

function load_staff_from_db(mysqli $conn, array $defaults): array {
  $data = $defaults;
  $exists = $conn->query("SHOW TABLES LIKE 'staff'");
  if (!$exists || $exists->num_rows === 0) {
    return $data;
  }
  $res = $conn->query("SELECT nome, ruolo, categoria, foto, ordinamento FROM staff ORDER BY categoria, COALESCE(ordinamento, 9999), nome");
  if ($res instanceof mysqli_result) {
    $data = [];
    while ($row = $res->fetch_assoc()) {
      $cat = strtolower(trim($row['categoria'] ?? 'staff'));
      if (!isset($data[$cat])) {
        $data[$cat] = [];
      }
      $data[$cat][] = [
        'nome' => $row['nome'] ?: 'Staff',
        'ruolo' => $row['ruolo'] ?? '',
        'foto' => $row['foto'] ?: '/img/giocatori/unknown.jpg',
      ];
    }
    $res->free();
    foreach ($defaults as $cat => $list) {
      if (empty($data[$cat])) {
        $data[$cat] = $list;
      }
    }
  }
  return $data;
}

$staffData = $defaultStaff;
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
  $staffData = load_staff_from_db($conn, $defaultStaff);
}

function about_content(array $staffData, array $staffCategories): array {
    return [
        'title' => 'Chi siamo',
        'subtitle' => 'Passione, amicizia e sport - lo spirito Old School',
        'intro_html' => '<p>Siamo <strong>Frank</strong> ed <strong>Emanuele</strong>, due amici con la stessa visione: trasformare ogni partita in un momento di aggregazione, risate e vera competizione sana.</p><p>Organizziamo <strong>tornei amatoriali di calcio a 5 e calcio a 8</strong> - e, qualche volta, anche di altri sport - nella zona di <strong>Napoli Nord</strong>. I nostri eventi non hanno premi in denaro, ma offrono qualcosa di molto piu importante: <strong>unione, amicizia e divertimento puro</strong>.</p><p>Ogni torneo e pensato per essere un\'esperienza completa: arbitri qualificati, sistema <strong>VAR</strong>, <strong>highlights</strong>, completini personalizzati e anche <strong>contenuti TikTok</strong> per far rivivere i momenti piu belli di ogni partita.</p>',
        'highlight' => 'Tornei Old School - il calcio come una volta, con lo spirito di oggi.',
        'facts' => [
            ['title' => 'Dove operiamo', 'text' => 'Napoli e area nord della provincia.', 'link' => ''],
            ['title' => 'Contatto diretto', 'text' => 'info@torneioldschool.it', 'link' => 'mailto:info@torneioldschool.it'],
            ['title' => 'Sponsor e partnership', 'text' => 'sponsor@torneioldschool.it', 'link' => 'mailto:sponsor@torneioldschool.it'],
            ['title' => 'Community', 'text' => 'Calendari, classifiche, articoli e storie dei nostri tornei amatoriali.', 'link' => ''],
        ],
        'organizers' => [
            ['nome' => 'Frank', 'description' => 'Spirito organizzativo del gruppo, gestisce logistica e contatti con squadre e arbitri. Sempre pronto a dare energia e motivazione al campo.'],
            ['nome' => 'Emanuele', 'description' => 'Creativo e appassionato di comunicazione, cura i social, i video e l\'esperienza digitale dei nostri tornei.'],
        ],
        'staff' => about_staff_groups($staffData, $staffCategories),
    ];
}

function about_staff_groups(array $data, array $categories): array {
    $result = [];
    foreach (array_unique(array_merge(['arbitro', 'videomaker', 'organizzazione', 'staff'], array_keys($data))) as $key) {
        if (empty($data[$key])) continue;
        $category = $categories[$key] ?? [];
        $fallback = $category['fallback_role'] ?? ucfirst($key);
        $members = [];
        foreach ($data[$key] as $member) {
            $member['ruolo'] = trim($member['ruolo'] ?? '') ?: $fallback;
            $members[] = $member;
        }
        $result[] = ['label' => $category['label'] ?? ucwords(str_replace('_', ' ', $key)), 'description' => $category['description'] ?? 'Staff', 'members' => $members];
    }
    return $result;
}
