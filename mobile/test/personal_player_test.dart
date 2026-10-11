import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/account_page.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/personal_player_page.dart';
import 'package:tornei_old_school/player_history.dart';

class PersonalApi extends DemoApi {
  final queries = <Map<String, String>>[];
  bool linked = true;

  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    final result = await super.request(
      path,
      query: query,
      body: body,
      accessToken: accessToken,
      uploads: uploads,
    );
    if (path == 'api/mobile/v1/account.php' && !linked) {
      return {...Map<String, dynamic>.from(result as Map), 'player': null};
    }
    if (path == 'api/mobile/v1/player_stats.php') queries.add({...?query});
    return result;
  }
}

void main() {
  testWidgets(
    'Account links own stats and authenticated match history at 320 px',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = PersonalApi();
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AccountPage(auth: auth, section: 'esport'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Apri le mie statistiche'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Apri le mie statistiche'));
      await tester.pumpAndSettle();
      expect(find.byType(PersonalPlayerPage), findsOneWidget);
      expect(api.queries.single, {'section': 'esport'});
      expect(find.text('Luca Rossi'), findsOneWidget);
      expect(find.text('Presenze: 3'), findsWidgets);
      await tester.scrollUntilVisible(find.text('Partite giocate'), 100);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Partite giocate'));
      await tester.pumpAndSettle();
      expect(find.byType(PlayerHistoryPage), findsOneWidget);
      expect(api.queries.last, {
        'section': 'esport',
        'type': 'presenze',
        'page': '1',
      });
      await tester.tap(find.text('Marcatori'));
      await tester.pumpAndSettle();
      expect(api.queries.last, {
        'section': 'esport',
        'type': 'gol',
        'page': '1',
      });
      expect(api.queries.every((q) => !q.containsKey('giocatore_id')), true);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Unlinked account explains association without a stats button', (
    tester,
  ) async {
    final api = PersonalApi()..linked = false;
    final auth = DemoAuth(api);
    await auth.login();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: AccountPage(auth: auth)),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Le mie statistiche'),
      100,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Apri le mie statistiche'), findsNothing);
    expect(
      find.text(
        'Non hai ancora un profilo giocatore collegato. Contatta un amministratore.',
      ),
      findsOneWidget,
    );
    expect(api.queries, isEmpty);
  });

  testWidgets(
    'Home starts with rankings without the marketing tournament panel',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: HomeDashboard(
              api: DemoApi(),
              section: 'calcio',
              onTournaments: () {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Classifica giocatori totale'), findsOneWidget);
      expect(find.text('Tornei calcetto Napoli'), findsNothing);
      expect(find.text('Scopri i tornei'), findsNothing);
    },
  );
}
