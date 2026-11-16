import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/ranking_pagination.dart';

void main() {
  for (final scale in [1.0, 2.0]) {
    testWidgets(
      'pagination at 320px and text scale $scale respects boundaries',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 800));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final changes = <int>[];
        Future<void> show(int page, int total) => tester.pumpWidget(
          MaterialApp(
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: Scaffold(
              body: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  RankingPagination(
                    page: page,
                    totalPages: total,
                    players: 1000,
                    onChanged: changes.add,
                  ),
                ],
              ),
            ),
          ),
        );
        await show(1, 100);
        expect(
          tester
              .widget<IconButton>(
                find.widgetWithIcon(IconButton, Icons.arrow_back_rounded),
              )
              .onPressed,
          isNull,
        );
        await tester.tap(find.byTooltip('Pagina successiva'));
        expect(changes, [2]);
        await show(100, 100);
        expect(
          tester
              .widget<IconButton>(
                find.widgetWithIcon(IconButton, Icons.arrow_forward_rounded),
              )
              .onPressed,
          isNull,
        );
        await tester.tap(find.byTooltip('Pagina precedente'));
        expect(changes.last, 99);
        await show(1, 1);
        expect(
          tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
          isNull,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }
}
