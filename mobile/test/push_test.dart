import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/push.dart';

class MemoryStore implements SessionStore {
  final values = <String, String>{};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

class PushAuth extends TosAuth {
  PushAuth()
    : super(
        TosApi(baseUrl: Uri.parse('https://torneioldschool.it')),
        storage: MemoryStore(),
      ) {
    user = {'id': 1};
  }
  final requests = <Map<String, dynamic>>[];
  bool configured = true;
  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) async {
    if (body == null) return {'configured': configured};
    requests.add(body);
    return {'success': true};
  }

  void signOut() {
    user = null;
    notifyListeners();
  }
}

class FakePush implements PushBackend {
  Completer<bool>? permission;
  final changes = StreamController<String>.broadcast(sync: true);
  bool granted = true;
  int initializes = 0, requests = 0, deleted = 0, tokens = 0;
  @override
  Future<void> initialize() async {
    initializes++;
  }

  @override
  Future<bool> allowed() async => granted;
  @override
  Future<bool> requestPermission() async {
    requests++;
    return permission == null ? granted : permission!.future;
  }

  @override
  Future<String?> token() async {
    tokens++;
    return 'device-token';
  }

  @override
  Future<void> deleteToken() async {
    deleted++;
  }

  @override
  Stream<String> get tokenChanges => changes.stream;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late PushAuth auth;
  late FakePush backend;
  late PushController push;
  setUp(() {
    auth = PushAuth();
    backend = FakePush();
    push = PushController(auth: auth, backend: backend, supported: true);
  });
  tearDown(() async {
    push.dispose();
    await backend.changes.close();
    auth.dispose();
  });

  test(
    'initial permission prompts once and subscribes after consent',
    () async {
      await push.start();
      await push.requestInitialPermission();
      await push.requestInitialPermission();
      expect(backend.requests, 1);
      expect(push.enabled, true);
      expect(
        auth.requests.where((r) => r['action'] == 'subscribe'),
        hasLength(1),
      );
    },
  );

  test('guest gets prompt without registering a device', () async {
    auth.signOut();
    await push.start();
    await push.requestInitialPermission();
    expect(backend.requests, 1);
    expect(backend.tokens, 0);
    expect(auth.requests, isEmpty);
  });

  test('initial denial is not prompted again', () async {
    backend.granted = false;
    await push.start();
    await push.requestInitialPermission();
    await push.requestInitialPermission();
    expect(backend.requests, 1);
    expect(push.enabled, false);
    expect(backend.tokens, 0);
  });

  test('manual disable survives restore after initial permission', () async {
    await push.start();
    await push.requestInitialPermission();
    await push.setEnabled(false);
    push.didChangeAppLifecycleState(AppLifecycleState.resumed);
    await Future<void>.delayed(Duration.zero);
    expect(push.enabled, false);
    expect(
      auth.requests.where((r) => r['action'] == 'subscribe'),
      hasLength(1),
    );
  });

  test(
    'startup asks no permission and registers no device without opt-in',
    () async {
      await push.start();
      expect(backend.requests, 0);
      expect(backend.tokens, 0);
      expect(auth.requests, isEmpty);
    },
  );
  test(
    'missing server configuration does not prompt Android permission',
    () async {
      auth.configured = false;
      await push.start();
      await push.setEnabled(true);
      expect(push.enabled, false);
      expect(backend.requests, 0);
      expect(auth.requests, isEmpty);
      expect(push.message, contains('preparazione'));
    },
  );
  test('denying permission never registers a token', () async {
    backend.granted = false;
    await push.start();
    await push.setEnabled(true);
    expect(push.enabled, false);
    expect(backend.tokens, 0);
    expect(auth.requests, isEmpty);
  });
  test(
    'enable, own-device test and disable revoke server and Firebase token',
    () async {
      await push.start();
      await push.setEnabled(true);
      expect(push.enabled, true);
      await push.sendTest();
      await push.setEnabled(false);
      expect(auth.requests.map((r) => r['action']), [
        'subscribe',
        'test',
        'unsubscribe',
      ]);
      expect(auth.requests.every((r) => r['token'] == 'device-token'), true);
      expect(backend.deleted, 1);
      expect(push.enabled, false);
      expect((auth.storage as MemoryStore).values, {
        'tos.push.https://torneioldschool.it.1': '0',
      });
    },
  );
  test(
    'token rotation replaces registration and removes previous token',
    () async {
      await push.start();
      await push.setEnabled(true);
      backend.changes.add('rotated-token');
      await Future<void>.delayed(Duration.zero);
      expect(auth.requests.last, {
        'action': 'unsubscribe',
        'token': 'device-token',
      });
      expect(auth.requests[1], {
        'action': 'subscribe',
        'token': 'rotated-token',
      });
    },
  );
  test('logout stops token refresh and foreground eligibility', () async {
    await push.start();
    await push.setEnabled(true);
    auth.signOut();
    backend.changes.add('rotated-token');
    await Future<void>.delayed(Duration.zero);
    expect(push.enabled, false);
    expect(auth.requests.length, 1);
  });
  test(
    'logout during permission dialog cannot opt in the next account',
    () async {
      backend.permission = Completer<bool>();
      await push.start();
      final enabling = push.setEnabled(true);
      await Future<void>.delayed(Duration.zero);
      auth.signOut();
      backend.permission!.complete(true);
      await enabling;
      expect(push.enabled, false);
      expect(auth.requests, isEmpty);
      expect((auth.storage as MemoryStore).values, isEmpty);
    },
  );
  test('unsupported platforms never initialize Firebase', () async {
    final unsupported = PushController(
      auth: auth,
      backend: backend,
      supported: false,
    );
    await unsupported.start();
    expect(backend.initializes, 0);
    expect(unsupported.ready, false);
    unsupported.dispose();
  });
  test(
    'Android permission revoked while away removes the registered device',
    () async {
      await push.start();
      await push.setEnabled(true);
      backend.granted = false;
      push.didChangeAppLifecycleState(AppLifecycleState.resumed);
      await Future<void>.delayed(Duration.zero);
      expect(push.enabled, false);
      expect(backend.deleted, 1);
      expect(auth.requests.last['action'], 'unsubscribe');
    },
  );
  test('notification links reject foreign origins and non-HTTPS URLs', () {
    for (final url in [
      'http://torneioldschool.it',
      'https://evil.test',
      'https://torneioldschool.it.evil.test',
      'https://user@torneioldschool.it',
      'https://torneioldschool.it:444/',
      'javascript:alert(1)',
      '//evil.test',
    ]) {
      expect(pushLink(url), isNull, reason: url);
    }
    expect(
      pushLink('https://torneioldschool.it/articolo.php?id=12')?.query,
      'id=12',
    );
  });
}
