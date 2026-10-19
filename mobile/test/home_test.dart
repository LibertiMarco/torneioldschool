import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/account_page.dart';

import 'auth_test.dart' show MemorySessionStore;

class SignedInTestAuth extends TosAuth {
  SignedInTestAuth(super.api) : super(storage: MemorySessionStore()) {
    user = {
      'id': 1,
      'nome': 'Test',
      'cognome': 'User',
      'permissions': {'admin': false, 'graphics': false},
    };
  }
  @override
  Future<void> restore() async {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const versionChannel = MethodChannel('tos/app_version');
  final currentVersionResponse = jsonEncode({
    'android': {'build': 8, 'url': 'https://example.test/update'},
    'ios': {'build': 8, 'url': 'https://example.test/update'},
  });
  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(versionChannel, (_) async => 8);
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(versionChannel, null);
  });

  testWidgets(
    'Signed-in user browses tournaments and scores without admin tools',
    (tester) async {
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient((request) async {
          if (request.url.path.endsWith('app_version.php')) {
            return http.Response(currentVersionResponse, 200);
          }
          if (request.url.path.endsWith('tournaments.php')) {
            return http.Response(
              jsonEncode({
                'tournaments': [
                  {
                    'nome': 'Cup',
                    'filetorneo': 'Cup.php',
                    'categoria': 'Calcio',
                    'stato': 'in corso',
                  },
                ],
              }),
              200,
            );
          }
          if (request.url.path.endsWith('leggiClassifica.php')) {
            return http.Response(
              jsonEncode([
                {
                  'nome': 'Team A',
                  'punti': 3,
                  'giocate': 1,
                  'differenza_reti': 2,
                },
              ]),
              200,
            );
          }
          return http.Response(
            jsonEncode({
              '1': [
                {
                  'squadra_casa': 'Team A',
                  'squadra_ospite': 'Team B',
                  'gol_casa': 2,
                  'gol_ospite': 0,
                  'giocata': 1,
                },
              ],
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(TosApp(api: api, auth: SignedInTestAuth(api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(find.text('Cup'), findsOneWidget);
      expect(find.text('Gestione'), findsNothing);
      await tester.ensureVisible(find.text('Cup'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cup'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Team A'), 150);
      expect(find.text('Team A'), findsOneWidget);
      await tester.ensureVisible(find.text('Calendario'));
      await tester.tap(find.text('Calendario'));
      await tester.pumpAndSettle();
      expect(find.text('2  :  0'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'Service outage offers retry and leaves account navigation usable',
    (tester) async {
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient((request) async {
          // Version verification succeeds; only content services are offline.
          if (request.url.path.endsWith('app_version.php')) {
            return http.Response(currentVersionResponse, 200);
          }
          return http.Response('{"error":{"message":"Unavailable"}}', 503);
        }),
      );
      await tester.pumpWidget(TosApp(api: api, auth: SignedInTestAuth(api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(find.text('Riprova'), findsOneWidget);
      await tester.tap(find.text('Account'));
      await tester.pumpAndSettle();
      expect(find.byType(AccountPage), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
