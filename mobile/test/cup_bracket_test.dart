import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/cup_bracket.dart';
import 'package:tornei_old_school/tournament_logic.dart';
import 'package:tornei_old_school/tournament_page.dart';
import 'package:tornei_old_school/theme.dart';

List<Map<String, dynamic>> seeds(int count) => List.generate(
  count,
  (i) => {'name': 'Team ${i + 1}', 'label': '${i + 1}ª'},
);
Map<String, dynamic> game(
  String home,
  String away, {
  String round = 'QUARTI',
  int played = 1,
  int h = 2,
  int a = 0,
  String leg = '',
}) => {
  'id': '$home-$away-$leg',
  'fase': 'GOLD',
  'fase_round': round,
  'squadra_casa': home,
  'squadra_ospite': away,
  'gol_casa': h,
  'gol_ospite': a,
  'giocata': played,
  'fase_leg': leg,
};
void main() {
  test('Fixed seeds, semifinal paths, final and all common bracket sizes', () {
    expect(cupSeedOrder(8), [1, 8, 4, 5, 2, 7, 3, 6]);
    for (final size in [2, 4, 8, 16, 32, 64, 128]) {
      final rows = buildCupBracket(seeds(size), [], 'GOLD');
      expect(rows.length, size - 1);
      expect(rows.last['bracket_slot'], 'F1');
      expect(rows.every((m) => m['template'] == true), true);
    }
    final rows = buildCupBracket(seeds(8), [], 'GOLD');
    expect(rows[0]['squadra_casa'], '1ª · Team 1');
    expect(rows[0]['squadra_ospite'], '8ª · Team 8');
    expect(rows[4]['squadra_casa'], 'Vincente Q1');
    expect(rows[4]['squadra_ospite'], 'Vincente Q2');
    expect(rows[6]['squadra_casa'], 'Vincente S1');
    expect(rows[6]['squadra_ospite'], 'Vincente S2');
  });
  test(
    'Cup ranges and group crossings use the corresponding standings positions',
    () {
      final teams = List.generate(
        16,
        (i) => {'id': i + 1, 'nome': 'Team ${i + 1}', 'punti': 16 - i},
      );
      final data = TournamentData(
        {
          'config': {
            'totale_squadre': 16,
            'qualificati_gold': 8,
            'qualificati_silver': 4,
            'qualificati_bronzo': 4,
          },
        },
        teams,
        [],
      );
      expect(cupSeeds(data, 'GOLD').first['name'], 'Team 1');
      expect(cupSeeds(data, 'SILVER').map((s) => s['name']), [
        'Team 9',
        'Team 10',
        'Team 11',
        'Team 12',
      ]);
      expect(cupSeeds(data, 'BRONZO').last['name'], 'Team 16');
      final grouped = TournamentData(
        {
          'config': {
            'formato': 'girone',
            'numero_gironi': 2,
            'squadre_per_girone': 8,
            'qualificati_gold': 8,
            'qualificati_silver': 4,
            'qualificati_bronzo': 4,
          },
        },
        [
          for (final group in ['A', 'B'])
            for (var i = 1; i <= 8; i++)
              {'nome': '$group$i', 'girone': group, 'punti': 9 - i},
        ],
        [],
      );
      final rows = buildCupBracket(cupSeeds(grouped, 'GOLD'), [], 'GOLD');
      expect(rows.first['squadra_casa'], '1ª girone A · A1');
      expect(rows.first['squadra_ospite'], '4ª girone B · B4');
      expect(rows[2]['squadra_casa'], '1ª girone B · B1');
      expect(rows[2]['squadra_ospite'], '4ª girone A · A4');
      expect(cupSeeds(grouped, 'SILVER').first['name'], 'A5');
      expect(cupSeeds(grouped, 'BRONZO').first['name'], 'A7');
      final incomplete = TournamentData(grouped.info, [], []);
      expect(cupSeeds(incomplete, 'GOLD').first['label'], '1ª girone A');
      expect(
        buildCupBracket(cupSeeds(incomplete, 'GOLD'), [], 'GOLD').length,
        7,
      );
    },
  );
  test('Real matches replace templates and only confirmed winners advance', () {
    final real = game('Team 1', 'Team 8');
    var rows = buildCupBracket(seeds(8), [real], 'GOLD');
    expect(rows.where((m) => m['id'] == real['id']).length, 1);
    expect(rows.where((m) => m['template'] == true).length, 6);
    expect(rows[4]['squadra_casa'], 'Vincente Q1 · Team 1');
    rows = buildCupBracket(seeds(8), [
      game('Team 1', 'Team 8', played: 0),
    ], 'GOLD');
    expect(rows[4]['squadra_casa'], 'Vincente Q1');
    rows = buildCupBracket(seeds(8), [
      game('Team 1', 'Team 8', leg: 'ANDATA'),
    ], 'GOLD');
    expect(rows[4]['squadra_casa'], 'Vincente Q1');
    rows = buildCupBracket(seeds(8), [
      game('Team 1', 'Team 8', leg: 'ANDATA'),
      game('Team 8', 'Team 1', leg: 'RITORNO', h: 3, a: 0),
    ], 'GOLD');
    expect(rows[5]['squadra_casa'], 'Vincente Q1 · Team 8');
    rows = buildCupBracket(seeds(8), [
      {
        ...game('Team 1', 'Team 8', h: 1, a: 1),
        'decisa_rigori': 1,
        'rigori_casa': 3,
        'rigori_ospite': 4,
      },
    ], 'GOLD');
    expect(rows[4]['squadra_casa'], 'Vincente Q1 · Team 8');
    expect(
      buildCupBracket(seeds(8), [real], 'SILVER').where((m) => m['id'] != null),
      isEmpty,
    );
  });
  test('Non-power-of-two sizes have explicit byes; legacy unknown rounds are retained', () {
    final rows = buildCupBracket(seeds(6), [], 'GOLD');
    expect(rows.where((m) => m['bye'] == true).length, 2);
    expect(rows[4]['squadra_casa'], '1ª · Team 1');
    expect(rows[5]['squadra_casa'], '2ª · Team 2');
    final unknown = game('Team 1', 'Team 8', round: 'KO');
    expect(
      buildCupBracket(seeds(8), [
        unknown,
      ], 'GOLD').where((m) => m['id'] == unknown['id']).length,
      1,
    );
  });
  test('Saved pairings remain authoritative when current standings differ', () {
    final rows = buildCupBracket(seeds(8), [game('Team 1', 'Team 4')], 'GOLD');
    final quarters = rows.where((m) => m['fase_round'] == 'QUARTI').toList();
    expect(
      quarters
          .where(
            (m) => '${m['squadra_casa']} ${m['squadra_ospite']}'.contains(
              'Team 4',
            ),
          )
          .length,
      1,
    );
    expect(quarters[1]['squadra_casa'], '8ª · Team 8');
  });
  test('Odd group counts avoid a same-group first round', () {
    final data = TournamentData(
      {
        'config': {
          'formato': 'girone',
          'numero_gironi': 3,
          'squadre_per_girone': 2,
          'qualificati_gold': 6,
        },
      },
      [
        for (final group in ['A', 'B', 'C'])
          for (var i = 1; i <= 2; i++)
            {'nome': '$group$i', 'girone': group, 'punti': 3 - i},
      ],
      [],
    );
    final rows = buildCupBracket(cupSeeds(data, 'GOLD'), [], 'GOLD');
    for (final row in rows.where(
      (m) => m['fase_round'] == 'QUARTI' && m['bye'] != true,
    )) {
      final pattern = RegExp(r'girone ([ABC])');
      expect(
        pattern.firstMatch('${row['squadra_casa']}')!.group(1),
        isNot(pattern.firstMatch('${row['squadra_ospite']}')!.group(1)),
      );
    }
  });
  testWidgets(
    'Templates are readable in both themes at narrow width and enlarged text, with no match link',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      for (final theme in [siteTheme(), siteDarkTheme()]) {
        await tester.pumpWidget(
          MaterialApp(
            theme: theme,
            home: MediaQuery(
              data: const MediaQueryData(textScaler: TextScaler.linear(1.6)),
              child: Scaffold(
                body: SingleChildScrollView(
                  child: CupTemplateCard(
                    match: {
                      'template': true,
                      'bracket_slot': 'Q1',
                      'squadra_casa': '1ª girone A · Squadra con un nome lungo',
                      'squadra_ospite':
                          '4ª girone B · Altra squadra con nome lungo',
                    },
                  ),
                ),
              ),
            ),
          ),
        );
        expect(find.text('Partita da creare'), findsOneWidget);
        expect(find.text('Dettaglio e referto'), findsNothing);
        expect(tester.takeException(), isNull);
      }
    },
  );
}
