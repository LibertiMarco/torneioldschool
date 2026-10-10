import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/browser_auth.dart';

void main() {
  test(
    'Real preview uses same origin and attaches session CSRF to writes',
    () async {
      final requests = <http.Request>[];
      final api = BrowserApi(
        baseUrl: Uri.parse('https://torneioldschool.it'),
        client: MockClient((request) async {
          requests.add(request);
          if (request.url.queryParameters['action'] == 'session') {
            return http.Response(
              jsonEncode({
                'user': {
                  'id': 1,
                  'permissions': {'admin': true},
                },
                'csrf': 'session-token',
              }),
              200,
            );
          }
          return http.Response('{}', 200);
        }),
      );
      final auth = BrowserAuth(api, navigate: (_) {});
      await auth.restore();
      expect(auth.admin, true);
      await auth.authorized(
        'api/mobile/v1/admin/user_features.php',
        body: {
          'id': 2,
          'revision': 'abc',
          'feature_flags': {'totocalcio': true, 'fantacalcio': false},
        },
      );
      expect(
        requests.last.url.toString(),
        'https://torneioldschool.it/app-preview/api.php?action=features',
      );
      expect(jsonDecode(requests.last.body)['_csrf'], 'session-token');
      expect(requests.last.headers.containsKey('Authorization'), false);
      await auth.authorized(
        'api/mobile/v1/account.php',
        body: {'nome': 'Marco', 'cognome': 'Demo', 'consenso_newsletter': 0},
        uploads: [
          ApiUpload(
            field: 'avatar',
            filename: 'profile.png',
            bytes: Uint8List.fromList([1, 2, 3]),
          ),
        ],
      );
      expect(requests.last.url.queryParameters['action'], 'account');
      expect(
        requests.last.headers['content-type'],
        startsWith('multipart/form-data;'),
      );
      expect(requests.last.body, contains('name="_csrf"'));
      expect(requests.last.body, contains('session-token'));
      expect(requests.last.body, contains('filename="profile.png"'));
      expect(() => auth.authorized('api/delete.php'), throwsA(isA<ApiError>()));
    },
  );

  test('Denied current role clears browser permissions and blocks subsequent writes', () async {
    var deny = false;
    final api = BrowserApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((_) async {
        if (deny) {
          return http.Response(
            '{"error":{"message":"Accesso revocato."}}',
            403,
          );
        }
        return http.Response(
          '{"user":{"permissions":{"admin":true}},"csrf":"token"}',
          200,
        );
      }),
    );
    final auth = BrowserAuth(api, navigate: (_) {});
    await auth.restore();
    deny = true;
    await expectLater(
      auth.authorized('api/mobile/v1/admin/users.php'),
      throwsA(isA<ApiError>()),
    );
    expect(auth.admin, false);
    expect(auth.error, 'Accesso revocato.');
    await expectLater(
      auth.authorized('api/mobile/v1/admin/user_features.php', body: {}),
      throwsA(isA<ApiError>()),
    );
  });
}
