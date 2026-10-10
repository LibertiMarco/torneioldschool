import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/tournament_list.dart';

Map<String, dynamic> cup(String name, String state, String start, String end) =>
    {
      'nome': name,
      'stato': state,
      'data_inizio': start,
      'data_fine': end,
      'categoria': 'Calcio a 5',
    };

class ArchiveApi extends TosApi {
  ArchiveApi() : super(baseUrl: Uri.parse('https://example.test'));
  @override
  Future<List<Map<String, dynamic>>> tournaments(String section) async =>
      List.generate(
        15,
        (i) => cup(
          'Cup ${i + 1}',
          'terminato',
          '2026-01-01',
          '2026-02-${(i + 1).toString().padLeft(2, '0')}',
        ),
      );
}

void main() {
  test('Website grouping, sorting and missing dates', () {
    final rows = [
      cup('Later', 'in corso', '2026-01-01', '2026-12-01'),
      cup('Missing', 'in corso', '', '0000-00-00'),
      cup('First', ' IN CORSO ', '2026-04-01', '2026-06-01'),
      cup('Tie', 'in corso', '2026-03-01', '2026-06-01'),
      cup('Upcoming later', 'programmato', '2027-04-01', ''),
      cup('Upcoming first', 'programmato', '2027-01-01', ''),
      cup('Old', 'terminato', '', '2024-01-01'),
      cup('Recent', 'terminato', '', '2026-01-01'),
      cup('Hidden', 'other', '', ''),
    ];
    expect(tournamentGroup(rows, 'in corso').map((r) => r['nome']), [
      'First',
      'Tie',
      'Later',
      'Missing',
    ]);
    expect(tournamentGroup(rows, 'programmato').map((r) => r['nome']), [
      'Upcoming first',
      'Upcoming later',
    ]);
    expect(tournamentGroup(rows, 'terminato').map((r) => r['nome']), [
      'Recent',
      'Old',
    ]);
    expect(tournamentDate('2026-02-31'), isNull);
    expect(tournamentDate('0000-00-00'), isNull);
  });
  test('Search ignores accents and includes category and period', () {
    expect(
      (['Calcio a 11', 'Calcio a 5', 'calcio a 8']..sort(naturalCategoryOrder)),
      ['Calcio a 5', 'calcio a 8', 'Calcio a 11'],
    );
    final rows = [cup('Città Cup', 'in corso', '2026-01-01', '2026-06-01')];
    expect(tournamentGroup(rows, 'in corso', search: 'citta'), hasLength(1));
    expect(
      tournamentGroup(
        rows,
        'in corso',
        search: '06/26',
        category: 'calcio a 5',
      ),
      hasLength(1),
    );
    expect(tournamentGroup(rows, 'in corso', category: 'Calcio a 8'), isEmpty);
  });
  testWidgets(
    'Archive loads twelve then allows more; search reaches all archived rows',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TournamentList(api: ArchiveApi(), section: 'calcio'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Tornei terminati (15)'));
      await tester.tap(find.text('Tornei terminati (15)'));
      await tester.pumpAndSettle();
      final list = find.byType(ListView);
      await tester.scrollUntilVisible(
        find.text('Carica altri tornei'),
        400,
        scrollable: find.descendant(
          of: list,
          matching: find.byType(Scrollable),
        ),
      );
      expect(find.text('Cup 1'), findsNothing);
      await tester.tap(find.text('Carica altri tornei'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Cup 1'),
        200,
        scrollable: find.descendant(
          of: list,
          matching: find.byType(Scrollable),
        ),
      );
      await tester.enterText(find.byType(TextField), 'Cup 1');
      await tester.pumpAndSettle();
      expect(find.text('Carica altri tornei'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
