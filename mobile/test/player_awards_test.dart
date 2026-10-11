import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/main.dart' show TournamentPage;
import 'package:tornei_old_school/player_awards.dart';
import 'package:tornei_old_school/personal_player_page.dart';

class AwardsApi extends DemoApi {
  final requests = <String>[];
  bool fail = false;
  @override
  Future<List<Map<String, dynamic>>> playerAwards(
    String id, {
    String section = 'calcio',
  }) async {
    requests.add('$id/$section');
    if (fail) throw const ApiError('Non disponibile');
    return id == '10' && section == 'calcio' ? awards : [];
  }
}

void main() {
  testWidgets(
    'Player card shows personal and team awards and opens the tournament at 320px',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = AwardsApi();
      await tester.pumpWidget(
        MaterialApp(
          home: PlayerPage(api: api, player: api.players.first),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Premi vinti').hitTestable(),
        150,
      );
      await tester.pumpAndSettle();
      expect(api.requests, ['10/calcio']);
      expect(find.text('Miglior attaccante'), findsOneWidget);
      expect(find.text('Coppa Gold'), findsOneWidget);
      expect(find.text('Individuale'), findsOneWidget);
      expect(find.text('Squadra'), findsOneWidget);
      expect(find.text('Campionato Demo · 2026'), findsNWidgets(2));
      await tester.ensureVisible(find.text('Miglior attaccante'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Miglior attaccante'));
      await tester.pumpAndSettle();
      expect(find.byType(TournamentPage), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'Awards retry on failure and reset when player or section changes',
    (tester) async {
      final api = AwardsApi()..fail = true;
      Widget page(String id, String section) => MaterialApp(
        home: Scaffold(
          body: PlayerAwardsSection(api: api, playerId: id, section: section),
        ),
      );
      await tester.pumpWidget(page('10', 'calcio'));
      await tester.pumpAndSettle();
      expect(
        find.text('Premi momentaneamente non disponibili.'),
        findsOneWidget,
      );
      api.fail = false;
      await tester.tap(find.text('Riprova premi'));
      await tester.pumpAndSettle();
      expect(find.text('Miglior attaccante'), findsOneWidget);
      await tester.pumpWidget(page('10', 'esport'));
      await tester.pumpAndSettle();
      expect(find.text('Miglior attaccante'), findsNothing);
      expect(
        find.text('Nessun premio vinto in questa sezione.'),
        findsOneWidget,
      );
      await tester.pumpWidget(page('11', 'calcio'));
      await tester.pumpAndSettle();
      expect(api.requests.last, '11/calcio');
      expect(
        find.text('Nessun premio vinto in questa sezione.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'Authenticated profile uses its own awards without an extra public request',
    (tester) async {
      final api = AwardsApi();
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: PersonalPlayerPage(auth: auth, section: 'calcio'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Premi vinti').hitTestable(),
        200,
      );
      await tester.pumpAndSettle();
      expect(find.text('Miglior attaccante'), findsOneWidget);
      expect(find.text('Coppa Gold'), findsOneWidget);
      expect(api.requests, isEmpty);
      expect(tester.takeException(), isNull);
    },
  );
}
