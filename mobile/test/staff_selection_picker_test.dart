import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_selection_picker.dart';

void main() {
  testWidgets(
    'Selection picker starts with all teams, searches, toggles with switches and applies once',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final calls = <List<String>>[];
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StaffSelectionPicker(
              model: {
                'title': 'Squadre che giocano',
                'options': [
                  for (var i = 1; i <= 12; i++)
                    {'id': '$i', 'label': 'Squadra $i', 'checked': true},
                ],
              },
              onChanged: calls.add,
            ),
          ),
        ),
      );
      expect(find.text('12 di 12 selezionate'), findsOneWidget);
      await tester.tap(find.text('Squadre che giocano'));
      await tester.pumpAndSettle();
      expect(find.byType(CheckboxListTile), findsNothing);
      await tester.tap(find.text('Deseleziona tutte'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Squadra 10');
      await tester.pumpAndSettle();
      await tester.tap(
        find.descendant(
          of: find.byType(SwitchListTile),
          matching: find.text('Squadra 10'),
        ),
      );
      await tester.pumpAndSettle();
      expect(calls, isEmpty);
      await tester.tap(find.text('Conferma selezione'));
      await tester.pumpAndSettle();
      expect(calls, [
        ['10'],
      ]);
      expect(tester.takeException(), isNull);
    },
  );
}
