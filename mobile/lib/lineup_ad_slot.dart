import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'ads.dart';
import 'native_ad_card.dart';

/// An inline placement between or after the home and away lineups.
class LineupAdSlot extends StatelessWidget {
  const LineupAdSlot({super.key, this.controller, this.preview = kIsWeb});
  final AdsController? controller;
  final bool preview;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final native = constraints.maxWidth >= 320;
      if (preview) {
        final colors = Theme.of(context).colorScheme;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Pubblicità', style: Theme.of(context).textTheme.labelSmall),
              const SizedBox(height: 8),
              Container(
                height: native ? 160 : 100,
                width: double.infinity,
                constraints: const BoxConstraints(maxWidth: 400),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerLow,
                  border: Border.all(color: colors.outlineVariant),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.campaign_outlined,
                      color: colors.onSurfaceVariant,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Anteprima annuncio',
                      style: TextStyle(color: colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      }
      // The native template needs at least 320 logical pixels. The existing
      // adaptive banner handles narrower screens without cropping ad assets.
      return native
          ? HomeNativeAdCard(controller: controller)
          : AdaptiveAdBanner(controller: controller);
    },
  );
}
