import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/player_event_marks.dart';
import 'package:tornei_old_school/own_goal_icon.dart';

void main() {
  testWidgets('An own goal alone is visible in match events with its count', (
    tester,
  ) async {
    final stats = <String, dynamic>{'autogol': '2', 'goal': 0};
    expect(hasPlayerEvents(stats), isTrue);
    expect(hasPlayerEvents({'autogol': 0}), isFalse);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: PlayerEventMarks(stats: stats)),
      ),
    );
    expect(find.textContaining('Autogol'), findsOneWidget);
    expect(find.byTooltip('Autogol: 2'), findsOneWidget);
    expect(find.byType(OwnGoalIcon), findsOneWidget);
    expect(find.textContaining('\u26BD'), findsNothing);
    expect(find.byType(Image), findsNothing);
  });
}
