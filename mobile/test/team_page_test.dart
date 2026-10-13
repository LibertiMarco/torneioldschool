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
      'giornata': 2,
      'fase_round': 'SEMIFINALE',
      'fase_leg': 'RITORNO',
      'data_partita': '2026-10-10',
      'ora_partita': '21:00',
    },
  ];
}

void main() {
  testWidgets('Dark team page adapts match cards, titles and roster surfaces', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = TeamFixtures();
    final theme = siteDarkTheme();
    await tester.pumpWidget(
      MaterialApp(
        theme: theme,
        home: TeamPage(api: api, slug: 'demo', team: api.teams.first),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.widget<Text>(find.text('Partite disputate')).style?.color,
      theme.colorScheme.onSurface,
    );
    final cards = tester.widgetList<Container>(
      find.descendant(
        of: find.byType(TeamMatchTile),
        matching: find.byType(Container),
      ),
    );
    expect(
      cards.any(
        (card) =>
            (card.decoration is BoxDecoration) &&
            (card.decoration as BoxDecoration).color ==
                theme.colorScheme.surfaceContainerLow,
      ),
      isTrue,
    );
    await tester.ensureVisible(find.text('Rosa'));
    await tester.tap(find.text('Rosa'));
    await tester.pumpAndSettle();
    expect(
      tester.widget<Text>(find.text('Rosa squadra')).style?.color,
      theme.colorScheme.onSurface,
    );
    final roster = tester.widgetList<Container>(
      find.descendant(
        of: find.byType(RosterPlayerCard),
        matching: find.byType(Container),
      ),
    );
    expect(
      roster.any(
        (card) =>
            (card.decoration is BoxDecoration) &&
            (card.decoration as BoxDecoration).color ==
                theme.colorScheme.surfaceContainerLow,
      ),
      isTrue,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Roster badges use team role, fallback role and captain flag at 320px',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 600));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi();
      for (final role in ['Portiere', 'GK', 'P', 'POR']) {
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: Scaffold(
              body: RosterPlayerCard(
                api: api,
                player: {
                  'nome': 'Mario',
                  'cognome': 'Rossi',
                  'ruolo_squadra': role,
                  'is_captain': '1',
                },
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          find.text('GK'),
          role == 'GK' ? findsNWidgets(2) : findsOneWidget,
        );
        expect(find.text('C'), findsOneWidget);
        expect(find.byTooltip('Portiere'), findsOneWidget);
        expect(find.byTooltip('Capitano'), findsOneWidget);
        expect(tester.takeException(), isNull);
      }
      for (final teamRole in ['', 'Difensore']) {
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: Scaffold(
              body: RosterPlayerCard(
                api: api,
                player: {
                  'nome': 'Mario',
                  'cognome': 'Rossi',
                  'ruolo_squadra': teamRole,
                  'ruolo': 'Portiere',
                  'is_captain': 0,
                },
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          find.text('GK'),
          teamRole.isEmpty ? findsOneWidget : findsNothing,
        );
        expect(find.text('C'), findsNothing);
        expect(tester.takeException(), isNull);
      }
    },
  );
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
      expect(find.text('Coppa Gold · Semifinale · Ritorno'), findsOneWidget);
      expect(find.text('Other A'), findsNothing);
      expect(find.text('Sporting Club'), findsNothing);
      await tester.tap(find.text('Rosa'));
      await tester.pumpAndSettle();
      expect(find.text('Rosa squadra'), findsOneWidget);
      expect(find.text('Luca Rossi'), findsOneWidget);
      expect(find.text('C'), findsOneWidget);
      expect(find.byTooltip('Capitano'), findsOneWidget);
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
