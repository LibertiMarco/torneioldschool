import 'api.dart';
import 'tournament_list.dart' show normalizedSearch, naturalCategoryOrder;

int stat(dynamic value) => int.tryParse('$value') ?? 0;
String phaseKey(dynamic value) {
  final key = '${value ?? ''}'.trim().toUpperCase();
  if (key.isEmpty || key.startsWith('GIRONE')) return 'REGULAR';
  return key == 'BRONZE' ? 'BRONZO' : key;
}

String groupKey(dynamic value) => '${value ?? ''}'
    .trim()
    .toUpperCase()
    .replaceFirst(RegExp(r'^(GIRONE|GRUPPO)\s+'), '');

class TournamentData {
  TournamentData(this.info, this.teams, this.matches);
  final Map<String, dynamic> info;
  final List<Map<String, dynamic>> teams, matches;
  Map<String, dynamic> get config => info['config'] is Map
      ? Map<String, dynamic>.from(info['config'] as Map)
      : {};
  String get format =>
      '${config['formato'] ?? config['formula_torneo'] ?? ''}'.toLowerCase();
  Map<String, dynamic> get layout => info['mobile_layout'] is Map
      ? Map<String, dynamic>.from(info['mobile_layout'] as Map)
      : {};
  bool get legacy => layout['group_mode'] == 'legacy';
  List<String> get groupLabels {
    final configured = stat(config['numero_gironi']);
    if (legacy) {
      if (format != 'girone') return [];
      if (configured > 1) return List.generate(configured, groupLetters);
      final actual =
          teams
              .map((r) => groupKey(r['girone']))
              .where((v) => v.isNotEmpty)
              .toSet()
              .toList()
            ..sort(naturalCategoryOrder);
      return actual.length > 1 ? actual : [];
    }
    return configured > 0 &&
            stat(config['squadre_per_girone']) > 0 &&
            format != 'campionato' &&
            format != 'eliminazione'
        ? List.generate(configured, groupLetters)
        : [];
  }

  int get groupCount => groupLabels.length;
  int get perGroup {
    final count = stat(config['squadre_per_girone']);
    if (count > 0 || !legacy || groupCount == 0) return count;
    final total = stat(config['totale_squadre']);
    return ((total > teams.length ? total : teams.length) / groupCount)
        .ceil()
        .clamp(1, 1000);
  }

  bool get grouped => groupCount > 0;
  int get spareggioPlaces {
    if (layout['spareggio'] != true) return 0;
    final configured = stat(config['spareggio_qualificati']);
    return (configured > 0
            ? configured
            : stat(layout['spareggio_default'] ?? 16))
        .clamp(0, teamCount);
  }

  int get teamCount {
    var total = stat(config['totale_squadre']);
    if (total <= 0) total = stat(config['campionato_squadre']);
    if (total <= 0) {
      total =
          stat(config['numero_gironi']) * stat(config['squadre_per_girone']);
    }
    return total > 0 ? total : stat(layout['default_team_count'] ?? 18);
  }

  String get rules {
    for (final value in [
      config['regole_html'],
      config['regole'],
      layout['rules_html'],
    ]) {
      if (value != null && '$value'.trim().isNotEmpty) return '$value';
    }
    return 'Le regole saranno pubblicate a breve.';
  }

  static Future<TournamentData> load(TosApi api, String slug) async {
    final results = await Future.wait<dynamic>([
      api.tournamentInfo(slug),
      api.standings(slug),
      api.matches(slug),
      api.tournamentTeams(slug),
    ]);
    final mapping = TosApi.rows(results[3]);
    final rows = TosApi.rows(results[1]).map((team) {
      final byId = mapping.where(
        (r) => stat(r['id']) > 0 && stat(r['id']) == stat(team['id']),
      );
      final byName = mapping.where(
        (r) =>
            '${r['nome']}'.trim().toLowerCase() ==
            '${team['nome']}'.trim().toLowerCase(),
      );
      final found = byId.isNotEmpty
          ? byId.first
          : byName.isNotEmpty
          ? byName.first
          : team;
      return {...team, 'girone': groupKey(found['girone'])};
    }).toList();
    return TournamentData(
      Map<String, dynamic>.from(results[0] as Map),
      rows,
      TosApi.rows(results[2]),
    );
  }

  Map<String, int> get cupPlaces {
    if (legacy && !grouped) {
      final gold = stat(layout['legacy_gold'] ?? 16);
      final silver = layout['legacy_silver'] == null
          ? (teams.length - gold).clamp(0, teams.length)
          : stat(layout['legacy_silver']);
      return {'GOLD': gold, 'SILVER': silver, 'BRONZO': 0};
    }
    final total = teamCount;
    final gold =
        (config.containsKey('qualificati_gold')
                ? stat(config['qualificati_gold'])
                : legacy
                ? stat(layout['legacy_group_gold'])
                : spareggioPlaces > 0
                ? spareggioPlaces ~/ 2
                : stat(layout['default_gold'] ?? 16))
            .clamp(0, total);
    final silver =
        (config.containsKey('qualificati_silver')
                ? stat(config['qualificati_silver'])
                : legacy
                ? layout['legacy_silver'] == null
                      ? (teams.length - gold).clamp(0, teams.length)
                      : stat(layout['legacy_silver'])
                : spareggioPlaces > 0
                ? spareggioPlaces ~/ 2
                : stat(layout['default_silver'] ?? 2))
            .clamp(0, total - gold);
    final bronze = stat(
      config['qualificati_bronzo'] ?? layout['default_bronze'],
    ).clamp(0, total - gold - silver);
    return {'GOLD': gold, 'SILVER': silver, 'BRONZO': bronze};
  }

  Map<String, int> get tableCupPlaces => cupPlaces.map(
    (key, value) => MapEntry(
      key,
      grouped
          ? key == 'GOLD' && legacy && layout['gold_per_group_override'] != null
                ? stat(layout['gold_per_group_override']).clamp(0, perGroup)
                : value ~/ groupCount
          : value,
    ),
  );
  String? cupForPosition(int position) {
    if (!grouped && spareggioPlaces >= 2 && position <= spareggioPlaces) {
      return 'SPAREGGIO';
    }
    if (legacy && !grouped && layout['legacy_silver_at_end'] == true) {
      if (position <= (cupPlaces['GOLD'] ?? 0)) return 'GOLD';
      return position > teams.length - (cupPlaces['SILVER'] ?? 0)
          ? 'SILVER'
          : null;
    }
    var end = 0;
    for (final entry in tableCupPlaces.entries) {
      end += entry.value;
      if (position <= end) return entry.key;
    }
    return layout['eliminated_color'] == true ? 'ELIMINATO' : null;
  }

  List<String> get phases {
    final present = matches.map((r) => phaseKey(r['fase'])).toSet();
    for (final entry in cupPlaces.entries) {
      if (entry.value > 0) present.add(entry.key);
    }
    present.add('REGULAR');
    if (spareggioPlaces >= 2) present.add('SPAREGGIO');
    return [
      'REGULAR',
      'SPAREGGIO',
      'GOLD',
      'SILVER',
      'BRONZO',
    ].where(present.contains).toList();
  }

  Map<String, List<Map<String, dynamic>>> get tables {
    if (!grouped) return {'Classifica': orderStandings(teams, matches)};
    final labels = groupLabels;
    final groups = {
      for (final label in labels) label: <Map<String, dynamic>>[],
    };
    final leftovers = <Map<String, dynamic>>[];
    final seeded = [...teams]
      ..sort((a, b) {
        final id = stat(a['id']).compareTo(stat(b['id']));
        return id != 0
            ? id
            : normalizedSearch('${a['nome']}')
                  .compareTo(normalizedSearch('${b['nome']}'));
      });
    for (final team in seeded) {
      final group = groups[groupKey(team['girone'])];
      if (group != null) {
        group.add(team);
      } else {
        leftovers.add(team);
      }
    }
    for (final group in groups.values) {
      while (group.length < perGroup && leftovers.isNotEmpty) {
        group.add(leftovers.removeAt(0));
      }
    }
    while (leftovers.isNotEmpty) {
      final shortest = groups.values.reduce(
        (a, b) => b.length < a.length ? b : a,
      );
      shortest.add(leftovers.removeAt(0));
    }
    return groups.map(
      (label, rows) => MapEntry('Girone $label', orderStandings(rows, matches)),
    );
  }
}

String groupLetters(int index) {
  var n = index;
  var label = '';
  do {
    label = String.fromCharCode(65 + n % 26) + label;
    n = n ~/ 26 - 1;
  } while (n >= 0);
  return label;
}

List<Map<String, dynamic>> orderStandings(
  List<Map<String, dynamic>> teams,
  List<Map<String, dynamic>> matches,
) {
  final points = teams.map((r) => stat(r['punti'])).toSet().toList()
    ..sort((a, b) => b.compareTo(a));
  final regular = matches.where(
    (m) => stat(m['giocata']) == 1 && phaseKey(m['fase']) == 'REGULAR',
  );
  final result = <Map<String, dynamic>>[];
  for (final total in points) {
    final tied = teams.where((r) => stat(r['punti']) == total).toList();
    // The site's head-to-head rule applies only when exactly two teams tie.
    if (tied.length == 2) {
      final a = '${tied[0]['nome']}';
      final b = '${tied[1]['nome']}';
      var pointsA = 0;
      var pointsB = 0;
      for (final match in regular) {
        final home = '${match['squadra_casa']}';
        final away = '${match['squadra_ospite']}';
        if (!((home == a && away == b) || (home == b && away == a))) continue;
        if (match['gol_casa'] == null || match['gol_ospite'] == null) continue;
        final h = stat(match['gol_casa']);
        final v = stat(match['gol_ospite']);
        if (h == v) {
          pointsA++;
          pointsB++;
        } else if ((h > v && home == a) || (v > h && away == a)) {
          pointsA += 3;
        } else {
          pointsB += 3;
        }
      }
      if (pointsA != pointsB) {
        result.addAll(pointsA > pointsB ? tied : tied.reversed);
        continue;
      }
    }
    tied.sort((a, b) {
      final difference = stat(b['differenza_reti'])
          .compareTo(stat(a['differenza_reti']));
      if (difference != 0) return difference;
      final goals = stat(b['gol_fatti']).compareTo(stat(a['gol_fatti']));
      return goals != 0
          ? goals
          : normalizedSearch('${a['nome']}')
                .compareTo(normalizedSearch('${b['nome']}'));
    });
    result.addAll(tied);
  }
  return result;
}

String matchRound(Map<String, dynamic> match, String phase) {
  if (phase == 'REGULAR' || phase == 'SPAREGGIO') {
    return '${match['giornata'] ?? 0}';
  }
  final stage = '${match['fase_round'] ?? ''}'.trim();
  return stage.isNotEmpty ? stage : '${match['giornata'] ?? 'KO'}';
}

const knockoutRounds = [
  'TRENTADUESIMI',
  'SEDICESIMI',
  'OTTAVI',
  'QUARTI',
  'SEMIFINALE',
  'FINALE',
];
int roundOrder(String left, String right) {
  final a = knockoutRounds.indexOf(left);
  final b = knockoutRounds.indexOf(right);
  if (a >= 0 && b >= 0) return a.compareTo(b);
  return naturalCategoryOrder(left, right);
}

String defaultCalendarRound(List<Map<String, dynamic>> matches, String phase) {
  final rows = matches.where((r) => phaseKey(r['fase']) == phase).toList();
  final available = rows.map((m) => matchRound(m, phase)).toSet().toList()
    ..sort(roundOrder);
  final pending = rows
      .where((r) => stat(r['giocata']) != 1)
      .map((m) => matchRound(m, phase))
      .toSet();
  final preferred = available.where(pending.contains).toList();
  final source = preferred.isNotEmpty ? preferred : available;
  if (source.isEmpty) return '';
  return phase == 'REGULAR' || source.any(knockoutRounds.contains)
      ? source.first
      : source.last;
}
