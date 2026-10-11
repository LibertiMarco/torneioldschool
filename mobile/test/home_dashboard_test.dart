import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/main.dart';

class HomeApi extends DemoApi {
  final orders = <String>[];
  final newsSections = <String>[];
  final hallSections = <String>[];
  final pages = <int>[];
  final pageSizes = <int>[];
  final searches = <String>[];
  bool unavailable = false;
  @override
  Future<Map<String, dynamic>> playerRanking({
    String order = 'gol',
    int page = 1,
    int perPage = 5,
    String search = '',
  }) async {
    orders.add(order);
    pages.add(page);
    pageSizes.add(perPage);
    searches.add(search);
    if (unavailable) throw const ApiError('Ranking non disponibile.');
    return {
      'data': [
        {
          'nome': 'Mario',
          'cognome': 'Rossi',
          'gol': 120,
          'presenze': 250,
          'posizione': 1,
        },
        {
          'nome': 'Luca',
          'cognome': 'Verdi',
          'gol': 120,
          'presenze': 170,
          'posizione': 1,
        },
        {
          'nome': 'Paolo',
          'cognome': 'Blu',
          'gol': 90,
          'presenze': 120,
          'posizione': 3,
        },
      ],
      'pagination': {'page': page, 'total_pages': 2, 'total': 13},
    };
  }

  @override
  Future<List<Map<String, dynamic>>> news(String section, {bool all = false}) {
    newsSections.add(section);
    return super.news(section, all: all);
  }

  @override
  Future<List<Map<String, dynamic>>> hallOfFame(String section) {
    hallSections.add(section);
    return super.hallOfFame(section);
  }
}

void main() {
  testWidgets(
    'Homepage reads all-time ranking with server tie positions and switches order',
    (tester) async {
      final api = HomeApi();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: HomeDashboard(
              api: api,
              section: 'calcio',
              onTournaments: () {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Mario Rossi'), 150);
      await tester.pumpAndSettle();
      expect(find.text('120 gol · 250 presenze'), findsOneWidget);
      expect(find.text('1'), findsNWidgets(2));
      expect(find.text('3'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Presenze'), -100);
      await tester.tap(find.text('Presenze'));
      await tester.pumpAndSettle();
      expect(api.orders.last, 'presenze');
      expect(find.text('250 presenze · 120 gol'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Classifica completa'), 100);
      await tester.tap(find.text('Classifica completa'));
      await tester.pumpAndSettle();
      expect(api.orders.last, 'presenze');
      expect(api.pageSizes.last, 10);
      expect(find.text('Marcatori'), findsOneWidget);
      expect(find.text('Presenze'), findsOneWidget);
      expect(find.text('250 presenze · 120 gol'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.byTooltip('Pagina successiva'),
        150,
        scrollable: find.descendant(
          of: find.descendant(
            of: find.byType(GlobalRankingPage),
            matching: find.byType(ListView),
          ),
          matching: find.byType(Scrollable),
        ),
      );
      await tester.tap(find.byTooltip('Pagina successiva'));
      await tester.pumpAndSettle();
      expect(api.pages.last, 2);
      await tester.enterText(find.byType(TextField), 'Mario');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(api.pages.last, 1);
      expect(api.searches.last, 'Mario');
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Full ranking fits a narrow phone and shows site statistics', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = HomeApi();
    await tester.pumpWidget(MaterialApp(home: GlobalRankingPage(api: api)));
    await tester.pumpAndSettle();
    expect(api.pageSizes.last, 10);
    await tester.scrollUntilVisible(
      find.byTooltip('Pagina successiva'),
      150,
      scrollable: find.descendant(
        of: find.byType(ListView),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Pagina 1 di 2 · 13 giocatori'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PlayerRankingCard(
            api: api,
            row: {
              'nome': 'Mario',
              'cognome': 'Rossi',
              'gol': 120,
              'presenze': 250,
              'ruolo': 'Attaccante',
              'media_voti': '7.50',
              'posizione': 1,
            },
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Attaccante · 120 gol · 250 presenze · Media voto: 7.50'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
  testWidgets('Ranking outage leaves news and hall available', (tester) async {
    final api = HomeApi()..unavailable = true;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HomeDashboard(
            api: api,
            section: 'calcio',
            onTournaments: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('La nuova stagione'), 150);
    await tester.pumpAndSettle();
    expect(api.newsSections.last, 'calcio');
    await tester.scrollUntilVisible(find.text('Old School FC'), 150);
    await tester.pumpAndSettle();
    expect(api.hallSections.last, 'calcio');
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'Esport section persists from homepage to tournaments at phone width',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = HomeApi();
      await tester.pumpWidget(TosApp(api: api, auth: DemoAuth(api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Esport'));
      await tester.pumpAndSettle();
      expect(find.text('Ranking EA FC'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Luca Rossi'), 100);
      await tester.pumpAndSettle();
      expect(find.text('40 pt'), findsOneWidget);
      expect(api.orders, isNot(contains('presenze')));
      await tester.scrollUntilVisible(find.text('La nuova stagione'), 150);
      await tester.pumpAndSettle();
      expect(api.newsSections.last, 'esport');
      await tester.scrollUntilVisible(find.text('Old School FC'), 150);
      await tester.pumpAndSettle();
      expect(api.hallSections.last, 'esport');
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(find.text('Old School Esport Cup'), findsOneWidget);
      expect(find.text('Old School Cup'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  test('Articles decode HTML entities and preserve paragraphs without executing markup', () {
    expect(
      articleText(
        '<p>A &amp; B</p><p>Nuova <b>stagione</b><br>2026</p><script>alert(1)</script>',
      ),
      'A & B\n\nNuova stagione\n2026',
    );
  });
}
