import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/details.dart';

class TwoTeamReportApi extends DemoApi {
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    String? accessToken,
    List<ApiUpload>? uploads,
    Map<String, dynamic>? body,
  }) async {
    final result = await super.request(
      path,
      query: query,
      accessToken: accessToken,
      body: body,
    );
    if (path == 'api/get_eventi_partita.php') {
      final rows = List<Map<String, dynamic>>.from(result as List);
      return [
        ...rows,
        {
          ...rows.first,
          'nome': 'Marco',
          'cognome': 'Ospite',
          'squadra': 'Real Academy',
          'cartellino_giallo': 1,
          'cartellino_rosso': 1,
        },
      ];
    }
    return result;
  }
}

class FilteredReportApi extends TwoTeamReportApi {
  FilteredReportApi({this.noEvents = false});
  final bool noEvents;
  int eventRequests = 0;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    String? accessToken,
    List<ApiUpload>? uploads,
    Map<String, dynamic>? body,
  }) async {
    final result = await super.request(
      path,
      query: query,
      accessToken: accessToken,
      uploads: uploads,
      body: body,
    );
    if (path != 'api/get_eventi_partita.php') return result;
    eventRequests++;
    final base = {
      ...(result as List).first as Map<String, dynamic>,
      'goal': 0,
      'cartellino_giallo': 0,
      'cartellino_rosso': 0,
      'is_captain': 0,
    };
    final rows = <Map<String, dynamic>>[
      {
        ...base,
        'nome': 'Solo',
        'cognome': 'Gol',
        'goal': '2',
        'ruolo_squadra': 'POR',
        'is_captain': '1',
      },
      {
        ...base,
        'nome': 'Solo',
        'cognome': 'Giallo',
        'goal': 0,
        'cartellino_giallo': '1',
        'squadra': 'Real Academy',
      },
      {
        ...base,
        'nome': 'Solo',
        'cognome': 'Rosso',
        'cartellino_rosso': 1,
        'squadra': 'Altra squadra',
      },
      {
        ...base,
        'nome': 'Senza',
        'cognome': 'Eventi',
        'voto': 7,
        'ruolo_squadra': '',
        'ruolo': 'Portiere',
      },
      {
        ...base,
        'nome': 'Solo',
        'cognome': 'Assist',
        'assist': 1,
        'ruolo_squadra': 'Difensore',
        'ruolo': 'Portiere',
        'squadra': 'Real Academy',
      },
    ];
    return noEvents
        ? rows
              .map(
                (row) => {
                  ...row,
                  'goal': 0,
                  'cartellino_giallo': 0,
                  'cartellino_rosso': 0,
                },
              )
              .toList()
        : rows;
  }
}

class GoalkeeperOrderApi extends FilteredReportApi {
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    String? accessToken,
    List<ApiUpload>? uploads,
    Map<String, dynamic>? body,
  }) async {
    final result = await super.request(
      path,
      query: query,
      accessToken: accessToken,
      uploads: uploads,
      body: body,
    );
    if (path != 'api/get_eventi_partita.php') return result;
    return [
      for (final row in result as List<Map<String, dynamic>>)
        {
          ...row,
          if (row['cognome'] == 'Gol') 'ruolo_squadra': 'Attaccante',
          if (row['cognome'] == 'Assist') 'ruolo_squadra': 'GK',
        },
    ];
  }
}

void main() {
  testWidgets(
    'All puts each team goalkeeper first and preserves the remaining order',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1100));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = GoalkeeperOrderApi();
      await tester.pumpWidget(
        MaterialApp(
          home: MatchPage(api: api, id: '1'),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Tutti').hitTestable(), 200);
      await tester.pumpAndSettle();
      expect(find.text('Senza Eventi'), findsNothing);
      expect(find.text('Solo Assist'), findsNothing);
      await tester.tap(find.text('Tutti'));
      await tester.pumpAndSettle();
      final home = find.byKey(const ValueKey('match-report-CASA'));
      final away = find.byKey(const ValueKey('match-report-OSPITE'));
      for (final pair in [
        (home, 'Senza Eventi', 'Solo Gol'),
        (away, 'Solo Assist', 'Solo Giallo'),
      ]) {
        final goalkeeper = find.descendant(
          of: pair.$1,
          matching: find.text(pair.$2),
        );
        final outfield = find.descendant(
          of: pair.$1,
          matching: find.text(pair.$3),
        );
        expect(goalkeeper, findsOneWidget);
        expect(outfield, findsOneWidget);
        expect(
          tester.getTopLeft(goalkeeper).dy,
          lessThan(tester.getTopLeft(outfield).dy),
        );
      }
      expect(api.eventRequests, 1);
      await tester.tap(find.text('Con eventi'));
      await tester.pumpAndSettle();
      expect(find.text('Senza Eventi'), findsNothing);
      expect(find.text('Solo Assist'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Match report defaults to goals and cards; All includes the other players without reloading',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = FilteredReportApi();
      await tester.pumpWidget(
        MaterialApp(
          home: MatchPage(api: api, id: '1'),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Con eventi').hitTestable(),
        200,
      );
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Con eventi'))
            .selected,
        true,
      );
      expect(find.text('Solo Gol'), findsOneWidget);
      expect(find.text('GK'), findsOneWidget);
      expect(find.text('C'), findsOneWidget);
      expect(find.byTooltip('Portiere'), findsOneWidget);
      expect(find.byTooltip('Capitano'), findsOneWidget);
      expect(find.text('Solo Giallo'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Solo Rosso').hitTestable(),
        100,
      );
      await tester.pumpAndSettle();
      expect(find.text('Solo Rosso'), findsOneWidget);
      expect(find.text('Senza Eventi'), findsNothing);
      expect(find.text('Solo Assist'), findsNothing);
      await tester.scrollUntilVisible(find.text('Tutti').hitTestable(), -150);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tutti'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Tutti'))
            .selected,
        true,
      );
      expect(find.text('Senza Eventi'), findsOneWidget);
      expect(find.text('Solo Assist'), findsOneWidget);
      expect(find.text('GK'), findsNWidgets(2));
      expect(find.text('C'), findsOneWidget);
      expect(api.eventRequests, 1);
      await tester.tap(find.text('Con eventi'));
      await tester.pumpAndSettle();
      expect(find.text('Senza Eventi'), findsNothing);
      expect(find.text('Solo Assist'), findsNothing);
      expect(api.eventRequests, 1);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Event-free report still lets All show players and votes', (
    tester,
  ) async {
    final api = FilteredReportApi(noEvents: true);
    await tester.pumpWidget(
      MaterialApp(
        home: MatchPage(api: api, id: '1'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Tutti').hitTestable(), 200);
    await tester.pumpAndSettle();
    expect(find.text('Referto non ancora disponibile.'), findsNothing);
    expect(find.text('Senza Eventi'), findsNothing);
    expect(
      find.text(
        'Nessun gol o cartellino. Tocca “Tutti” per vedere tutti i giocatori.',
      ),
      findsOneWidget,
    );
    await tester.tap(find.text('Tutti'));
    await tester.pumpAndSettle();
    expect(find.text('Senza Eventi'), findsOneWidget);
    expect(api.eventRequests, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Match reports keep home left and away right on small phones', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        home: MatchPage(api: TwoTeamReportApi(), id: '1'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Referto giocatori'), 200);
    await tester.pumpAndSettle();
    final home = find.byKey(const ValueKey('match-report-CASA'));
    final away = find.byKey(const ValueKey('match-report-OSPITE'));
    expect(home, findsOneWidget);
    expect(away, findsOneWidget);
    expect(tester.getTopLeft(home).dx, lessThan(tester.getTopLeft(away).dx));
    expect(tester.getTopLeft(home).dy, tester.getTopLeft(away).dy);
    expect(
      find.descendant(of: home, matching: find.text('Luca Rossi')),
      findsOneWidget,
    );
    expect(
      find.descendant(of: away, matching: find.text('Marco Ospite')),
      findsOneWidget,
    );
    expect(
      find.descendant(
        of: away,
        matching: find.byWidgetPredicate(
          (w) =>
              w is Image &&
              w.image is AssetImage &&
              (w.image as AssetImage).assetName == 'assets/emoji/football.png',
        ),
      ),
      findsNWidgets(2),
    );
    expect(
      find.descendant(of: away, matching: find.byTooltip('Ammonito')),
      findsOneWidget,
    );
    expect(
      find.descendant(of: away, matching: find.byTooltip('Espulso')),
      findsOneWidget,
    );
    expect(find.text('0 assist'), findsNothing);
    expect(find.text('0 gol'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
