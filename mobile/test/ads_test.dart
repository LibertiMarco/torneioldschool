import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/ads.dart';

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
  test('real ID requires release AND explicit monetization', () {
    for (final release in [false, true]) {
      for (final monetization in [false, true]) {
        expect(
          androidBannerId(release: release, monetization: monetization),
          release && monetization
              ? 'ca-app-pub-8390787841690316/9634821513'
              : 'ca-app-pub-3940256099942544/9214589741',
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
