import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:google_mobile_ads/src/ad_instance_manager.dart';
import 'package:tornei_old_school/ads.dart';
import 'package:tornei_old_school/native_ad_card.dart';
import 'package:tornei_old_school/lineup_ad_slot.dart';

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
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    for (final native in [false, true]) {
      testWidgets(
        'failed $native ad retries with backoff on $platform and stops on revocation',
        (tester) async {
          final backend = PrivacyFake()..allowed = true;
          final controller = AdsController(
            enabled: true,
            backend: backend,
            authorizeTracking: () async => platform != TargetPlatform.iOS,
          );
          await controller.start();
          final ids = <int>[];
          tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
            instanceManager.channel,
            (call) async {
              if (call.method == (native ? 'loadNativeAd' : 'loadBannerAd')) {
                ids.add((call.arguments as Map)['adId'] as int);
              }
              return null;
            },
          );
          addTearDown(
            () => tester.binding.defaultBinaryMessenger
                .setMockMethodCallHandler(instanceManager.channel, null),
          );
          await tester.pumpWidget(
            MaterialApp(
              home: Scaffold(
                body: native
                    ? HomeNativeAdCard(controller: controller)
                    : AdaptiveAdBanner(controller: controller),
              ),
            ),
          );
          await tester.pumpAndSettle();
          void fail() {
            final ad = instanceManager.adFor(ids.last)!;
            final error = LoadAdError(2, 'test', 'offline', null);
            if (ad is NativeAd) {
              ad.listener.onAdFailedToLoad!(ad, error);
            } else if (ad is BannerAd) {
              ad.listener.onAdFailedToLoad!(ad, error);
            }
          }

          expect(ids.length, 1);
          for (final delay in [5, 15, 45]) {
            fail();
            await tester.pump(Duration(seconds: delay - 1));
            final before = ids.length;
            await tester.pump(const Duration(seconds: 1));
            expect(ids.length, before + 1);
          }
          fail();
          await tester.pump(const Duration(minutes: 2));
          expect(ids.length, 4, reason: 'Retries must be bounded');
          // Recreate the slot, then revoke while a retry is still pending.
          await tester.pumpWidget(const SizedBox());
          await tester.pumpWidget(
            MaterialApp(
              home: Scaffold(
                body: native
                    ? HomeNativeAdCard(controller: controller)
                    : AdaptiveAdBanner(controller: controller),
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(ids.length, 5);
          fail();
          await controller.showPrivacyOptions();
          await tester.pumpAndSettle();
          await tester.pump(const Duration(minutes: 2));
          expect(ids.length, 5);
          await tester.pumpWidget(const SizedBox());
          controller.dispose();
        },
        variant: TargetPlatformVariant({platform}),
      );
    }
  }
  test('offline consent is fetched again on resume', () async {
    final backend = PrivacyFake()..fail = true;
    final controller = AdsController(enabled: true, backend: backend);
    await controller.start();
    expect(controller.ready, isFalse);
    backend
      ..fail = false
      ..allowed = true;
    controller.didChangeAppLifecycleState(AppLifecycleState.resumed);
    await Future<void>.delayed(Duration.zero);
    expect(backend.updates, 2);
    expect(controller.ready, isTrue);
    controller.dispose();
  });
  for (final width in [360.0, 280.0]) {
    testWidgets(
      'Lineup ad at $width pixels survives score rebuilds and respects consent',
      (tester) async {
        final controller = AdsController(
          enabled: true,
          backend: PrivacyFake()..allowed = true,
        );
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
        Widget page(int score, {bool preview = false}) => MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Text('$score'),
                SizedBox(
                  width: width,
                  child: LineupAdSlot(
                    key: const ValueKey('between-teams'),
                    controller: controller,
                    preview: preview,
                  ),
                ),
              ],
            ),
          ),
        );
        await tester.pumpWidget(page(0));
        await tester.pumpAndSettle();
        expect(calls, isEmpty, reason: 'No request before consent');
        await controller.start();
        await tester.pumpAndSettle();
        final load = width >= 320 ? 'loadNativeAd' : 'loadBannerAd';
        expect(calls.where((method) => method == load).length, 1);
        await tester.pumpWidget(page(1));
        await tester.pumpAndSettle();
        expect(
          calls.where((method) => method == load).length,
          1,
          reason: 'Editing a score must not reload an ad',
        );
        await controller.showPrivacyOptions();
        await tester.pumpAndSettle();
        expect(calls.where((method) => method == 'disposeAd').length, 1);
        calls.clear();
        await tester.pumpWidget(page(2, preview: true));
        await tester.pumpAndSettle();
        expect(find.text('Anteprima annuncio'), findsOneWidget);
        expect(
          calls,
          isEmpty,
          reason: 'Web preview never requests a mobile ad',
        );
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
        controller.dispose();
      },
    );
  }
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('tos/tracking'),
          (_) async => true,
        );
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(const MethodChannel('tos/tracking'), null);
  });

  test('ATT refusal allows ads when UMP allows requests', () async {
    final backend = PrivacyFake()..allowed = true;
    final ads = AdsController(
      enabled: true,
      backend: backend,
      authorizeTracking: () async => false,
    );
    await ads.start();
    expect(backend.events, ['update', 'collect', 'initialize']);
    expect(backend.initializations, 1);
    expect(ads.ready, isTrue);
    expect(ads.busy, isFalse);
    ads.dispose();
  });

  test('ATT completes before initialization; ATT revocation allows ads, UMP revocation stops them', () async {
    final backend = PrivacyFake()..allowed = true;
    var authorized = true;
    final ads = AdsController(
      enabled: true,
      backend: backend,
      authorizeTracking: () async {
        backend.events.add('att');
        return authorized;
      },
    );
    await ads.start();
    expect(backend.events, ['update', 'collect', 'att', 'initialize']);
    ads.didChangeAppLifecycleState(AppLifecycleState.inactive);
    expect(ads.ready, isFalse);
    authorized = false;
    ads.didChangeAppLifecycleState(AppLifecycleState.resumed);
    await Future<void>.delayed(Duration.zero);
    expect(ads.ready, isTrue);
    expect(backend.initializations, 1);
    await ads.showPrivacyOptions();
    expect(ads.ready, isFalse);
    ads.dispose();
  });

  test(
    'UMP blocks ads and ATT prompt regardless of tracking permission',
    () async {
      for (final authorized in [false, true]) {
        final backend = PrivacyFake();
        var prompts = 0;
        final ads = AdsController(
          enabled: true,
          backend: backend,
          authorizeTracking: () async {
            prompts++;
            return authorized;
          },
        );
        await ads.start();
        expect(ads.ready, isFalse);
        expect(prompts, 0);
        expect(backend.initializations, 0);
        ads.dispose();
      }
    },
  );

  test('ATT channel failure does not initialize the SDK', () async {
    final backend = PrivacyFake()..allowed = true;
    final ads = AdsController(
      enabled: true,
      backend: backend,
      authorizeTracking: () async => throw StateError('channel unavailable'),
    );
    await ads.start();
    expect(ads.ready, isFalse);
    expect(backend.initializations, 0);
    ads.dispose();
  });

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

  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    for (final native in [false, true]) {
      testWidgets(
        '$platform ${native ? 'native' : 'banner'}: covered routes dispose the ad; return reloads; revocation disposes',
        (tester) async {
          final controller = AdsController(
            enabled: true,
            backend: PrivacyFake()..allowed = true,
          );
          await controller.start();
          final calls = <String>[];
          final requestedIds = <String>[];
          tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
            instanceManager.channel,
            (call) async {
              calls.add(call.method);
              if (call.method == 'loadNativeAd' ||
                  call.method == 'loadBannerAd') {
                requestedIds.add((call.arguments as Map)['adUnitId'] as String);
              }
              return null;
            },
          );
          addTearDown(
            () => tester.binding.defaultBinaryMessenger
                .setMockMethodCallHandler(instanceManager.channel, null),
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
          expect(
            requestedIds.single,
            platform == TargetPlatform.iOS
                ? (native
                      ? 'ca-app-pub-3940256099942544/3986624511'
                      : 'ca-app-pub-3940256099942544/2435281174')
                : (native
                      ? 'ca-app-pub-3940256099942544/2247696110'
                      : 'ca-app-pub-3940256099942544/9214589741'),
          );
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
        variant: TargetPlatformVariant({platform}),
      );
    }
  }

  test('web and desktop never enable mobile ads; iOS never uses Android production IDs', () {
    for (final platform in TargetPlatform.values) {
      expect(supportsMobileAds(web: true, platform: platform), isFalse);
      final mobile =
          platform == TargetPlatform.android || platform == TargetPlatform.iOS;
      expect(supportsMobileAds(web: false, platform: platform), mobile);
      if (!mobile) {
        expect(
          bannerIdForPlatform(platform, release: true, monetization: true),
          isNull,
        );
        expect(
          nativeIdForPlatform(platform, release: true, monetization: true),
          isNull,
        );
      }
    }
    for (final release in [false, true]) {
      for (final monetization in [false, true]) {
        expect(
          bannerIdForPlatform(
            TargetPlatform.iOS,
            release: release,
            monetization: monetization,
          ),
          release && monetization
              ? iosProductionAdIds.banner
              : 'ca-app-pub-3940256099942544/2435281174',
        );
        expect(
          nativeIdForPlatform(
            TargetPlatform.iOS,
            release: release,
            monetization: monetization,
          ),
          release && monetization
              ? iosProductionAdIds.native
              : 'ca-app-pub-3940256099942544/3986624511',
        );
      }
    }
  });

  testWidgets(
    'narrow native slot requests one adaptive banner without cropping',
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
      expect(calls.where((call) => call == 'loadNativeAd'), isEmpty);
      expect(calls.where((call) => call == 'loadBannerAd'), hasLength(1));
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
