import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/theme.dart';
import 'package:tornei_old_school/tournament_page.dart';

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

void main() {
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
