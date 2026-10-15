import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import 'ads.dart';

/// One compact native template in the news feed, replacing the Home banner.
class HomeNativeAdCard extends StatelessWidget {
  const HomeNativeAdCard({super.key, this.controller});
  final AdsController? controller;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: controller ?? AdsController.instance,
    builder: (context, _) {
      if (!(controller ?? AdsController.instance).ready ||
          !(ModalRoute.of(context)?.isCurrent ?? true)) {
        return const SizedBox.shrink();
      }
      return SafeArea(
        top: false,
        bottom: false,
        child: LayoutBuilder(
          builder: (context, constraints) {
            // The SDK's small template recommends at least 320 logical pixels.
            // Do not crop assets, shrink the CTA or overlay AdChoices to fit it.
            if (constraints.maxWidth < 320) return const SizedBox.shrink();
            final colors = Theme.of(context).colorScheme;
            return Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 400),
                child: _NativeCard(key: ValueKey(colors), colors: colors),
              ),
            );
          },
        ),
      );
    },
  );
}

class _NativeCard extends StatefulWidget {
  const _NativeCard({super.key, required this.colors});
  final ColorScheme colors;
  @override
  State<_NativeCard> createState() => _NativeCardState();
}

class _NativeCardState extends State<_NativeCard> {
  NativeAd? _ad;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final colors = widget.colors;
    final background = colors.surfaceContainerLow;
    final ad = NativeAd(
      adUnitId: nativeIdForPlatform(
        defaultTargetPlatform,
        release: kReleaseMode,
        monetization: const bool.fromEnvironment('TOS_ADS_MONETIZATION'),
      )!,
      request: const AdRequest(),
      nativeAdOptions: NativeAdOptions(
        videoOptions: VideoOptions(startMuted: true),
      ),
      nativeTemplateStyle: NativeTemplateStyle(
        templateType: TemplateType.small,
        mainBackgroundColor: background,
        cornerRadius: 12,
        primaryTextStyle: NativeTemplateTextStyle(
          textColor: colors.onSurface,
          backgroundColor: background,
          style: NativeTemplateFontStyle.bold,
          size: 15,
        ),
        secondaryTextStyle: NativeTemplateTextStyle(
          textColor: colors.onSurfaceVariant,
          backgroundColor: background,
          size: 13,
        ),
        tertiaryTextStyle: NativeTemplateTextStyle(
          textColor: colors.onSurfaceVariant,
          backgroundColor: background,
          size: 12,
        ),
        callToActionTextStyle: NativeTemplateTextStyle(
          textColor: colors.onPrimary,
          backgroundColor: colors.primary,
          style: NativeTemplateFontStyle.bold,
          size: 14,
        ),
      ),
      listener: NativeAdListener(
        onAdLoaded: (ad) {
          if (!mounted || !identical(_ad, ad)) {
            ad.dispose();
            return;
          }
          setState(() => _loaded = true);
        },
        onAdFailedToLoad: (ad, error) {
          ad.dispose();
          if (mounted && identical(_ad, ad)) {
            setState(() {
              _ad = null;
              _loaded = false;
            });
          }
        },
      ),
    );
    _ad = ad;
    try {
      await ad.load();
    } catch (_) {
      ad.dispose();
      if (mounted && identical(_ad, ad)) {
        setState(() {
          _ad = null;
          _loaded = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ad == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Pubblicità', style: Theme.of(context).textTheme.labelSmall),
          const SizedBox(height: 8),
          // The native SDK owns all ad assets, attribution, CTA and AdChoices.
          SizedBox(
            width: double.infinity,
            height: 160,
            child: AdWidget(ad: _ad!),
          ),
        ],
      ),
    );
  }
}
