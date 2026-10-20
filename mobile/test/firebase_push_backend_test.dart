import 'package:firebase_core_platform_interface/test.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/push.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const messaging = MethodChannel('plugins.flutter.io/firebase_messaging');
  const registration = MethodChannel('tos/push_registration');
  final calls = <String>[];
  var apnsReads = 0;
  var readyAfter = 0;
  String? appleError;
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

  setUp(() async {
    setupFirebaseCoreMocks();
    calls.clear();
    apnsReads = 0;
    readyAfter = 0;
    appleError = null;
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    messenger.setMockMethodCallHandler(registration, (call) async {
      calls.add(call.method);
      return call.method == 'registrationError' ? appleError : null;
    });
    messenger.setMockMethodCallHandler(messaging, (call) async {
      calls.add(call.method);
      switch (call.method) {
        case 'Messaging#setAutoInitEnabled':
          return {'isAutoInitEnabled': true};
        case 'Messaging#getAPNSToken':
          return {'token': ++apnsReads > readyAfter ? 'apple-token' : null};
        case 'Messaging#getToken':
          return {'token': 'firebase-token'};
        default:
          throw StateError('Unexpected method: ${call.method}');
      }
    });
    await FirebasePushBackend().initialize();
  });

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    messenger.setMockMethodCallHandler(registration, null);
    messenger.setMockMethodCallHandler(messaging, null);
  });

  test('iOS starts APNs and obtains FCM only after the Apple token', () async {
    expect(await FirebasePushBackend().token(), 'firebase-token');
    expect(calls.take(3), [
      'Messaging#setAutoInitEnabled',
      'register',
      'Messaging#getAPNSToken',
    ]);
    expect(calls.last, 'Messaging#getToken');
  });

  testWidgets('a delayed Apple token is awaited before requesting FCM', (
    tester,
  ) async {
    readyAfter = 3;
    final pending = FirebasePushBackend().token();
    await tester.pump();
    expect(calls, isNot(contains('Messaging#getToken')));
    for (var i = 0; i < 3; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    expect(await pending, 'firebase-token');
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('an absent APNs token never requests FCM and allows retry', (
    tester,
  ) async {
    readyAfter = 1000;
    final pending = expectLater(
      FirebasePushBackend().token(),
      throwsStateError,
    );
    await tester.pump();
    for (var i = 0; i < 60; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    await pending;
    expect(calls, isNot(contains('Messaging#getToken')));
    readyAfter = 0;
    expect(await FirebasePushBackend().token(), 'firebase-token');
    expect(calls.where((call) => call == 'register').length, 2);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('Apple registration errors are reported with the cause', (
    tester,
  ) async {
    readyAfter = 1000;
    appleError = 'no valid aps-environment entitlement';
    final pending = expectLater(
      FirebasePushBackend().token(),
      throwsA(
        isA<StateError>().having(
          (e) => e.message,
          'message',
          contains('aps-environment'),
        ),
      ),
    );
    await tester.pump();
    for (var i = 0; i < 60; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    await pending;
    expect(calls, isNot(contains('Messaging#getToken')));
    debugDefaultTargetPlatformOverride = null;
  });

  test('Android does not use the Apple registration channel', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    expect(await FirebasePushBackend().token(), 'firebase-token');
    expect(calls, ['Messaging#setAutoInitEnabled', 'Messaging#getToken']);
  });
}
