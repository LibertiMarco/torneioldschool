import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/comments_section.dart';

import 'auth_test.dart' show MemorySessionStore, testUser;

void main() {
  for (final signedIn in [false, true]) {
    testWidgets('comments read, paginate and respect sign-in=$signedIn', (
      tester,
    ) async {
      final writes = <Map>[];
      var fail = false;
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient((request) async {
          if (request.url.path.endsWith('login.php')) {
            return http.Response(
              jsonEncode({
                'access_token': 'access',
                'refresh_token': 'refresh',
                'user': testUser,
              }),
              200,
            );
          }
          if (request.method == 'POST') {
            writes.add(jsonDecode(request.body) as Map);
            expect(request.headers['Authorization'], 'Bearer access');
            if (fail) {
              return http.Response(
                '{"error":"Troppi commenti. Attendi un minuto."}',
                429,
              );
            }
            return http.Response('{"success":true}', 200);
          }
          final page = int.parse(request.url.queryParameters['page'] ?? '1');
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': page,
                  'autore': 'Autore reale',
                  'commento': 'Commento $page',
                  'data': '17/10/2026 12:00',
                  'can_delete': signedIn,
                },
              ],
              'pagination': {'page': page, 'has_more': page == 1},
            }),
            200,
          );
        }),
      );
      final auth = TosAuth(api, storage: MemorySessionStore());
      if (signedIn) {
        await auth.loginWithPassword('test@example.test', 'secret', 'captcha');
      }
      await tester.pumpWidget(
        MaterialApp(
          home: AppAuthScope(
            auth: auth,
            child: Scaffold(
              body: SingleChildScrollView(
                child: CommentsSection(api: api, postId: '5'),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Commento 1'), findsOneWidget);
      await tester.tap(find.byTooltip('Pagina successiva'));
      await tester.pumpAndSettle();
      expect(find.text('Commento 2'), findsOneWidget);
      if (!signedIn) {
        expect(find.text('Accedi per commentare'), findsOneWidget);
        expect(find.byType(TextField), findsNothing);
        expect(find.byTooltip('Elimina commento'), findsNothing);
      } else {
        await tester.enterText(find.byType(TextField), 'Il mio commento');
        fail = true;
        await tester.tap(find.text('Pubblica commento'));
        await tester.pumpAndSettle();
        expect(
          find.text('Troppi commenti. Attendi un minuto.'),
          findsOneWidget,
        );
        expect(
          tester.widget<TextField>(find.byType(TextField)).controller!.text,
          'Il mio commento',
        );
        fail = false;
        await tester.tap(find.text('Pubblica commento'));
        await tester.pumpAndSettle();
        expect(writes.last['post_id'], 5);
        expect(writes.last['commento'], 'Il mio commento');
        expect(
          tester.widget<TextField>(find.byType(TextField)).controller!.text,
          isEmpty,
        );
        await tester.tap(find.byTooltip('Elimina commento'));
        await tester.pumpAndSettle();
        final before = writes.length;
        await tester.tap(find.text('Annulla'));
        await tester.pumpAndSettle();
        expect(writes.length, before, reason: 'Cancel must never delete');
      }
      expect(tester.takeException(), isNull);
    });
  }
}
