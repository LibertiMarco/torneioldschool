import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/player_history.dart';
import 'package:tornei_old_school/theme.dart';

const player = {'id': 7, 'nome': 'Mario', 'cognome': 'Rossi'};

class HistoryApi extends DemoApi {
  final calls = <String>[];
  bool failNext = false;
  @override
  Future<Map<String, dynamic>> playerMatches(
    String id, {
    String type = 'gol',
    int page = 1,
  }) async {
    calls.add('$id/$type/$page');
    if (failNext) {
      failNext = false;
      throw const ApiError('Connessione non disponibile. Riprova.');
    }
    return {
      'player': {
        ...player,
        'totali': {'gol': 3, 'presenze': 2},
      },
      'matches': [
        {
          'partita_id': page,
          'torneo_nome': 'Torneo $page',
          'squadra_casa': 'Old School FC',
          'squadra_ospite': 'Real Academy',
          'data_partita': '2026-10-10',
          'gol_casa': 3,
          'gol_ospite': 0,
          'goal': type == 'gol' ? 3 : 0,
          'presenza': 1,
          'voto': 7.5,
        },
      ],
      'pagination': {'has_more': page == 1},
    };
  }
}

void main() {
  testWidgets('Match tournament name is readable in dark history', (
    tester,
  ) async {
    final theme = siteDarkTheme();
    await tester.pumpWidget(
      MaterialApp(
        theme: theme,
        home: Scaffold(
          body: PlayerHistoryMatchCard(
            api: DemoApi(),
            type: 'gol',
            match: const {
              'torneo_nome': 'Torneo prova',
              'fase': 'REGULAR',
              'giornata': 3,
              'squadra_casa': 'Casa',
              'squadra_ospite': 'Ospite',
            },
          ),
        ),
      ),
    );
    expect(
      tester.widget<Text>(find.text('Torneo prova')).style?.color,
      theme.colorScheme.primary,
    );
    expect(find.textContaining('Giornata 3'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('History requests use the site endpoint, player ID, selected filter and pagination', () async {
    final api = TosApi(
      baseUrl: Uri.parse('https://example.test/sub/'),
      client: MockClient((request) async {
        expect(request.url.path, '/sub/api/giocatore_partite.php');
        expect(request.url.queryParameters, {
          'giocatore_id': '7',
          'tipo': 'presenze',
          'page': '3',
          'limit': '10',
        });
        return http.Response(
          jsonEncode({
            'player': player,
            'matches': [],
            'pagination': {'has_more': false},
          }),
          200,
        );
      }),
    );
    expect(
      (await api.playerMatches('7', type: 'presenze', page: 3))['matches'],
      isEmpty,
    );
  });

  for (final type in ['gol', 'presenze']) {
    testWidgets(
      'Ranking tap opens $type history and permits switching filter',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 800));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = HistoryApi();
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: PlayerRankingCard(api: api, row: player, order: type),
            ),
          ),
        );
        await tester.tap(find.text('Mario Rossi'));
        await tester.pumpAndSettle();
        expect(find.byType(PlayerHistoryPage), findsOneWidget);
        expect(api.calls.single, '7/$type/1');
        expect(
          find.byWidgetPredicate(
            (w) =>
                w is Image &&
                w.image is AssetImage &&
                (w.image as AssetImage).assetName ==
                    'assets/emoji/football.png',
          ),
          findsNWidgets(type == 'gol' ? 3 : 0),
        );
        final nextType = type == 'gol' ? 'presenze' : 'gol';
        await tester.tap(
          find.text(nextType == 'gol' ? 'Marcatori' : 'Presenze'),
        );
        await tester.pumpAndSettle();
        expect(api.calls.last, '7/$nextType/1');
        expect(
          find.byWidgetPredicate(
            (w) =>
                w is Image &&
                w.image is AssetImage &&
                (w.image as AssetImage).assetName ==
                    'assets/emoji/football.png',
          ),
          findsNWidgets(nextType == 'gol' ? 3 : 0),
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'History loads older matches, retries a failure and opens match details',
    (tester) async {
      final api = HistoryApi();
      await tester.pumpWidget(
        MaterialApp(
          home: PlayerHistoryPage(api: api, player: player),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.byTooltip('Pagina successiva'), 150);
      api.failNext = true;
      await tester.tap(find.byTooltip('Pagina successiva'));
      await tester.pumpAndSettle();
      expect(find.text('Torneo 1'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Riprova'), 100);
      await tester.tap(find.text('Riprova'));
      await tester.pumpAndSettle();
      expect(api.calls.last, '7/gol/2');
      await tester.scrollUntilVisible(find.text('Torneo 2'), 100);
      expect(find.text('Torneo 1'), findsNothing);
      expect(
        tester
            .widget<IconButton>(
              find.byWidgetPredicate(
                (widget) =>
                    widget is IconButton &&
                    widget.tooltip == 'Pagina successiva',
              ),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(find.byTooltip('Pagina precedente'));
      await tester.pumpAndSettle();
      expect(api.calls.last, '7/gol/1');
      expect(find.text('Torneo 1'), findsOneWidget);
      expect(find.text('Torneo 2'), findsNothing);
      await tester.tap(find.byTooltip('Pagina successiva'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Torneo 2'));
      await tester.pumpAndSettle();
      expect(find.text('Dettaglio partita'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final type in ['gol', 'presenze']) {
    testWidgets('$type history replaces each page with the next ten matches', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(600, 6000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final calls = <int>[];
      await tester.pumpWidget(
        MaterialApp(
          home: PlayerHistoryPage(
            api: DemoApi(),
            player: player,
            initialType: type,
            loadMatches: (filter, page) async {
              expect(filter, type);
              calls.add(page);
              return {
                'player': player,
                'matches': List.generate(
                  10,
                  (index) => {
                    'partita_id': (page - 1) * 10 + index + 1,
                    'torneo_nome': 'Partita ${(page - 1) * 10 + index + 1}',
                    'squadra_casa': 'Casa',
                    'squadra_ospite': 'Ospite',
                    'goal': 1,
                    'presenza': 1,
                  },
                ),
                'pagination': {'has_more': page == 1},
              };
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester
            .widgetList<PlayerHistoryMatchCard>(
              find.byType(PlayerHistoryMatchCard),
            )
            .length,
        10,
      );
      await tester.scrollUntilVisible(find.byTooltip('Pagina successiva'), 400);
      await tester.tap(find.byTooltip('Pagina successiva'));
      await tester.pumpAndSettle();
      expect(calls, [1, 2]);
      expect(
        tester
            .widgetList<PlayerHistoryMatchCard>(
              find.byType(PlayerHistoryMatchCard),
            )
            .length,
        10,
      );
      expect(find.text('Partita 1'), findsNothing);
      expect(find.text('Partita 11'), findsOneWidget);
      await tester.scrollUntilVisible(find.byTooltip('Pagina precedente'), 400);
      await tester.tap(find.byTooltip('Pagina precedente'));
      await tester.pumpAndSettle();
      expect(calls, [1, 2, 1]);
      expect(find.text('Partita 1'), findsOneWidget);
    });
  }
}
