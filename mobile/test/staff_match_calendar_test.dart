import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_match_forms.dart';
import 'package:tornei_old_school/theme.dart';

void main() {
  for (final width in [320.0, 1000.0]) {
    testWidgets(
      'Field calendar renders existing and draft matches at $width px',
      (tester) async {
        await tester.binding.setSurfaceSize(Size(width, 1000));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final key = GlobalKey();
        var actions = 0;
        final model = {
          'title': '9–15 novembre 2026 · Campo 1',
          'note': 'Orari di inizio. Blu: presenti. Arancione: da creare.',
          'firstHour': 18,
          'lastHour': 21,
          'actions': [
            {'label': 'Precedente'},
            {'label': 'Oggi'},
            {'label': 'Successivo'},
            {'label': 'Giorno'},
            {'label': 'Settimana'},
          ],
          'days': [
            for (var index = 0; index < 7; index++)
              {
                'label': '${9 + index} nov',
                'today': index == 0,
                'events': index == 0
                    ? [
                        {
                          'time': '18:30',
                          'title': 'Napoli – Roma',
                          'subtitle': 'McLeague · REGULAR · G2',
                          'draft': false,
                        },
                        {
                          'time': '19:30',
                          'title': 'Inter – Lazio',
                          'subtitle': 'Da creare',
                          'draft': true,
                        },
                      ]
                    : [],
              },
          ],
        };
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: Scaffold(
              body: RepaintBoundary(
                key: key,
                child: MediaQuery(
                  data: MediaQueryData(
                    size: Size(width, 1000),
                    textScaler: const TextScaler.linear(1.4),
                  ),
                  child: ListView(
                    children: [
                      StaffMatchCalendar(
                        model: model,
                        render: (block) => TextButton(
                          onPressed: () => actions++,
                          child: Text('${block['label']}'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Napoli – Roma'), findsOneWidget);
        expect(find.text('Da creare'), findsOneWidget);
        await tester.tap(find.text('Successivo'));
        await tester.pump();
        expect(actions, 1);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
