import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/tournament_logic.dart';

Map<String, dynamic> team(
  String name,
  int id,
  String group,
  int points,
  int difference,
) => {
  'id': id,
  'nome': name,
  'girone': group,
  'punti': points,
  'differenza_reti': difference,
  'gol_fatti': 5,
};
void main() {
  test(
    'Exactly two tied teams use direct encounters; three use goal difference',
    () {
      final teams = [team('A', 1, '', 3, -5), team('B', 2, '', 3, 9)];
      final games = [
        {
          'squadra_casa': 'A',
          'squadra_ospite': 'B',
          'gol_casa': 1,
          'gol_ospite': 0,
          'giocata': 1,
          'fase': 'REGULAR',
        },
      ];
      expect(orderStandings(teams, games).first['nome'], 'A');
      expect(
        orderStandings(teams, [
          {...games.single, 'fase': 'GOLD'},
        ]).first['nome'],
        'B',
      );
      expect(
        orderStandings([
          ...teams,
          team('C', 3, '', 3, 7),
        ], games).map((r) => r['nome']),
        ['B', 'C', 'A'],
      );
    },
  );
  test('Groups reset rankings, fill unassigned teams by ID, and distribute cup places', () {
    final data = TournamentData(
      {
        'config': {
          'formato': 'girone',
          'numero_gironi': 2,
          'squadre_per_girone': 2,
          'qualificati_gold': 2,
          'qualificati_silver': 2,
        },
      },
      [
        team('B1', 3, 'Girone B', 2, 0),
        team('A1', 1, 'A', 3, 0),
        team('Missing', 2, '', 1, 0),
        team('B2', 4, 'B', 0, 0),
      ],
      [],
    );
    expect(data.tables.keys, ['Girone A', 'Girone B']);
    expect(data.tables['Girone A']!.map((r) => r['nome']), ['A1', 'Missing']);
    expect(data.tableCupPlaces, {'GOLD': 1, 'SILVER': 1, 'BRONZO': 0});
    expect(data.cupForPosition(1), 'GOLD');
    expect(data.cupForPosition(2), 'SILVER');
  });
  test('Legacy pages preserve their own qualifications even with zero config defaults', () {
    final data = TournamentData(
      {
        'config': {'qualificati_gold': 0, 'qualificati_silver': 0},
        'mobile_layout': {
          'group_mode': 'legacy',
          'legacy_gold': 14,
          'legacy_silver': 4,
        },
      },
      List.generate(18, (i) => team('Team $i', i, '', 0, 0)),
      [],
    );
    expect(data.cupPlaces, {'GOLD': 14, 'SILVER': 4, 'BRONZO': 0});
    expect(data.cupForPosition(15), 'SILVER');
  });
  test('Legacy group inference and Serie C qualification override match site helper', () {
    final data = TournamentData(
      {
        'config': {'formato': 'girone'},
        'mobile_layout': {
          'group_mode': 'legacy',
          'legacy_group_gold': 16,
          'gold_per_group_override': 8,
        },
      },
      [team('A', 1, 'A', 1, 0), team('B', 2, 'B', 1, 0)],
      [],
    );
    expect(data.groupLabels, ['A', 'B']);
    expect(data.perGroup, 1);
    expect(data.tableCupPlaces['GOLD'], 1);
  });
  test('Formula spareggio and McLeague elimination remain distinct', () {
    final formula = TournamentData(
      {
        'config': {'totale_squadre': 18},
        'mobile_layout': {'spareggio': true, 'spareggio_default': 16},
      },
      [],
      [],
    );
    expect(formula.cupPlaces['GOLD'], 8);
    expect(formula.cupForPosition(1), 'SPAREGGIO');
    expect(formula.phases, contains('SPAREGGIO'));
    final mc = TournamentData(
      {
        'mobile_layout': {
          'default_team_count': 7,
          'default_gold': 4,
          'default_silver': 2,
          'eliminated_color': true,
        },
      },
      [],
      [],
    );
    expect(mc.cupForPosition(7), 'ELIMINATO');
  });
  test(
    'Calendar defaults to pending day and respects named knockout rounds',
    () {
      final games = [
        {'fase': 'REGULAR', 'giornata': 1, 'giocata': 1},
        {'fase': 'REGULAR', 'giornata': 2, 'giocata': 0},
        {'fase': 'GOLD', 'fase_round': 'QUARTI', 'giocata': 1},
        {'fase': 'GOLD', 'fase_round': 'SEMIFINALE', 'giocata': 0},
        {'fase': 'GOLD', 'fase_round': 'FINALE', 'giocata': 0},
      ];
      expect(defaultCalendarRound(games, 'REGULAR'), '2');
      expect(defaultCalendarRound(games, 'GOLD'), 'SEMIFINALE');
      expect(phaseKey('Girone'), 'REGULAR');
      expect(phaseKey('BRONZE'), 'BRONZO');
    },
  );
}
