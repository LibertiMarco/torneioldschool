import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/records_page.dart';

void main() {
  testWidgets('record espanso leggibile a 320px con testo ingrandito', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = TosApi(baseUrl: Uri.parse('https://example.com'));
    api.authenticatedRead = (_, _) async => {
      'coverage': 'Solo dati verificati',
      'tournaments': ['Coppa'],
      'formats': ['Calcio a 5'],
      'groups': {
        'Calcio a 5': {
          'records': {
            'total': {
              'label': 'Partita con più gol',
              'value': 12,
              'holders': [
                {
                  'subject': 'Squadra Old School Campionato',
                  'player_id': null,
                  'match': {
                    'id': 1,
                    'torneo': 'Coppa Old School Campionato',
                    'squadra_casa': 'Squadra di casa Old School',
                    'squadra_ospite': 'Squadra ospite Old School',
                    'gol_casa': 7,
                    'gol_ospite': 5,
                    'data_partita': '2026-10-01',
                  },
                },
              ],
            },
          },
        },
      },
    };
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: const TextScaler.linear(1.6)),
          child: child!,
        ),
        home: RecordsPage(api: api),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Partita con più gol'));
    await tester.tap(find.text('Partita con più gol'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Squadra Old School Campionato'), findsOneWidget);
  });
  testWidgets(
    'record e statistiche usano valori del servizio condiviso e filtri',
    (tester) async {
      final api = TosApi(baseUrl: Uri.parse('https://example.com'));
      final queries = <Map<String, String>?>[];
      api.authenticatedRead = (path, query) async {
        expect(path, 'api/records.php');
        queries.add(query);
        return {
          'coverage': 'Solo dati completi verificati',
          'tournaments': ['Coppa'],
          'formats': ['Calcio a 5'],
          'groups': {
            'Calcio a 5': {
              'eligible_matches': 10,
              'verified_keeper_matches': 2,
              'goalkeeper': {
                'appearances': 2,
                'goals_conceded': 3,
                'average': '1.50',
                'clean_sheets': 1,
                'clean_sheet_percentage': '50.00',
              },
              'records': {
                'total': {
                  'label': 'Partita con più gol',
                  'value': 9,
                  'holders': [],
                },
              },
            },
          },
        };
      };
      await tester.pumpWidget(
        MaterialApp(
          home: RecordsPage(api: api, playerId: '7'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Media: 1.50'), findsOneWidget);
      expect(find.text('Percentuale: 50.00%'), findsOneWidget);
      expect(queries.single!['player_id'], '7');
      await tester.tap(find.text('Storico TOS'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Coppa').last);
      await tester.pumpAndSettle();
      expect(queries.last!['torneo'], 'Coppa');
      await tester.pumpWidget(
        MaterialApp(
          home: RecordsPage(key: UniqueKey(), api: api),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Partita con più gol'), findsOneWidget);
      expect(find.text('9 · 0 detentori'), findsOneWidget);
    },
  );
  testWidgets('nessun dato mostra stato vuoto', (tester) async {
    final api = TosApi(baseUrl: Uri.parse('https://example.com'));
    api.authenticatedRead = (_, _) async => {
      'coverage': 'Dati mancanti',
      'groups': <String, dynamic>{},
      'tournaments': [],
      'formats': [],
    };
    await tester.pumpWidget(MaterialApp(home: RecordsPage(api: api)));
    await tester.pumpAndSettle();
    expect(
      find.text('Nessun dato verificato disponibile per questi filtri.'),
      findsOneWidget,
    );
  });
}
