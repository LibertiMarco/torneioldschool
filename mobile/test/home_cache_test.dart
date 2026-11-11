import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/home_cache.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/api.dart';
import 'package:http/testing.dart';
import 'package:http/http.dart' as http;

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

void main() {
  test(
    'Concurrent reads share one request; failure preserves the last snapshot',
    () async {
      final cache = HomeCache();
      final pending = Completer<dynamic>();
      var calls = 0;
      Future<dynamic> load() {
        calls++;
        return pending.future;
      }

      final first = cache.refresh('next', load),
          second = cache.refresh('next', load);
      expect(identical(first, second), isTrue);
      pending.complete({'match': 1});
      await Future.wait([first, second]);
      expect(calls, 1);
      await expectLater(
        cache.refresh('next', () async => throw Exception('offline')),
        throwsException,
      );
      expect(cache.peek('next'), {'match': 1});
    },
  );

  test(
    'Persisted snapshots survive a new cache instance; expired data is ignored',
    () async {
      String? saved;
      final cache = HomeCache(
        write: (value) async {
          saved = value;
        },
      );
      await cache.refresh(
        'favorites',
        () async => {
          'items': [1],
        },
      );
      await Future<void>.delayed(Duration.zero);
      final reopened = HomeCache(read: () async => saved);
      await reopened.hydrate();
      expect(reopened.peek('favorites'), {
        'items': [1],
      });
      final old = jsonDecode(saved!) as Map<String, dynamic>;
      old['saved'] = DateTime.now()
          .subtract(const Duration(days: 2))
          .toIso8601String();
      final expired = HomeCache(read: () async => jsonEncode(old));
      await expired.hydrate();
      expect(expired.peek('favorites'), isNull);
    },
  );

  test(
    'Clearing an account rejects late writes and deletes disk data',
    () async {
      String? saved = 'old';
      final cache = HomeCache(
        write: (value) async {
          saved = value;
        },
        remove: () async {
          saved = null;
        },
      );
      final pending = Completer<dynamic>();
      final request = cache.refresh('private', () => pending.future);
      await cache.clear();
      pending.complete({'secret': 1});
      await request;
      expect(cache.peek('private'), isNull);
      expect(saved, isNull);
    },
  );

  test('Account caches are isolated and removed on real logout', () async {
    final storage = MemoryStore();
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test'),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/logout.php')) {
          return http.Response('{}', 200);
        }
        return http.Response(
          jsonEncode({
            'access_token': 'access',
            'refresh_token': 'refresh',
            'user': {'id': 1, 'email': 'first@example.test'},
          }),
          200,
        );
      }),
    );
    final auth = TosAuth(api, storage: storage);
    await auth.loginWithPassword('first@example.test', 'password', 'captcha');
    final first = auth.homeCache;
    await first.refresh('next', () async => {'match': 1});
    auth.user = {'id': 2, 'email': 'second@example.test'};
    expect(auth.homeCache.peek('next'), isNull);
    auth.user = {'id': 1, 'email': 'first@example.test'};
    expect(auth.homeCache.peek('next'), {'match': 1});
    await auth.logout();
    expect(first.peek('next'), isNull);
    expect(storage.values, isEmpty);
  });

  testWidgets(
    'Cached content stays visible while updating, offline, and on resume',
    (tester) async {
      final cache = HomeCache();
      await cache.refresh('ranking', () async => 'saved');
      var pending = Completer<dynamic>();
      var calls = 0;
      Future<dynamic> load() {
        calls++;
        return pending.future;
      }

      Widget page(int revision) => MaterialApp(
        home: CachedHomeData(
          cache: cache,
          cacheKey: 'ranking',
          load: load,
          revision: revision,
          builder: (_, snapshot) =>
              Text(snapshot.hasData ? '${snapshot.data}' : 'loading'),
        ),
      );
      await tester.pumpWidget(page(0));
      expect(find.text('saved'), findsOneWidget);
      await tester.pump();
      expect(find.text('loading'), findsNothing);
      pending.complete('fresh');
      await tester.pumpAndSettle();
      expect(find.text('fresh'), findsOneWidget);
      pending = Completer<dynamic>();
      await tester.pumpWidget(page(1));
      await tester.pump();
      expect(find.text('fresh'), findsOneWidget);
      pending.completeError(Exception('offline'));
      await tester.pumpAndSettle();
      expect(find.text('fresh'), findsOneWidget);
      expect(find.byType(Tooltip), findsOneWidget);
      pending = Completer<dynamic>();
      final before = calls;
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pump();
      expect(calls, before + 1);
      pending.complete('newer');
      await tester.pumpAndSettle();
      expect(find.text('newer'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );

  testWidgets('Changing cache identity hides old account data immediately', (
    tester,
  ) async {
    final first = HomeCache(), second = HomeCache();
    await first.refresh('next', () async => 'first account');
    final pending = Completer<dynamic>();
    Widget page(HomeCache cache) => MaterialApp(
      home: CachedHomeData(
        cache: cache,
        cacheKey: 'next',
        load: () => pending.future,
        builder: (_, snapshot) =>
            Text(snapshot.hasData ? '${snapshot.data}' : 'loading'),
      ),
    );
    await tester.pumpWidget(page(first));
    expect(find.text('first account'), findsOneWidget);
    await tester.pumpWidget(page(second));
    expect(find.text('first account'), findsNothing);
    pending.complete('second account');
    await tester.pumpAndSettle();
    expect(find.text('second account'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
  });
}
