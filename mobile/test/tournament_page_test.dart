import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/ranking_surface.dart';
import 'package:tornei_old_school/theme.dart';
import 'package:tornei_old_school/tournament_page.dart';
import 'package:tornei_old_school/tournament_logic.dart';

class GroupedTournamentApi extends DemoApi {
  List<Map<String, dynamic>> get groupedTeams => [
    ...teams.map((t) => {...t, 'girone': 'A'}),
    ...teams.map(
      (t) => {
        ...t,
        'id': (t['id'] as int) + 3,
        'nome': '${t['nome']} B',
        'girone': 'B',
      },
    ),
  ];
  @override
  Future<Map<String, dynamic>> tournamentInfo(String slug) async => {
    'nome': 'Old School Cup',
    'config': {
      'formato': 'girone',
      'numero_gironi': 2,
      'squadre_per_girone': 3,
      'qualificati_gold': 2,
      'qualificati_silver': 2,
      'qualificati_bronzo': 2,
      'regole_html': '<p>Le regole del torneo.</p>',
    },
  };
  @override
  Future<List<Map<String, dynamic>>> standings(String slug) async =>
      groupedTeams;
  @override
  Future<List<Map<String, dynamic>>> tournamentTeams(String slug) async =>
      groupedTeams;
  @override
  Future<List<Map<String, dynamic>>> matches(String slug) async => [
    ...games,
    {
      ...games.first,
      'id': 3,
      'fase': 'GOLD',
      'fase_round': 'FINALE',
      'giornata': null,
      'decisa_rigori': 1,
      'rigori_casa': 4,
      'rigori_ospite': 3,
    },
  ];
}

class CupLegApi extends GroupedTournamentApi {
  @override
  Future<List<Map<String, dynamic>>> matches(String slug) async => [
    {
      ...games.first,
      'id': 10,
      'fase': 'GOLD',
      'fase_round': null,
      'giornata': 2,
      'fase_leg': 'ANDATA',
      'giocata': 1,
    },
    {
      ...games.first,
      'id': 11,
      'fase': 'GOLD',
      'fase_round': 'SEMIFINALE',
      'giornata': 2,
      'fase_leg': 'RITORNO',
      'giocata': 0,
    },
    {
      ...games.first,
      'id': 12,
      'fase': 'GOLD',
      'fase_round': null,
      'giornata': 1,
      'fase_leg': null,
      'giocata': 0,
    },
  ];
}

void main() {
  testWidgets('Tournament scorer goals use readable theme colors', (
    tester,
  ) async {
    for (final theme in [siteTheme(), siteDarkTheme()]) {
      await tester.pumpWidget(
        MaterialApp(
          theme: theme,
          home: Scaffold(
            body: TournamentScorers(
              api: DemoApi(),
              players: const [
                {'id': 1, 'nome': 'Mario', 'cognome': 'Rossi', 'gol': 8},
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester.widget<Text>(find.text('8')).style?.color,
        theme.colorScheme.primary,
      );
      expect(
        tester.widget<Text>(find.text('GOL')).style?.color,
        theme.colorScheme.onSurfaceVariant,
      );
      expect(
        tester.widget<Text>(find.text('CLASSIFICA MARCATORI')).style?.color,
        theme.brightness == Brightness.dark
            ? theme.colorScheme.onSurface
            : Colors.white,
      );
    }
  });

  testWidgets(
    'Dark standings positions stay readable on qualification colors',
    (tester) async {
      final teams = List.generate(4, (i) => {'nome': 'Squadra ${i + 1}'});
      final data = TournamentData(
        {
          'config': {
            'formato': 'campionato',
            'qualificati_gold': 1,
            'qualificati_silver': 1,
            'qualificati_bronzo': 1,
          },
        },
        teams,
        [],
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: siteDarkTheme(),
          home: Scaffold(
            body: StandingsTable(
              api: DemoApi(),
              slug: 'demo',
              label: 'Classifica',
              teams: teams,
              data: data,
            ),
          ),
        ),
      );
      for (final position in ['1', '2', '3']) {
        expect(tester.widget<Text>(find.text(position)).style?.color, siteBlue);
      }
      expect(
        tester.widget<Text>(find.text('4')).style?.color,
        siteDarkTheme().colorScheme.onSurface,
      );
    },
  );

  testWidgets('Match cards use the dark theme surface and readable labels', (
    tester,
  ) async {
    final colors = siteDarkTheme().colorScheme;
    await tester.pumpWidget(
      MaterialApp(
        theme: siteDarkTheme(),
        home: Scaffold(
          body: MatchCard(
            api: DemoApi(),
            match: {
              'id': 1,
              'squadra_casa': 'Old School FC',
              'squadra_ospite': 'Demo United',
              'giocata': 1,
              'gol_casa': 2,
              'gol_ospite': 1,
              'data_partita': '2026-10-01',
              'ora_partita': '20:00',
            },
            phase: 'GOLD',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.widget<Card>(find.byType(Card)).color,
      colors.surfaceContainerLow,
    );
    expect(find.text('TERMINATA'), findsOneWidget);
    expect(find.text('Old School FC'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Scorer table keeps tied positions, alternates rows and opens players after paging',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final players = List.generate(
        16,
        (i) => <String, dynamic>{
          'id': 1,
          'nome': 'Giocatore',
          'cognome': '$i',
          'gol': 8,
          'squadra': 'Old School FC',
        },
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: TournamentScorers(api: DemoApi(), players: players),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('1'), findsNWidgets(15));
      expect(find.text('GOL'), findsNWidgets(15));
      final surfaces = tester
          .widgetList<RankingSurface>(find.byType(RankingSurface))
          .toList();
      expect(surfaces.map((s) => s.index), List.generate(15, (i) => i));
      expect(find.text('Giocatore 15'), findsNothing);
      await tester.ensureVisible(find.byTooltip('Marcatori successivi'));
      await tester.tap(find.byTooltip('Marcatori successivi'));
      await tester.pumpAndSettle();
      expect(find.text('Pagina 2 di 2'), findsOneWidget);
      expect(find.text('Giocatore 15'), findsOneWidget);
      expect(find.text('1'), findsOneWidget);
      expect(
        tester.widget<RankingSurface>(find.byType(RankingSurface)).index,
        15,
      );
      await tester.ensureVisible(find.text('Giocatore 15'));
      await tester.tap(find.text('Giocatore 15'));
      await tester.pumpAndSettle();
      expect(find.byType(PlayerPage), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Cup bracket and calendar separate semifinal legs and final', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 1800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        theme: siteTheme(),
        home: TournamentPage(
          api: CupLegApi(),
          tournament: const {'nome': 'Coppa', 'filetorneo': 'demo.php'},
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Regular season'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coppa Gold').last);
    await tester.pumpAndSettle();
    expect(find.text('Semifinale · Andata'), findsOneWidget);
    expect(find.text('Semifinale · Ritorno'), findsOneWidget);
    expect(find.text('Finale'), findsOneWidget);
    expect(find.text('Giornata 1'), findsNothing);
    expect(find.text('Giornata 2'), findsNothing);
    await tester.tap(find.text('Calendario'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Regular season'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coppa Gold').last);
    await tester.pumpAndSettle();
    expect(tester.widget<MatchCard>(find.byType(MatchCard)).match['id'], 11);
    await tester.tap(find.text('Semifinale · Ritorno').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Semifinale · Andata').last);
    await tester.pumpAndSettle();
    expect(tester.widget<MatchCard>(find.byType(MatchCard)).match['id'], 10);
    await tester.tap(find.text('Semifinale · Andata').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tutte').last);
    await tester.pumpAndSettle();
    expect(find.byType(MatchCard), findsNWidgets(3));
    expect(find.text('Semifinale · Andata'), findsOneWidget);
    expect(find.text('Semifinale · Ritorno'), findsOneWidget);
    expect(find.text('Finale'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'Styled tournament at 320px shows grouped tables, all tabs and phase filters',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: TournamentPage(
            api: GroupedTournamentApi(),
            tournament: const {
              'nome': 'Old School Cup',
              'filetorneo': 'demo.php',
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Girone A'), findsOneWidget);
      final table = find.byType(StandingsTable).first;
      await tester.drag(
        find.descendant(
          of: table,
          matching: find.byType(SingleChildScrollView),
        ),
        const Offset(-250, 0),
      );
      await tester.pumpAndSettle();
      expect(find.text('Old School FC'), findsOneWidget);
      await tester.tap(find.text('Calendario'));
      await tester.pumpAndSettle();
      expect(find.text('Giornata 4'), findsOneWidget);
      await tester.tap(find.text('Regular season'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Coppa Gold').last);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Rigori 4 : 3'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Rigori 4 : 3'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Marcatori'),
        -150,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Marcatori'));
      await tester.pumpAndSettle();
      expect(find.text('Luca Rossi'), findsOneWidget);
      await tester.tap(find.text('Regole'));
      await tester.pumpAndSettle();
      expect(find.text('Le regole del torneo.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
