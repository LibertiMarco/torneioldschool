import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/records_table.dart';
import 'package:tornei_old_school/home_dashboard.dart' show SiteImage;

void main() {
  testWidgets('tabella generale con sette record e pari merito a 320px', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = TosApi(baseUrl: Uri.parse('https://example.com'));
    final queries = <Map<String, String>?>[];
    final records = <String, dynamic>{};
    final labels = [
      'Partita con più gol',
      'Vittoria più larga',
      'Squadra con più gol in una partita',
      'Giocatore con più gol in una partita',
      'Più marcatori diversi nella stessa squadra',
      'Pareggio con più gol',
      'Vittoria più larga senza subire gol',
    ];
    for (var i = 0; i < labels.length; i++) {
      records['$i'] = {
        'label': labels[i],
        'value': i == 0 ? 12 : null,
        'holders': i == 0
            ? List.generate(
                4,
                (j) => {
                  'subject': 'Detentore ${j + 1}',
                  'player_id': 7,
                  'match': {
                    'id': 1,
                    'torneo': 'Coppa',
                    'torneo_nome': 'Coppa Old School',
                    'data_partita': '2026-10-01',
                    'squadra_casa': 'Casa',
                    'squadra_ospite': 'Ospite',
                    'gol_casa': 7,
                    'gol_ospite': 5,
                  },
                },
              )
            : [],
      };
    }
    api.authenticatedRead = (path, query) async {
      expect(path, 'api/records.php');
      queries.add(query);
      return {
        'groups': {
          'Generali': {'records': records},
        },
        'tournaments': ['Coppa'],
        'formats': ['Calcio a 5'],
        'coverage': 'Calcolati automaticamente',
      };
    };
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Column(
              children: [
                const Text('Marcatori e presenze totali'),
                RecordsTable(api: api),
              ],
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(Table), findsOneWidget);
    expect(find.text('Record TOS'), findsOneWidget);
    expect(find.text('Vittoria più larga senza subire gol'), findsOneWidget);
    expect(queries.single!['scope'], 'records');
    expect(find.text('12 gol'), findsOneWidget);
    expect(find.byType(SiteImage), findsNWidgets(2));
    expect(find.text('Detentore 4'), findsNothing);
    await tester.ensureVisible(find.text('Altri detentori · 3'));
    await tester.tap(find.text('Altri detentori · 3'));
    await tester.pumpAndSettle();
    expect(find.text('Detentore 4'), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<String>), findsNothing);
    expect(queries.single!.containsKey('formato'), isFalse);
    expect(queries.single!.containsKey('torneo'), isFalse);
    expect(tester.takeException(), isNull);
  });
}
