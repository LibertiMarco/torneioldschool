import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';

void main() {
  test('All-time homepage preserves ranks without a tournament filter; section reads are explicit', () async {
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((request) async {
        if (request.url.path.endsWith('classifica_giocatori.php')) {
          expect(request.url.queryParameters.containsKey('torneo'), false);
          expect(request.url.queryParameters['ordine'], 'presenze');
          expect(request.url.queryParameters['per_page'], '5');
          return http.Response(
            jsonEncode({
              'data': [
                {'gol': 150, 'presenze': 200, 'posizione': 3},
              ],
            }),
            200,
          );
        }
        expect(request.url.queryParameters['sezione'], 'esport');
        if (request.url.path.endsWith('blog.php')) {
          expect(request.url.queryParameters['azione'], 'ultimi');
          return http.Response('[]', 200);
        }
        return http.Response('{"data":[]}', 200);
      }),
    );
    final ranking = await api.playerRanking(order: 'presenze');
    expect(TosApi.rows(ranking['data']).single['posizione'], 3);
    await api.news('esport');
    await api.hallOfFame('esport');
  });
  test('S256 matches RFC 7636 vector', () {
    expect(
      TosAuth.challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
      'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    );
  });
  test(
    'Preserves deployment subdirectory and encodes tournament parameters',
    () async {
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test/torneioldschool/'),
        client: MockClient((request) async {
          expect(request.url.path, '/torneioldschool/api/leggiClassifica.php');
          expect(request.url.queryParameters['torneo'], 'Cup A&B');
          return http.Response(
            jsonEncode([
              {'nome': 'Team'},
            ]),
            200,
          );
        }),
      );
      expect((await api.standings('Cup A&B')).single['nome'], 'Team');
    },
  );
  test('Handles matchday objects and arrays, plus single-match payloads', () {
    expect(
      TosApi.flattenMatches({
        '1': [
          {'id': 1},
        ],
        '3': [
          {'id': 2},
        ],
      }).length,
      2,
    );
    expect(
      TosApi.flattenMatches([
        [
          {'id': 1},
        ],
        [
          {'id': 2},
        ],
      ]).length,
      2,
    );
    expect(
      TosApi.flattenMatches([
        {'id': 1},
      ]).single['id'],
      1,
    );
  });
  test('Does not surface server internals or interpret HTML as data', () async {
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient(
        (_) async => http.Response('{"error":"SELECT secret FROM users"}', 500),
      ),
    );
    await expectLater(
      api.standings('test'),
      throwsA(
        isA<ApiError>().having(
          (error) => error.message,
          'safe message',
          'Servizio temporaneamente non disponibile.',
        ),
      ),
    );
    final html = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((_) async => http.Response('<html>Login</html>', 200)),
    );
    await expectLater(html.standings('test'), throwsA(isA<ApiError>()));
  });
}
