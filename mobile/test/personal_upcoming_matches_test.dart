import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/personal_player_design.dart';
import 'package:tornei_old_school/home_dashboard.dart' show SiteImage;
import 'package:tornei_old_school/details.dart' show MatchPage;
import 'package:tornei_old_school/personal_match_order.dart';

void main() {
  test('Upcoming dates first, undated next, past dates last without mutating input', () {
    final matches = [
      {'id': 1, 'data_partita': '2026-10-19'},
      {'id': 2, 'data_partita': '2026-10-22'},
      {'id': 3, 'data_partita': null},
      {'id': 4, 'data_partita': '2026-10-20', 'ora_partita': '09:00:00'},
      {'id': 5, 'data_partita': '2026-10-21'},
    ];
    final sorted = orderPersonalMatches(
      matches,
      now: DateTime(2026, 10, 20, 23),
    );
    expect(sorted.map((match) => match['id']), [4, 5, 2, 3, 1]);
    expect(matches.first['id'], 1);
    final tomorrow = orderPersonalMatches(matches, now: DateTime(2026, 10, 21));
    expect(tomorrow.map((match) => match['id']), [5, 2, 3, 1, 4]);
  });

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
        'logo_casa': '/img/home.png',
        'logo_ospite': '/img/away.png',
        'torneo_nome': 'Torneo autunnale',
        'data_partita': '2026-10-25',
        'ora_partita': '20:30:00',
      },
    ]);
    expect(find.text('Old School FC'), findsOneWidget);
    expect(find.text('Atletico Roma'), findsOneWidget);
    final card = find.widgetWithText(Card, 'Old School FC');
    final crests = find.descendant(of: card, matching: find.byType(SiteImage));
    expect(tester.widgetList<SiteImage>(crests).map((image) => image.path), [
      '/img/home.png',
      '/img/away.png',
    ]);
    expect(
      find.descendant(of: card, matching: find.byIcon(Icons.event_outlined)),
      findsNothing,
    );
    expect(find.text('Torneo autunnale\n25/10/2026 · 20:30'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.text('Old School FC'));
    await tester.tap(find.text('Old School FC'));
    await tester.pumpAndSettle();
    expect(tester.widget<MatchPage>(find.byType(MatchPage)).id, '12');
  });

  testWidgets('Profile explains an empty personal calendar', (tester) async {
    await showProfile(tester, []);
    expect(
      find.text('Non ci sono partite da disputare per le tue squadre.'),
      findsOneWidget,
    );
  });

  testWidgets(
    'Profile expands matches in groups of three and stops at the end',
    (tester) async {
      await showProfile(tester, [
        for (var i = 1; i <= 7; i++)
          {
            'id': i,
            'squadra_casa': 'Casa $i',
            'squadra_ospite': 'Ospite $i',
            'data_partita': '${DateTime.now().year + 1}-01-0$i',
          },
      ]);
      expect(find.text('Casa 3'), findsOneWidget);
      expect(find.text('Casa 4'), findsNothing);
      Future<void> more() async {
        await tester.scrollUntilVisible(
          find.text('Mostra altre partite'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.ensureVisible(find.text('Mostra altre partite'));
      await tester.pumpAndSettle();
        await tester.tap(find.text('Mostra altre partite'));
        await tester.pumpAndSettle();
      }

      await more();
      expect(find.text('Casa 6'), findsOneWidget);
      expect(find.text('Casa 7'), findsNothing);
      await more();
      expect(find.text('Casa 7'), findsOneWidget);
      expect(find.text('Mostra altre partite'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
