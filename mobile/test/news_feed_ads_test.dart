import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/native_ad_card.dart';

void main() {
  for (final preview in [false, true]) {
    testWidgets('news spacing and order with preview=$preview', (tester) async {
      final api = preview
          ? DemoApi()
          : TosApi(baseUrl: Uri.parse('https://example.test'));
      for (final count in [0, 1, 6, 7, 12, 13, 40]) {
        final rows = List.generate(
          count,
          (i) => <String, dynamic>{'id': i, 'titolo': 'News $i'},
        );
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: NewsFeed(api: api, rows: rows),
            ),
          ),
        );
        final list = tester.widget<ListView>(find.byType(ListView));
        final delegate = list.childrenDelegate;
        final context = tester.element(find.byType(ListView));
        // Builder wraps each item; inspect its returned child through the
        // original builder so content order is checked without mounting ads.
        final builder = delegate as SliverChildBuilderDelegate;
        final children = [
          for (var i = 0; i < delegate.estimatedChildCount!; i++)
            builder.builder(context, i)!,
        ];
        expect(
          children.whereType<NewsCard>().map((w) => w.row['id']),
          List.generate(count, (i) => i),
        );
        expect(
          children.whereType<HomeNativeAdCard>().length,
          preview || count == 0 ? 0 : (count - 1) ~/ 6,
        );
        if (children.isNotEmpty) expect(children.last, isA<NewsCard>());
        for (var i = 0; i < children.length; i++) {
          if (children[i] is HomeNativeAdCard) expect(i % 7, 6);
        }
        expect(tester.takeException(), isNull);
      }
    });
  }

  testWidgets('long news feed builds nearby articles progressively', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: NewsFeed(
            api: TosApi(baseUrl: Uri.parse('https://example.test')),
            rows: List.generate(
              100,
              (i) => <String, dynamic>{'id': i, 'titolo': 'News $i'},
            ),
          ),
        ),
      ),
    );
    expect(find.byType(NewsCard).evaluate().length, lessThan(30));
    expect(find.text('News 99'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
