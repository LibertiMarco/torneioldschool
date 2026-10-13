import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/personal_player_design.dart';
import 'package:tornei_old_school/theme.dart';

void main() {
  for (final ambiguous in [false, true]) {
    testWidgets(
      'Esport only shows tournaments and awards; ambiguous=$ambiguous',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 800));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        Map<String, dynamic>? opened;
        final team = <String, dynamic>{
          'id': 1,
          'nome': 'Mario Rossi',
          'torneo': 'cup',
          'torneo_nome': 'Esport Cup',
        };
        await tester.pumpWidget(
          MaterialApp(
            theme: siteDarkTheme(),
            home: Scaffold(
              body: PlayerDashboard(
                api: DemoApi(),
                player: const {'id': 0, 'nome': 'Mario', 'cognome': 'Rossi'},
                teams: ambiguous
                    ? []
                    : [
                        team,
                        {...team, 'id': 2},
                      ],
                awards: ambiguous
                    ? []
                    : [
                        {
                          'id': 1,
                          'premio': 'Campione',
                          'competizione': 'Esport Cup',
                          'anno': '2026',
                          'tipo': 'Squadra',
                        },
                      ],
                section: 'esport',
                refresh: () async {},
                history: (_) => fail('Esport must not open player history'),
                openTeam: (value) => opened = value,
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('I miei tornei'), findsOneWidget);
        expect(find.text('Premi vinti'), findsOneWidget);
        expect(find.text('Storico prestazioni'), findsNothing);
        expect(find.text('Le mie statistiche'), findsNothing);
        expect(find.textContaining('Presenze'), findsNothing);
        expect(find.textContaining('Gol'), findsNothing);
        if (ambiguous) {
          expect(
            find.text('Non risultano tornei associati al tuo account.'),
            findsOneWidget,
          );
          expect(find.text('Campione'), findsNothing);
        } else {
          expect(find.byType(ListTile), findsOneWidget);
          expect(find.text('Campione'), findsOneWidget);
          await tester.tap(find.byType(ListTile));
          expect(opened, team);
        }
        expect(tester.takeException(), isNull);
      },
    );
  }
}
