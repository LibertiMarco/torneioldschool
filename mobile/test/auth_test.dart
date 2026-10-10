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
