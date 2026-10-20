import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/update_gate.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('tos/app_version');

  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (_) async => 8);
  });
  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    testWidgets('$platform blocks on resume when a new release is available', (
      tester,
    ) async {
      debugDefaultTargetPlatformOverride = platform;
      var latest = 8;
      final api = TosApi(
        baseUrl: Uri.parse('https://example.com'),
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'android': {
                'build': platform == TargetPlatform.android ? latest : 1,
                'url': 'https://example.com/android',
              },
              'ios': {
                'build': platform == TargetPlatform.iOS ? latest : 1,
                'url': 'https://example.com/ios',
              },
            }),
            200,
          ),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: UpdateGate(api: api, child: const Text('Contenuto app')),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Contenuto app'), findsOneWidget);
      latest = 9;
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(find.text('Contenuto app'), findsNothing);
      expect(find.text('Aggiorna ora'), findsOneWidget);
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Aggiorna ora'), findsOneWidget);
      expect(find.text('Contenuto app'), findsNothing);
      await tester.pumpWidget(const SizedBox());
        debugDefaultTargetPlatformOverride = null;
    });
  }

  testWidgets(
    'Old build blocks existing routes and stays blocked on network failure',
    (tester) async {
      var latest = 8;
      var offline = false;
      final api = TosApi(
        baseUrl: Uri.parse('https://example.com'),
        client: MockClient((_) async {
          if (offline) throw Exception('offline');
          return http.Response(
            jsonEncode({
              'android': {'build': latest, 'url': 'https://example.com/update'},
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: UpdateGate(api: api, child: const Text('Contenuto app')),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Contenuto app'), findsOneWidget);
      latest = 9;
      await tester.pump(const Duration(minutes: 1));
      await tester.pumpAndSettle();
      expect(find.text('Contenuto app'), findsNothing);
      expect(find.text('Aggiorna ora'), findsOneWidget);
      offline = true;
      await tester.tap(find.text('Verifica di nuovo'));
      await tester.pumpAndSettle();
      expect(find.text('Contenuto app'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('Cannot enter app before first successful version check', (
    tester,
  ) async {
    final api = TosApi(
      baseUrl: Uri.parse('https://example.com'),
      client: MockClient((_) async => http.Response('unavailable', 503)),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: UpdateGate(api: api, child: const Text('Contenuto app')),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Contenuto app'), findsNothing);
    expect(find.text('Verifica di nuovo'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
}
