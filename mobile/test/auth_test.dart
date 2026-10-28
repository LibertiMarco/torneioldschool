import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';

class MemorySessionStore implements SessionStore {
  String? value;
  @override
  Future<String?> read(String key) async => value;
  @override
  Future<void> write(String key, String next) async {
    value = next;
  }

  @override
  Future<void> delete(String key) async {
    value = null;
  }
}

const testUser = {
  'id': 1,
  'nome': 'Test',
  'cognome': 'User',
  'email': 'fixture@example.test',
  'ruolo': 'admin',
  'permissions': {'admin': true, 'graphics': true},
};

void main() {
  test(
    'Account deletion uses bearer and clears local session only after success',
    () async {
      final store = MemorySessionStore()
        ..value = jsonEncode({'access': 'access', 'refresh': 'refresh'});
      var fail = true;
      final auth = TosAuth(
        TosApi(
          baseUrl: Uri.parse('https://example.test'),
          client: MockClient((request) async {
            if (request.url.path.endsWith('/account_delete.php')) {
              expect(request.headers['Authorization'], 'Bearer access');
              expect(jsonDecode(request.body), {
                'password': ' exact password ',
                'confirm_delete': true,
              });
              return fail
                  ? http.Response(
                      '{"error":{"message":"Password non corretta."}}',
                      403,
                    )
                  : http.Response('{"success":true}', 200);
            }
            return http.Response(jsonEncode({'user': testUser}), 200);
          }),
        ),
        storage: store,
      );
      await auth.restore();
      await expectLater(
        auth.deleteAccount(' exact password '),
        throwsA(isA<ApiError>()),
      );
      expect(store.value, isNotNull);
      expect(auth.user, isNotNull);
      fail = false;
      await auth.deleteAccount(' exact password ');
      expect(store.value, isNull);
      expect(auth.user, isNull);
    },
  );
  test('Content reads use the session, refresh expired tokens and detach on logout', () async {
    final store = MemorySessionStore()
      ..value = jsonEncode({'access': 'old', 'refresh': 'refresh'});
    final contentHeaders = <String?>[];
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/token.php')) {
          expect(jsonDecode(request.body)['grant_type'], 'refresh_token');
          return http.Response(
            jsonEncode({
              'access_token': 'new',
              'refresh_token': 'new-refresh',
              'user': testUser,
            }),
            200,
          );
        }
        if (request.url.path.endsWith('/me.php')) {
          expect(request.headers['Authorization'], 'Bearer old');
          return http.Response(jsonEncode({'user': testUser}), 200);
        }
        if (request.url.path.endsWith('/logout.php')) {
          expect(request.headers['Authorization'], 'Bearer new');
          return http.Response('{}', 200);
        }
        contentHeaders.add(request.headers['Authorization']);
        if (request.headers['Authorization'] == 'Bearer old') {
          return http.Response('Expired', 401);
        }
        expect(request.url.queryParameters['torneo'], 'Cup');
        return http.Response('[]', 200);
      }),
    );
    final auth = TosAuth(api, storage: store);
    await api.standings('Cup');
    await auth.restore();
    await api.standings('Cup');
    await auth.logout();
    await api.standings('Cup');
    expect(contentHeaders, [null, 'Bearer old', 'Bearer new', null]);
  });
  test('Staff controller headers use the refreshed database role and keep bearer out of URLs', () async {
    final store = MemorySessionStore()
      ..value = jsonEncode({'access': 'old', 'refresh': 'refresh'});
    var permissions = {'admin': true, 'graphics': true};
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((request) async {
        expect(request.url.queryParameters.containsKey('access_token'), false);
        expect(request.headers['Authorization'], 'Bearer old');
        return http.Response(
          jsonEncode({
            'user': {...testUser, 'permissions': permissions},
          }),
          200,
        );
      }),
    );
    final auth = TosAuth(api, storage: store);
    await auth.restore();
    expect(await auth.staffSessionHeaders(), {'Authorization': 'Bearer old'});
    permissions = {'admin': false, 'graphics': false};
    await expectLater(
      auth.staffSessionHeaders(),
      throwsA(isA<ApiError>().having((error) => error.status, 'status', 403)),
    );
    expect(auth.admin, false);
    expect(auth.graphics, false);
  });
  test('Concurrent expired requests rotate the refresh only once', () async {
    final store = MemorySessionStore()
      ..value = jsonEncode({'access': 'old-access', 'refresh': 'old-refresh'});
    final releaseRefresh = Completer<void>();
    int refreshCount = 0;
    int expiredCount = 0;
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/token.php')) {
        refreshCount++;
        expect(jsonDecode(request.body)['refresh_token'], 'old-refresh');
        await releaseRefresh.future;
        return http.Response(
          jsonEncode({
            'access_token': 'new-access',
            'refresh_token': 'new-refresh',
            'user': testUser,
          }),
          200,
        );
      }
      if (request.headers['Authorization'] == 'Bearer old-access') {
        expiredCount++;
        if (expiredCount == 2) releaseRefresh.complete();
        return http.Response('{"error":{"message":"Expired"}}', 401);
      }
      return http.Response(jsonEncode({'user': testUser}), 200);
    });
    final auth = TosAuth(
      TosApi(baseUrl: Uri.parse('https://example.test'), client: client),
      storage: store,
    );
    // Two restore calls model simultaneous authenticated requests during startup.
    await Future.wait([auth.restore(), auth.restore()]);
    expect(refreshCount, 1);
    expect(auth.admin, isTrue);
    expect(jsonDecode(store.value!)['refresh'], 'new-refresh');
  });
  test('Revoked sessions clear saved credentials while network outages preserve them', () async {
    final store = MemorySessionStore()
      ..value = jsonEncode({'access': 'old', 'refresh': 'refresh'});
    final revoked = TosAuth(
      TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient(
          (_) async => http.Response('{"error":{"message":"Revoked"}}', 401),
        ),
      ),
      storage: store,
    );
    await revoked.restore();
    expect(store.value, isNull);
    expect(revoked.user, isNull);
    store.value = jsonEncode({'access': 'old', 'refresh': 'refresh'});
    final offline = TosAuth(
      TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient(
          (_) async =>
              http.Response('{"error":{"message":"Unavailable"}}', 503),
        ),
      ),
      storage: store,
    );
    await offline.restore();
    expect(store.value, isNotNull);
    expect(offline.user, isNull);
  });
  test('Logout revokes before deleting local credentials', () async {
    final store = MemorySessionStore()
      ..value = jsonEncode({'access': 'old', 'refresh': 'refresh'});
    bool revoked = false;
    final auth = TosAuth(
      TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/logout.php')) {
            expect(request.method, 'POST');
            expect(store.value, isNotNull);
            revoked = true;
            return http.Response('{"success":true}', 200);
          }
          return http.Response(jsonEncode({'user': testUser}), 200);
        }),
      ),
      storage: store,
    );
    await auth.restore();
    await auth.logout();
    expect(revoked, isTrue);
    expect(store.value, isNull);
    expect(auth.user, isNull);
  });
}
