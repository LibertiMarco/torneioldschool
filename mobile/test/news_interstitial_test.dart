import 'package:flutter/material.dart';
import 'package:tornei_old_school/article_content.dart';
import 'package:tornei_old_school/ads.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/news_interstitial.dart';

void main() {
  testWidgets('Article banners preserve paragraphs and have a density cap', (
    tester,
  ) async {
    final paragraph = List.filled(20, 'Parola').join(' ');
    for (final count in [1, 4, 16]) {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ArticleContent(
                content: List.filled(count, paragraph).join('\n\n'),
                ads: true,
              ),
            ),
          ),
        ),
      );
      expect(find.byType(AdaptiveAdBanner), findsNWidgets(count < 8 ? 1 : 2));
      expect(find.text(paragraph, findRichText: true), findsNWidgets(count));
    }
  });
  test('News and articles share a grace period and cooldown', () {
    final start = DateTime(2026);
    final frequency = NewsAdFrequency(started: start);
    expect(frequency.allows(start), isFalse);
    expect(frequency.allows(start.add(const Duration(seconds: 30))), isTrue);
    frequency.lastShown = start.add(const Duration(seconds: 30));
    expect(frequency.allows(start.add(const Duration(minutes: 2))), isFalse);
    expect(frequency.allows(start.add(const Duration(seconds: 150))), isTrue);
  });
}
