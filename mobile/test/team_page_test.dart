import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/team_page.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/theme.dart';
import 'package:tornei_old_school/details.dart' show MatchPage;

class TeamFixtures extends DemoApi {
  @override
  Future<List<Map<String, dynamic>>> matches(String slug) async => [
    ...games,
    {
      'id': 3,
      'giocata': 1,
      'squadra_casa': 'Other A',
      'squadra_ospite': 'Other B',
      'gol_casa': 5,
      'gol_ospite': 1,
      'data_partita': '2026-10-10',
    },
    {
      'id': 4,
      'giocata': 1,
      'squadra_casa': 'Rival',
      'squadra_ospite': 'Old School FC',
      'gol_casa': 2,
      'gol_ospite': 2,
      'decisa_rigori': 1,
      'rigori_casa': 3,
      'rigori_ospite': 4,
      'fase': 'GOLD',
      'data_partita': '2026-10-10',
      'ora_partita': '21:00',
    },
  ];
}

void main() {
  test('Team history includes played home/away matches in all phases, newest first', () async {
    final rows = teamPlayedMatches(
      await TeamFixtures().matches('demo'),
      ' old school fc ',
    );
    expect(rows.map((m) => m['id']), [4, 1]);
    expect(
      teamPlayedMatches(await TeamFixtures().matches('demo'), 'Missing'),
      isEmpty,
    );
  });
  test('Results use selected team perspective and include penalty winner', () {
    final match = {'squadra_casa': 'Rival', 'gol_casa': 3, 'gol_ospite': 1};
    expect(teamMatchOutcome(match, 'Old School FC'), 'Sconfitta');
    expect(teamMatchOutcome(match, 'Rival'), 'Vittoria');
    expect(teamMatchOutcome({...match, 'gol_casa': 1}, 'Rival'), 'Pareggio');
    expect(
      teamMatchOutcome({
        ...match,
        'gol_casa': 1,
        'decisa_rigori': 1,
        'rigori_casa': 2,
        'rigori_ospite': 4,
      }, 'Old School FC'),
      'Vittoria',
    );
  });
  testWidgets(
    'Team opens played matches; roster switch preserves stats and match opens detail',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = TeamFixtures();
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: TeamPage(api: api, slug: 'demo', team: api.teams.first),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Partite disputate'), findsOneWidget);
      expect(find.text('2 partite'), findsOneWidget);
      expect(find.text('Coppa Gold'), findsOneWidget);
      expect(find.text('Other A'), findsNothing);
      expect(find.text('Sporting Club'), findsNothing);
      await tester.tap(find.text('Rosa'));
      await tester.pumpAndSettle();
      expect(find.text('Rosa squadra'), findsOneWidget);
      expect(find.text('Luca Rossi'), findsOneWidget);
      expect(find.text('Capitano'), findsOneWidget);
      expect(find.text('4'), findsWidgets);
      await tester.binding.setSurfaceSize(const Size(320, 844));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Partite'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('3 : 1'),
        180,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('3 : 1'));
      await tester.pumpAndSettle();
      expect(find.byType(MatchPage), findsOneWidget);
      expect(find.text('Dettaglio partita'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
