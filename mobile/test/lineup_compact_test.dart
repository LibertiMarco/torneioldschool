import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_match_forms.dart';
import 'package:tornei_old_school/staff_controls.dart';
import 'package:tornei_old_school/native_staff_tool.dart';
import 'package:tornei_old_school/theme.dart';

import 'staff_dark_review_test.dart' show capture;

void main() {
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });
  for (final scale in [1.0, 1.6]) {
    testWidgets('Compact lineup fits at 320px with text scale $scale', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final key = GlobalKey();
      final players = List.generate(
        3,
        (i) => {
          'id': 'player-$i',
          'name': ['Marco Rossi', 'Luca Bianchi', 'Mario Esposito'][i],
          'badges': i == 0 ? ['C', 'DIFFIDATO'] : [],
          'fields': [
            {
              'id': 'presence-$i',
              'stat': 'presenza',
              'type': 'checkbox',
              'checked': true,
            },
            for (final stat in ['goal', 'assist', 'autogol'])
              {
                'id': '$stat-$i',
                'stat': stat,
                'label': stat == 'goal'
                    ? 'Gol'
                    : stat == 'assist'
                    ? 'Assist'
                    : 'Autogol',
                'value': '0',
              },
            for (final stat in ['cartellino_giallo', 'cartellino_rosso'])
              {
                'id': '$stat-$i',
                'stat': stat,
                'type': 'checkbox',
                'checked': false,
              },
            {
              'id': 'vote-$i',
              'stat': 'voto',
              'type': 'select',
              'label': 'Voto',
              'value': '6',
              'options': [
                {'value': '6', 'label': '6'},
                {'value': '7', 'label': '7'},
              ],
            },
          ],
        },
      );
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: MaterialApp(
            theme: siteDarkTheme(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: StaffTheme(
              child: Scaffold(
                appBar: AppBar(title: const Text('Statistiche partita')),
                body: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: StaffMatchLineup(
                    model: {
                      'home': 'Old School',
                      'away': 'Academy',
                      'score': '0 - 0',
                      'teams': [
                        {
                          'id': 'home',
                          'name': 'Old School',
                          'players': players,
                          'actions': [],
                        },
                      ],
                    },
                    render: (field) => StaffNativeField(
                      field: Map<String, dynamic>.from(field),
                      onChanged: (_) {},
                      onPick: () {},
                    ),
                    onChanged: (_, _) {},
                    onAction: (_) {},
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      if (scale == 1) {
        Finder counter(String id) => find.byWidgetPredicate(
          (w) => w is StaffStatCounter && w.field['id'] == id,
        );
        expect(
          tester.getTopLeft(counter('goal-0')).dy,
          tester.getTopLeft(counter('autogol-0')).dy,
        );
        final gap =
            tester.getTopLeft(find.text('Luca Bianchi')).dy -
            tester.getTopLeft(find.text('Marco Rossi')).dy;
        expect(gap, lessThan(280));
      }
      await capture(tester, key, 'lineup-compact-$scale');
      expect(tester.takeException(), isNull);
    });
  }
}
