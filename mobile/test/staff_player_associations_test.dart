import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_player_associations.dart';
import 'package:tornei_old_school/native_staff_tool.dart';
import 'package:tornei_old_school/theme.dart';

void main() {
  for (final scale in [1.0, 1.6]) {
    testWidgets(
      'Association search shows at most four rows and scrolls at scale $scale',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 800));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final actions = <Map>[];
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: Scaffold(
              body: MediaQuery(
                data: MediaQueryData(
                  size: const Size(320, 800),
                  textScaler: TextScaler.linear(scale),
                ),
                child: ListView(
                  children: [
                    StaffPlayerList(
                      model: {
                        'context': 'team:query',
                        'rows': [
                          for (var i = 0; i < 10; i++)
                            {
                              'name': 'Giocatore $i Cognome lungo',
                              'detail': 'Centrocampista',
                              'action': {'id': 'add-$i', 'disabled': false},
                            },
                        ],
                      },
                      onChanged: (_, _) {},
                      onAction: actions.add,
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          find.text('Giocatore 0 Cognome lungo').hitTestable(),
          findsOneWidget,
        );
        expect(
          find.text('Giocatore 4 Cognome lungo').hitTestable(),
          findsNothing,
        );
        final list = find.descendant(
          of: find.byType(StaffPlayerList),
          matching: find.byType(ListView),
        );
        await tester.drag(list, const Offset(0, -1000));
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(
          find.text('Giocatore 9 Cognome lungo'),
          100,
          scrollable: find
              .descendant(of: list, matching: find.byType(Scrollable))
              .first,
        );
        await tester.tap(find.text('Giocatore 9 Cognome lungo'));
        expect(actions.last['id'], 'add-9');
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets('Removal search preserves selected players outside the filter', (
    tester,
  ) async {
    final changes = <Map>[];
    final rows = [
      for (var i = 0; i < 10; i++)
        {
          'field': {
            'id': 'player-$i',
            'label': 'Giocatore $i',
            'checked': i == 0,
          },
        },
    ];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ListView(
            children: [
              StaffPlayerList(
                model: {'removal': true, 'context': 'team-1', 'rows': rows},
                onChanged: (field, value) =>
                    changes.add({'id': field['id'], ...value}),
                onAction: (_) {},
              ),
            ],
          ),
        ),
      ),
    );
    await tester.enterText(find.byType(TextField), 'Giocatore 9');
    await tester.pumpAndSettle();
    expect(find.byType(CheckboxListTile), findsNothing);
    await tester.tap(find.widgetWithText(SwitchListTile, 'Giocatore 9'));
    await tester.pumpAndSettle();
    expect(changes.single, {'id': 'player-9', 'checked': true});
    expect(find.text('2 di 10 selezionati'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '');
    await tester.pumpAndSettle();
    expect(find.text('2 di 10 selezionati'), findsOneWidget);
    expect(rows[9]['field']!['checked'], true);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'Captain uses a switch and role choices retain the empty option',
    (tester) async {
      final changes = <Map>[];
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ListView(
              children: [
                StaffNativeField(
                  field: {
                    'type': 'checkbox',
                    'appearance': 'captain',
                    'label': 'Capitano',
                    'checked': false,
                  },
                  onChanged: changes.add,
                  onPick: () {},
                ),
                StaffNativeField(
                  field: {
                    'type': 'select',
                    'appearance': 'choice',
                    'label': 'Ruolo in squadra',
                    'value': 'Portiere',
                    'options': [
                      {'value': '', 'label': 'Nessun ruolo'},
                      {'value': 'Portiere', 'label': 'Portiere'},
                    ],
                  },
                  onChanged: changes.add,
                  onPick: () {},
                ),
              ],
            ),
          ),
        ),
      );
      expect(find.byType(SwitchListTile), findsOneWidget);
      await tester.tap(find.text('Capitano'));
      await tester.pumpAndSettle();
      expect(changes.single, {'checked': true});
      await tester.tap(find.text('Portiere'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Nessun ruolo'));
      await tester.pumpAndSettle();
      expect(changes.last, {'value': ''});
      expect(tester.takeException(), isNull);
    },
  );
}
