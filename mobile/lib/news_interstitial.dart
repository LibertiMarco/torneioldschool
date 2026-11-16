import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import 'ads.dart';

/// Shared by the news section and article links: never two consecutive ads.
class NewsAdFrequency {
  NewsAdFrequency({DateTime? started}) : started = started ?? DateTime.now();
  final DateTime started;
  DateTime? lastShown;
  bool allows(DateTime now) =>
      now.difference(started) >= const Duration(seconds: 30) &&
      (lastShown == null ||
          now.difference(lastShown!) >= const Duration(minutes: 2));
}

class NewsInterstitial {
  NewsInterstitial._() {
    AdsController.instance.addListener(_prepare);
    _prepare();
  }
  static final instance = NewsInterstitial._();
  final frequency = NewsAdFrequency();
  InterstitialAd? _ad;
  DateTime? _loadedAt;
  bool _loading = false, _showing = false, _navigating = false;
  int _generation = 0;

  String? get _unit {
    if (!AdsController.instance.enabled) return null;
    final ios = defaultTargetPlatform == TargetPlatform.iOS;
    if (!kReleaseMode) {
      return ios
          ? 'ca-app-pub-3940256099942544/4411468910'
          : 'ca-app-pub-3940256099942544/1033173712';
    }
    if (!const bool.fromEnvironment('TOS_ADS_MONETIZATION')) return null;
    final id = ios
        ? const String.fromEnvironment(
            'TOS_IOS_NEWS_INTERSTITIAL_ID',
            defaultValue: 'ca-app-pub-8390787841690316/1835677376',
          )
        : const String.fromEnvironment(
            'TOS_ANDROID_NEWS_INTERSTITIAL_ID',
            defaultValue: 'ca-app-pub-8390787841690316/8574290205',
          );
    return id.isEmpty ? null : id;
  }

  void _prepare() {
    if (!AdsController.instance.ready) {
      _generation++;
      _ad?.dispose();
      _ad = null;
      return;
    }
    final unit = _unit;
    if (unit == null || _loading || _showing || _ad != null) return;
    final generation = _generation;
    _loading = true;
    unawaited(
      InterstitialAd.load(
        adUnitId: unit,
        request: const AdRequest(),
        adLoadCallback: InterstitialAdLoadCallback(
          onAdLoaded: (ad) {
            _loading = false;
            if (generation != _generation || !AdsController.instance.ready) {
              ad.dispose();
              return;
            }
            _ad = ad;
            _loadedAt = DateTime.now();
          },
          onAdFailedToLoad: (_) {
            _loading = false;
          },
        ),
      ).catchError((Object _) {
        _loading = false;
      }),
    );
  }

  Future<void> open(BuildContext context, VoidCallback navigate) async {
    if (_navigating) return;
    _navigating = true;
    try {
      final ad = _ad;
      final now = DateTime.now();
      final fresh =
          _loadedAt != null &&
          now.difference(_loadedAt!) < const Duration(minutes: 55);
      if (ad != null &&
          fresh &&
          AdsController.instance.ready &&
          frequency.allows(now)) {
        _ad = null;
        _showing = true;
        final done = Completer<void>();
        void finish() {
          ad.dispose();
          _showing = false;
          if (!done.isCompleted) done.complete();
        }

        ad.fullScreenContentCallback = FullScreenContentCallback(
          onAdShowedFullScreenContent: (_) {
            frequency.lastShown = now;
          },
          onAdDismissedFullScreenContent: (_) => finish(),
          onAdFailedToShowFullScreenContent: (_, _) => finish(),
        );
        try {
          await ad.show();
          await done.future;
        } catch (_) {
          finish();
        }
      } else if (!fresh) {
        _ad?.dispose();
        _ad = null;
      }
      if (context.mounted && (ModalRoute.of(context)?.isCurrent ?? true)) {
        navigate();
      }
    } finally {
      _navigating = false;
      _prepare();
    }
  }
}
