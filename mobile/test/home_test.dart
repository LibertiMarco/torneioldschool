import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';

import 'auth_test.dart' show MemorySessionStore;

void main() {
  testWidgets('Browse tournaments and match scores; guest has no admin tools', (
    tester,
  ) async {
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((request) async {
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
    await tester.pumpWidget(
      TosApp(
        api: api,
        auth: TosAuth(api, storage: MemorySessionStore()),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tornei'));
    await tester.pumpAndSettle();
    expect(find.text('Cup'), findsOneWidget);
    expect(find.text('Gestione'), findsNothing);
    await tester.tap(find.text('Cup'));
    await tester.pumpAndSettle();
    expect(find.text('Team A'), findsOneWidget);
    await tester.tap(find.text('Calendario'));
    await tester.pumpAndSettle();
    expect(find.text('2 : 0'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'Service outage offers retry and leaves account navigation usable',
    (tester) async {
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient(
          (_) async =>
              http.Response('{"error":{"message":"Unavailable"}}', 503),
        ),
      );
      await tester.pumpWidget(
        TosApp(
          api: api,
          auth: TosAuth(api, storage: MemorySessionStore()),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(find.text('Riprova'), findsOneWidget);
      await tester.tap(find.text('Account'));
      await tester.pumpAndSettle();
      expect(find.widgetWithText(FilledButton, 'Accedi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
