import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

/// Production requires both a release build and an explicit opt-in.
String androidBannerId({required bool release, required bool monetization}) =>
    release && monetization
    ? 'ca-app-pub-8390787841690316/9634821513'
    : 'ca-app-pub-3940256099942544/9214589741';

String androidNativeId({required bool release, required bool monetization}) =>
    release && monetization
    ? 'ca-app-pub-8390787841690316/2123997150'
    : 'ca-app-pub-3940256099942544/2247696110';

bool supportsMobileAds({required bool web, required TargetPlatform platform}) =>
    !web &&
    (platform == TargetPlatform.android || platform == TargetPlatform.iOS);

String? bannerIdForPlatform(
  TargetPlatform platform, {
  required bool release,
  required bool monetization,
}) => switch (platform) {
  TargetPlatform.android => androidBannerId(
    release: release,
    monetization: monetization,
  ),
  // iOS production IDs are deliberately unavailable until supplied separately.
  TargetPlatform.iOS => 'ca-app-pub-3940256099942544/2435281174',
  _ => null,
};

String? nativeIdForPlatform(
  TargetPlatform platform, {
  required bool release,
  required bool monetization,
}) => switch (platform) {
  TargetPlatform.android => androidNativeId(
    release: release,
    monetization: monetization,
  ),
  TargetPlatform.iOS => 'ca-app-pub-3940256099942544/3986624511',
  _ => null,
};

abstract class AdsPrivacyBackend {
  Future<void> update();
  Future<void> collect();
  Future<void> options();
  Future<bool> canRequest();
  Future<bool> optionsRequired();
  Future<void> initialize();
}

class GoogleAdsPrivacy implements AdsPrivacyBackend {
  @override
  Future<void> update() {
    final done = Completer<void>();
    ConsentInformation.instance.requestConsentInfoUpdate(
      ConsentRequestParameters(),
      () => done.complete(),
      (error) => done.completeError(error),
    );
    return done.future;
  }

  Future<void> _form(void Function(void Function(FormError?)) show) {
    final done = Completer<void>();
    show(
      (error) => error == null ? done.complete() : done.completeError(error),
    );
    return done.future;
  }

  @override
  Future<void> collect() => _form(ConsentForm.loadAndShowConsentFormIfRequired);
  @override
  Future<void> options() async {
    // Let listeners remove AdWidget before presenting a new consent choice.
    await WidgetsBinding.instance.endOfFrame;
    await _form(ConsentForm.showPrivacyOptionsForm);
  }

  @override
  Future<bool> canRequest() => ConsentInformation.instance.canRequestAds();
  @override
  Future<bool> optionsRequired() async =>
      await ConsentInformation.instance.getPrivacyOptionsRequirementStatus() ==
      PrivacyOptionsRequirementStatus.required;
  @override
  Future<void> initialize() async {
    await MobileAds.instance.initialize();
  }
}

class AdsController extends ChangeNotifier {
  AdsController({required this.enabled, required this.backend});
  static final instance = AdsController(
    // Both mobile platforms use their own official sample IDs by default.
    enabled:
        supportsMobileAds(web: kIsWeb, platform: defaultTargetPlatform) &&
        !const bool.fromEnvironment('TOS_PREVIEW'),
    backend: GoogleAdsPrivacy(),
  );
  final bool enabled;
  final AdsPrivacyBackend backend;
  bool ready = false, privacyRequired = false, busy = false;
  bool _started = false, _initialized = false;
  String? error;

  Future<void> start() async {
    if (!enabled || _started) return;
    _started = true;
    await _run(() async {
      await backend.update();
      await backend.collect();
    });
  }

  Future<void> showPrivacyOptions() async {
    if (!enabled || busy || !privacyRequired) return;
    await _run(backend.options);
  }

  Future<void> _run(Future<void> Function() action) async {
    busy = true;
    ready = false; // Remove and dispose banners before changing consent.
    error = null;
    notifyListeners();
    try {
      await action();
    } catch (_) {
      error = 'Preferenze privacy non disponibili.';
    }
    try {
      privacyRequired = await backend.optionsRequired();
      if (await backend.canRequest()) {
        if (!_initialized) {
          await backend.initialize();
          _initialized = true;
        }
        ready = true;
      }
    } catch (_) {
      ready = false;
      error = 'Pubblicità non disponibile.';
    }
    busy = false;
    notifyListeners();
  }
}

class AdsPrivacyButton extends StatelessWidget {
  const AdsPrivacyButton({super.key});
  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: AdsController.instance,
    builder: (context, _) {
      final ads = AdsController.instance;
      if (!ads.privacyRequired) return const SizedBox.shrink();
      return IconButton(
        tooltip: 'Preferenze privacy annunci',
        icon: const Icon(Icons.privacy_tip_outlined),
        onPressed: ads.busy
            ? null
            : () async {
                await ads.showPrivacyOptions();
                if (context.mounted && ads.error != null) {
                  ScaffoldMessenger.of(context)
                      .showSnackBar(SnackBar(content: Text(ads.error!)));
                }
              },
      );
    },
  );
}

class AdaptiveAdBanner extends StatelessWidget {
  const AdaptiveAdBanner({super.key, this.controller});
  final AdsController? controller;
  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: controller ?? AdsController.instance,
    builder: (context, _) =>
        (controller ?? AdsController.instance).ready &&
            (ModalRoute.of(context)?.isCurrent ?? true)
        ? const SafeArea(top: false, bottom: false, child: _AdaptiveBanner())
        : const SizedBox.shrink(),
  );
}

class _AdaptiveBanner extends StatefulWidget {
  const _AdaptiveBanner();
  @override
  State<_AdaptiveBanner> createState() => _AdaptiveBannerState();
}

class _AdaptiveBannerState extends State<_AdaptiveBanner> {
  BannerAd? _ad;
  AdSize? _size;
  bool _loaded = false;
  int _generation = 0;
  String? _layout;
  Future<void> _load(int width, String layout) async {
    if (!mounted || layout != _layout) return;
    final generation = ++_generation;
    setState(() {
      _loaded = false;
      _size = null;
      _ad?.dispose();
      _ad = null;
    });
    try {
      final size = AdSize.getInlineAdaptiveBannerAdSize(width, 100);
      if (!mounted || generation != _generation || layout != _layout) {
        return;
      }
      final ad = BannerAd(
        adUnitId: bannerIdForPlatform(
          defaultTargetPlatform,
          release: kReleaseMode,
          monetization: const bool.fromEnvironment('TOS_ADS_MONETIZATION'),
        )!,
        size: size,
        request: const AdRequest(),
        listener: BannerAdListener(
          onAdLoaded: (ad) async {
            AdSize? actualSize;
            try {
              actualSize = await (ad as BannerAd).getPlatformAdSize();
            } catch (_) {
              // Leave the slot hidden if the native view cannot be measured.
              return;
            }
            if (!mounted || generation != _generation) {
              ad.dispose();
              return;
            }
            if (actualSize == null || actualSize.height <= 0) return;
            setState(() {
              _size = actualSize;
              _loaded = true;
            });
          },
          onAdFailedToLoad: (ad, error) {
            ad.dispose();
            if (mounted && generation == _generation) {
              setState(() {
                _ad = null;
                _loaded = false;
              });
            }
          },
        ),
      );
      _ad = ad;
      await ad.load();
    } catch (_) {
      /* Ads must never interrupt tournament functionality. */
    }
  }

  @override
  void dispose() {
    ++_generation;
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = constraints.maxWidth.floor();
      final layout = '$width-${MediaQuery.orientationOf(context)}';
      if (width > 0 && layout != _layout) {
        _layout = layout;
        WidgetsBinding.instance.addPostFrameCallback(
          (_) => _load(width, layout),
        );
      }
      if (!_loaded || _ad == null || _size == null || _size!.width > width) {
        return const SizedBox.shrink();
      }
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Pubblicità', style: Theme.of(context).textTheme.labelSmall),
            const SizedBox(height: 6),
            SizedBox(
              width: _size!.width.toDouble(),
              height: _size!.height.toDouble(),
              child: AdWidget(ad: _ad!),
            ),
          ],
        ),
      );
    },
  );
}
