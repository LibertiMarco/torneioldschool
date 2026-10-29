import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/original_emblem.dart';

void main() {
  testWidgets('Parenthetical annotations do not change initials or color', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Row(
          children: [
            OriginalEmblem(name: 'Eredivisie', tournament: true),
            OriginalEmblem(name: 'Eredivisie (Fascia C)', tournament: true),
            OriginalEmblem(name: 'Eredivisie (Fascia B (2026))', tournament: true),
          ],
        ),
      ),
    );
    expect(find.text('E'), findsNWidgets(3));
    final colors = tester
        .widgetList<DecoratedBox>(
          find.descendant(
            of: find.byType(OriginalEmblem),
            matching: find.byType(DecoratedBox),
          ),
        )
        .map((box) => (box.decoration as BoxDecoration).color)
        .toSet();
    expect(colors.length, 1);
  });

  testWidgets('Team and tournament identities never render remote artwork', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Row(
          children: [
            for (final tournament in [false, true])
              SiteImage(
                api: DemoApi(),
                path: 'https://example.com/official-logo.png',
                identityName: 'Amatori Borgo',
                fallback: tournament
                    ? Icons.emoji_events_outlined
                    : Icons.shield_outlined,
                width: 22,
                height: 22,
              ),
          ],
        ),
      ),
    );
    expect(find.byType(Image), findsNothing);
    expect(find.byType(OriginalEmblem), findsNWidgets(2));
    expect(find.text('AB'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets('Empty and Unicode names fit at small sizes and enlarged text', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(3)),
          child: const Row(
            children: [
              OriginalEmblem(name: '', width: 22, height: 22),
              OriginalEmblem(name: 'Élite ⚽', width: 22, height: 22),
            ],
          ),
        ),
      ),
    );
    expect(find.text('S'), findsOneWidget);
    expect(find.text('É⚽'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
