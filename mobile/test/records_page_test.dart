import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/records_page.dart';
import 'package:tornei_old_school/personal_player_design.dart';

void main() {
  testWidgets(
    'statistiche portieri sempre sotto Le mie statistiche anche senza tabellini',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 1400));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = TosApi(baseUrl: Uri.parse('https://example.com'));
      api.authenticatedRead = (_, query) async {
        expect(query?['player_id'], '7');
        return {
          'groups': <String, dynamic>{},
          'tournaments': [],
          'formats': [],
        };
      };
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: PlayerDashboard(
              api: api,
              player: {'id': 7, 'nome': 'Mario', 'cognome': 'Cava'},
              teams: [],
              section: 'calcio',
              refresh: () async {},
              history: (_) {},
              openTeam: (_) {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(GoalkeeperSummary));
      expect(
        tester.getTopLeft(find.text('Statistiche portiere')).dy,
        greaterThan(tester.getTopLeft(find.text('Le mie statistiche')).dy),
      );
      for (final label in [
        'Presenze in porta',
        'Gol subiti',
        'Media gol subiti',
        'Porte inviolate',
        'Percentuale porte inviolate',
      ]) {
        expect(find.text(label), findsOneWidget);
      }
      expect(
        find.descendant(
          of: find.byType(GoalkeeperSummary),
          matching: find.text('—'),
        ),
        findsNWidgets(5),
      );
      expect(find.textContaining('Nessun tabellino'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'portieri riprova dopo errore e visualizza i valori del backend',
    (tester) async {
      final api = TosApi(baseUrl: Uri.parse('https://example.com'));
      int calls = 0;
      api.authenticatedRead = (_, _) async {
        if (calls++ == 0) {
          throw const ApiError('Errore temporaneo');
        }
        return {
          'groups': {
            'Calcio a 5': {
              'goalkeeper': {
                'appearances': 2,
                'goals_conceded': 3,
                'average': '1.50',
                'clean_sheets': 1,
                'clean_sheet_percentage': '50.00',
              },
            },
          },
        };
      };
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: GoalkeeperSummary(api: api, playerId: '7'),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Presenze in porta'), findsOneWidget);
      await tester.tap(find.text('Riprova'));
      await tester.pumpAndSettle();
      expect(find.text('1.50'), findsOneWidget);
      expect(find.text('50.00%'), findsOneWidget);
      expect(calls, 2);
      expect(find.text('Riprova'), findsNothing);
    },
  );
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
          home: GoalkeeperStatsPage(api: api, playerId: '7'),
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
    await tester.pumpWidget(
      MaterialApp(
        home: GoalkeeperStatsPage(api: api, playerId: '7'),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Nessun dato verificato disponibile per questi filtri.'),
      findsOneWidget,
    );
  });
}
