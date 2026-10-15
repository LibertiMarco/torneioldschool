import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/api.dart';

class AccessApi extends DemoApi {
  int tournamentReads = 0;
  int fullRankingReads = 0;
  @override
  Future<List<Map<String, dynamic>>> tournaments(String section) {
    tournamentReads++;
    return super.tournaments(section);
  }

  @override
  Future<Map<String, dynamic>> playerRanking({
    String order = 'gol',
    int page = 1,
    int perPage = 5,
    String search = '',
  }) {
    if (perPage > 5) fullRankingReads++;
    return super.playerRanking(
      order: order,
      page: page,
      perPage: perPage,
      search: search,
    );
  }
}

void main() {
  testWidgets('Access errors offer login and reload after authentication', (
    tester,
  ) async {
    final api = AccessApi();
    final auth = DemoAuth(api);
    var retries = 0;
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => AppAuthScope(auth: auth, child: child!),
        home: Scaffold(
          body: ErrorPanel(
            error: const ApiError(
              'Servizio temporaneamente non disponibile.',
              status: 401,
            ),
            retry: () => retries++,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Accedi per visualizzare questo contenuto.'),
      findsOneWidget,
    );
    expect(
      find.text('Servizio temporaneamente non disponibile.'),
      findsNothing,
    );
    expect(find.text('Riprova'), findsNothing);
    await tester.tap(find.text('Accedi'));
    await tester.pumpAndSettle();
    expect(auth.user, isNotNull);
    expect(retries, 1);
  });
  testWidgets(
    'Guest tournament tab does not load tournaments; login unlocks it',
    (tester) async {
      final api = AccessApi();
      final auth = DemoAuth(api);
      await tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => AppAuthScope(auth: auth, child: child!),
          home: HomePage(api: api, auth: auth, onTheme: () {}),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(api.tournamentReads, 0);
      expect(find.text('Accedi'), findsOneWidget);
      await tester.tap(find.text('Accedi'));
      await tester.pumpAndSettle();
      expect(api.tournamentReads, 1);
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.text('Accedi'), findsOneWidget);
    },
  );

  for (final order in ['gol', 'presenze']) {
    testWidgets('Full $order ranking requires login and relocks on logout', (
      tester,
    ) async {
      final api = AccessApi();
      final auth = DemoAuth(api);
      await tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => AppAuthScope(auth: auth, child: child!),
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => openPage(
                  context,
                  GlobalRankingPage(api: api, initialOrder: order),
                ),
                child: const Text('Apri classifica'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Apri classifica'));
      await tester.pumpAndSettle();
      expect(api.fullRankingReads, 0);
      expect(find.byType(GlobalRankingPage), findsNothing);
      await tester.tap(find.text('Accedi'));
      await tester.pumpAndSettle();
      expect(api.fullRankingReads, 1);
      expect(find.byType(GlobalRankingPage), findsOneWidget);
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.byType(GlobalRankingPage), findsNothing);
      expect(find.text('Accedi'), findsOneWidget);
    });
  }

  testWidgets('Tournament detail links require login', (tester) async {
    final api = AccessApi();
    final auth = DemoAuth(api);
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => AppAuthScope(auth: auth, child: child!),
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => openPage(
                context,
                TournamentPage(api: api, tournament: const {'nome': 'Test'}),
              ),
              child: const Text('Vedi torneo'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Vedi torneo'));
    await tester.pumpAndSettle();
    expect(find.byType(TournamentPage), findsNothing);
    expect(find.text('Accedi'), findsOneWidget);
  });
}
