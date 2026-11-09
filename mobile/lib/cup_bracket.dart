import 'tournament_logic.dart';

/// Standard fixed bracket: 1-8, 4-5, 2-7, 3-6 (and the same rule at any size).
List<int> cupSeedOrder(int size) {
  var order = [1, 2];
  while (order.length < size) {
    final sum = order.length * 2 + 1;
    order = order.expand((seed) => [seed, sum - seed]).toList();
  }
  return order;
}

List<Map<String, dynamic>> cupSeeds(TournamentData data, String phase) {
  final count = data.cupPlaces[phase] ?? 0;
  final places = data.tableCupPlaces[phase] ?? 0;
  final result = <Map<String, dynamic>>[];
  var offset = 0;
  for (final entry in data.tableCupPlaces.entries) {
    if (entry.key == phase) break;
    offset += entry.value;
  }
  final tables = data.tables;
  if (data.format == 'eliminazione') {
    final teams = tables.values.expand((rows) => rows).toList();
    return List.generate(
      data.teamCount,
      (i) => {
        'name': i < teams.length ? teams[i]['nome'] : '',
        'label': '${i + 1}ª testa di serie',
      },
    );
  }
  for (var rank = 0; rank < (data.grouped ? places : count); rank++) {
    for (final table in tables.entries) {
      final position =
          data.legacy &&
              !data.grouped &&
              phase == 'SILVER' &&
              data.layout['legacy_silver_at_end'] == true
          ? (data.teams.length - count).clamp(0, data.teams.length) + rank
          : offset + rank;
      final team = position < table.value.length ? table.value[position] : null;
      result.add({
        'name': team?['nome'] ?? '',
        'group': data.grouped ? table.key : '',
        'label': data.grouped
            ? '${position + 1}ª ${table.key.replaceFirst('Girone', 'girone')}'
            : '${position + 1}ª in classifica',
      });
    }
  }
  return result;
}

class _CupSide {
  _CupSide(this.name, this.label, this.candidates, [this.group = '']);
  final String name, label;
  final String group;
  final Set<String> candidates;
  String get display => name.isEmpty ? label : '$label · $name';
}

String _teamKey(dynamic value) => '${value ?? ''}'.trim().toLowerCase();

String? cupMatchWinner(List<Map<String, dynamic>> games) =>
    _cupWinner(games, '')?.name;

_CupSide? _cupWinner(List<Map<String, dynamic>> games, String label) {
  if (games.isEmpty ||
      games.any(
        (m) =>
            stat(m['giocata']) != 1 ||
            m['gol_casa'] == null ||
            m['gol_ospite'] == null,
      )) {
    return null;
  }
  final legs = games.map((m) => matchLeg(m['fase_leg'])).toSet();
  if (legs.contains('ANDATA') != legs.contains('RITORNO')) return null;
  final totals = <String, int>{};
  final names = <String, String>{};
  for (final game in games) {
    for (final side in ['casa', 'ospite']) {
      final name = '${game['squadra_$side'] ?? ''}';
      final key = _teamKey(name);
      names[key] = name;
      totals[key] = (totals[key] ?? 0) + stat(game['gol_$side']);
    }
  }
  if (totals.length != 2) return null;
  final ordered = totals.keys.toList()
    ..sort((a, b) => totals[b]!.compareTo(totals[a]!));
  String? winner = totals[ordered[0]] != totals[ordered[1]] ? ordered[0] : null;
  if (winner == null) {
    final penalties = games
        .where(
          (m) =>
              stat(m['decisa_rigori']) == 1 &&
              m['rigori_casa'] != null &&
              m['rigori_ospite'] != null,
        )
        .toList();
    if (penalties.isNotEmpty) {
      final game = penalties.last;
      final home = stat(game['rigori_casa']),
          away = stat(game['rigori_ospite']);
      if (home != away) {
        winner = _teamKey(
          game[home > away ? 'squadra_casa' : 'squadra_ospite'],
        );
      }
    }
  }
  return winner == null ? null : _CupSide(names[winner]!, label, {winner});
}

/// Projection only: never inserts matches or changes saved fixtures.
List<Map<String, dynamic>> buildCupBracket(
  List<Map<String, dynamic>> seeds,
  List<Map<String, dynamic>> matches,
  String phase,
) {
  final actual = matches.where((m) => phaseKey(m['fase']) == phase).toList();
  var size = 2;
  while (size < seeds.length) {
    size *= 2;
  }
  final configuredSize = size;
  for (final match in actual) {
    final round = knockoutRounds.indexOf(matchRound(match, phase));
    if (round >= 0) {
      final required = 1 << (knockoutRounds.length - round);
      if (required > size) size = required;
    }
  }
  if (seeds.length < 2 && actual.isEmpty) return [];
  // If only later fixtures exist, generic seeds fill the earlier unknown slots.
  final participants = List<_CupSide?>.generate(size, (index) {
    if (index >= seeds.length) {
      return seeds.isEmpty || size > configuredSize
          ? _CupSide('', '${index + 1}ª qualificata', {})
          : null;
    }
    final seed = seeds[index], name = '${seed['name'] ?? ''}';
    return _CupSide(
      name,
      '${seed['label']}',
      name.isEmpty ? {} : {_teamKey(name)},
      '${seed['group'] ?? ''}',
    );
  });
  var sides = cupSeedOrder(size).map((seed) => participants[seed - 1]).toList();
  // With an odd number of groups, redistribute opponents to avoid same-group ties.
  for (var i = 0; i < sides.length; i += 2) {
    final home = sides[i], away = sides[i + 1];
    if (home == null ||
        away == null ||
        home.group.isEmpty ||
        home.group != away.group) {
      continue;
    }
    for (var j = 0; j < sides.length; j += 2) {
      final otherHome = sides[j], otherAway = sides[j + 1];
      if (j == i ||
          otherHome == null ||
          otherAway == null ||
          otherAway.group == home.group ||
          otherHome.group == away.group) {
        continue;
      }
      sides[i + 1] = otherAway;
      sides[j + 1] = away;
      break;
    }
  }
  // Preserve authored first-round pairings even if the current table has moved.
  final firstRoundIndex = knockoutRounds.length - (size.bitLength - 1);
  final firstRound = firstRoundIndex < 0
      ? 'ROUND_$size'
      : knockoutRounds[firstRoundIndex];
  final locked = <int>{};
  final alignedPairs = <String>{};
  for (final game in actual.where((m) => matchRound(m, phase) == firstRound)) {
    final names = [
      _teamKey(game['squadra_casa']),
      _teamKey(game['squadra_ospite']),
    ];
    final pair = [...names]..sort();
    if (!alignedPairs.add(pair.join('|'))) continue;
    final positions = names
        .map(
          (name) =>
              sides.indexWhere((s) => s != null && _teamKey(s.name) == name),
        )
        .toList();
    if (positions.any((p) => p < 0 || locked.contains(p ~/ 2))) continue;
    final target = positions.first ~/ 2;
    for (var end = 0; end < 2; end++) {
      final from = sides.indexWhere(
        (s) => s != null && _teamKey(s.name) == names[end],
      );
      final to = target * 2 + end;
      final old = sides[to];
      sides[to] = sides[from];
      sides[from] = old;
    }
    locked.add(target);
  }
  final output = <Map<String, dynamic>>[];
  final handledRounds = <String>{};
  var roundIndex = knockoutRounds.length - (size.bitLength - 1);
  while (sides.length >= 2) {
    final round = roundIndex < 0
        ? 'ROUND_${sides.length}'
        : knockoutRounds[roundIndex];
    handledRounds.add(round);
    final prefix =
        const {
          'FINALE': 'F',
          'SEMIFINALE': 'S',
          'QUARTI': 'Q',
          'OTTAVI': 'O',
          'SEDICESIMI': 'SD',
          'TRENTADUESIMI': 'TD',
        }[round] ??
        'R${sides.length}-';
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final game in actual.where((m) => matchRound(m, phase) == round)) {
      final pair = [
        _teamKey(game['squadra_casa']),
        _teamKey(game['squadra_ospite']),
      ]..sort();
      groups.putIfAbsent(pair.join('|'), () => []).add(game);
    }
    final remaining = groups.values.toList();
    final next = <_CupSide?>[];
    for (var i = 0; i < sides.length; i += 2) {
      final home = sides[i], away = sides[i + 1];
      final slot = '$prefix${i ~/ 2 + 1}';
      final candidates = {...?home?.candidates, ...?away?.candidates};
      var selected = -1, best = 0;
      for (var j = 0; j < remaining.length; j++) {
        final game = remaining[j].first;
        final a = _teamKey(game['squadra_casa']),
            b = _teamKey(game['squadra_ospite']);
        final exact =
            home != null &&
            away != null &&
            ((home.candidates.contains(a) && away.candidates.contains(b)) ||
                (home.candidates.contains(b) && away.candidates.contains(a)));
        final score = exact
            ? 3
            : (candidates.contains(a) ? 1 : 0) +
                  (candidates.contains(b) ? 1 : 0);
        if (score > best) {
          best = score;
          selected = j;
        }
      }
      // Saved custom pairings take precedence over projected seeds.
      if (selected < 0 && remaining.length >= (sides.length - i) ~/ 2) {
        selected = 0;
      }
      final games = selected >= 0
          ? remaining.removeAt(selected)
          : <Map<String, dynamic>>[];
      final label = 'Vincente $slot';
      if (games.isNotEmpty) {
        games.sort(
          (a, b) => (matchLeg(a['fase_leg']) == 'RITORNO' ? 1 : 0).compareTo(
            matchLeg(b['fase_leg']) == 'RITORNO' ? 1 : 0,
          ),
        );
        output.addAll(games.map((game) => {...game, 'bracket_slot': slot}));
        final possible = {
          ...candidates,
          ...games.expand(
            (m) => [_teamKey(m['squadra_casa']), _teamKey(m['squadra_ospite'])],
          ),
        };
        next.add(_cupWinner(games, label) ?? _CupSide('', label, possible));
      } else {
        final bye = home == null || away == null;
        final survivor = home ?? away;
        output.add({
          'fase': phase,
          'fase_round': round,
          'bracket_slot': slot,
          'template': true,
          'bye': bye,
          'squadra_casa': home?.display ?? 'Accesso diretto',
          'squadra_ospite': away?.display ?? 'Accesso diretto',
        });
        next.add(bye ? survivor : _CupSide('', label, candidates));
      }
    }
    for (final games in remaining) {
      output.addAll(games);
    }
    sides = next;
    roundIndex++;
  }
  output.addAll(
    actual.where((m) => !handledRounds.contains(matchRound(m, phase))),
  );
  return output;
}
