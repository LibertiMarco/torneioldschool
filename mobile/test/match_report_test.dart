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
        },
      ];
    }
    return result;
  }
}

void main() {
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
      find.descendant(of: away, matching: find.text('2 gol')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}
