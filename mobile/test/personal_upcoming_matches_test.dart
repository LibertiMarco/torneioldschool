import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/personal_player_design.dart';

void main() {
  Future<void> showProfile(
    WidgetTester tester,
    List<Map<String, dynamic>> matches,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PlayerDashboard(
            api: DemoApi(),
            player: const {'nome': 'Luca', 'cognome': 'Rossi'},
            teams: const [],
            section: 'calcio',
            upcomingMatches: matches,
            refresh: () async {},
            history: (_) {},
            openTeam: (_) {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Le tue prossime partite'),
      100,
      scrollable: find.byType(Scrollable).first,
    );
  }

  testWidgets('Profile shows scheduled teams, tournament and local date', (
    tester,
  ) async {
    await showProfile(tester, [
      {
        'id': 12,
        'squadra_casa': 'Old School FC',
        'squadra_ospite': 'Atletico Roma',
        'torneo_nome': 'Torneo autunnale',
        'data_partita': '2026-10-25',
        'ora_partita': '20:30:00',
      },
    ]);
    expect(find.text('Old School FC - Atletico Roma'), findsOneWidget);
    expect(find.text('Torneo autunnale\n25/10/2026 · 20:30'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Profile explains an empty personal calendar', (tester) async {
    await showProfile(tester, []);
    expect(
      find.text('Non ci sono partite da disputare per le tue squadre.'),
      findsOneWidget,
    );
  });
}
