import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/src/ad_instance_manager.dart';
import 'package:tornei_old_school/ads.dart';
import 'package:tornei_old_school/native_ad_card.dart';

class PrivacyFake implements AdsPrivacyBackend {
  bool allowed = false, required = true, fail = false;
  int updates = 0, initializations = 0;
  final events = <String>[];
  @override
  Future<void> update() async {
    updates++;
    events.add('update');
    if (fail) throw StateError('offline');
  }

  @override
  Future<void> collect() async {
    events.add('collect');
  }

  @override
  Future<void> options() async {
    allowed = false;
  }

  @override
  Future<bool> canRequest() async => allowed;
  @override
  Future<bool> optionsRequired() async => required;
  @override
  Future<void> initialize() async {
    initializations++;
    events.add('initialize');
  }
}

void main() {
  testWidgets(
    'no consent means no banner requests or empty advertising space',
    (tester) async {
      final controller = AdsController(enabled: true, backend: PrivacyFake());
      await controller.start();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                AdaptiveAdBanner(controller: controller),
                HomeNativeAdCard(controller: controller),
              ],
            ),
          ),
        ),
      );
      expect(find.text('Pubblicità'), findsNothing);
      expect(find.byType(SafeArea), findsNothing);
      await tester.pumpWidget(const SizedBox());
      controller.dispose();
    },
  );

  for (final native in [false, true]) {
    testWidgets(
      '${native ? 'native' : 'banner'}: covered routes dispose the ad; return reloads; revocation disposes',
      (tester) async {
        final controller = AdsController(
          enabled: true,
          backend: PrivacyFake()..allowed = true,
        );
        await controller.start();
        final calls = <String>[];
        tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          instanceManager.channel,
          (call) async {
            calls.add(call.method);
            return null;
          },
        );
        addTearDown(
          () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
            instanceManager.channel,
            null,
          ),
        );
        final navigator = GlobalKey<NavigatorState>();
        await tester.pumpWidget(
          MaterialApp(
            navigatorKey: navigator,
            home: Scaffold(
              body: native
                  ? HomeNativeAdCard(controller: controller)
                  : AdaptiveAdBanner(controller: controller),
            ),
          ),
        );
        await tester.pumpAndSettle();
        final loadMethod = native ? 'loadNativeAd' : 'loadBannerAd';
        expect(calls.where((c) => c == loadMethod).length, 1);
        navigator.currentState!.push(
          MaterialPageRoute<void>(
            builder: (_) => const Scaffold(body: Text('Dettaglio')),
          ),
        );
        await tester.pumpAndSettle();
        expect(calls.where((c) => c == 'disposeAd').length, 1);
        expect(calls.where((c) => c == loadMethod).length, 1);
        navigator.currentState!.pop();
        await tester.pumpAndSettle();
        expect(calls.where((c) => c == loadMethod).length, 2);
        await controller.showPrivacyOptions();
        await tester.pumpAndSettle();
        expect(calls.where((c) => c == 'disposeAd').length, 2);
        await tester.pumpWidget(const SizedBox());
        await tester.pumpAndSettle();
        controller.dispose();
      },
    );
  }

  testWidgets(
    'native template does not request ads when available width is below 320',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final controller = AdsController(
        enabled: true,
        backend: PrivacyFake()..allowed = true,
      );
      await controller.start();
      final calls = <String>[];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        instanceManager.channel,
        (call) async {
          calls.add(call.method);
          return null;
        },
      );
      addTearDown(
        () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          instanceManager.channel,
          null,
        ),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Padding(
              padding: const EdgeInsets.all(20),
              child: HomeNativeAdCard(controller: controller),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(calls, isEmpty);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      controller.dispose();
    },
  );

  test('real ID requires release AND explicit monetization', () {
    for (final release in [false, true]) {
      for (final monetization in [false, true]) {
        expect(
          androidBannerId(release: release, monetization: monetization),
          release && monetization
              ? 'ca-app-pub-8390787841690316/9634821513'
              : 'ca-app-pub-3940256099942544/9214589741',
        );
        expect(
          androidNativeId(release: release, monetization: monetization),
          release && monetization
              ? 'ca-app-pub-8390787841690316/2123997150'
              : 'ca-app-pub-3940256099942544/2247696110',
        );
      }
    }
  });
  test('unsupported or disabled platforms never invoke native SDK', () async {
    final backend = PrivacyFake();
    final ads = AdsController(enabled: false, backend: backend);
    await ads.start();
    await ads.showPrivacyOptions();
    expect(backend.events, isEmpty);
    expect(ads.ready, isFalse);
    ads.dispose();
  });
  test('consent blocks SDK initialization and banners', () async {
    final backend = PrivacyFake();
    final ads = AdsController(enabled: true, backend: backend);
    await ads.start();
    expect(ads.ready, isFalse);
    expect(ads.privacyRequired, isTrue);
    expect(backend.initializations, 0);
    ads.dispose();
  });
  test(
    'initializes once after consent; revocation removes permission',
    () async {
      final backend = PrivacyFake()..allowed = true;
      final ads = AdsController(enabled: true, backend: backend);
      await ads.start();
      await ads.start();
      expect(backend.events, ['update', 'collect', 'initialize']);
      expect(backend.updates, 1);
      expect(ads.ready, isTrue);
      final states = <bool>[];
      ads.addListener(() => states.add(ads.ready));
      await ads.showPrivacyOptions();
      expect(states, [false, false]);
      expect(ads.ready, isFalse);
      expect(backend.initializations, 1);
      ads.dispose();
    },
  );
  test('offline first launch fails closed without blocking app', () async {
    final backend = PrivacyFake()..fail = true;
    final ads = AdsController(enabled: true, backend: backend);
    await ads.start();
    expect(ads.ready, isFalse);
    expect(ads.busy, isFalse);
    expect(ads.error, isNotNull);
    expect(backend.initializations, 0);
    ads.dispose();
  });
  test(
    'UMP may allow previously collected consent when update fails',
    () async {
      final backend = PrivacyFake()
        ..fail = true
        ..allowed = true;
      final ads = AdsController(enabled: true, backend: backend);
      await ads.start();
      expect(ads.ready, isTrue);
      expect(backend.initializations, 1);
      ads.dispose();
    },
  );
}
