import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';

class HallApi extends DemoApi {
  final sections = <String>[];

  @override
  Future<List<Map<String, dynamic>>> hallOfFame(String section) async {
    sections.add(section);
    return [
      {
        'competizione': 'Coppa storica',
        'sezione': section,
        'anno': 2024,
        'latest_sort_time': 10,
        'latest_record_id': 1,
        'premi': [
          {'premio': 'Vincitore', 'vincitrice': 'Squadra storica'},
        ],
      },
      {
        'competizione': 'Torneo recente con un nome molto lungo',
        'sezione': section,
        'anno': 2026,
        'latest_sort_time': 10,
        'latest_record_id': 2,
        'premi': [
          {'premio': 'Vincitore', 'vincitrice': 'Squadra recente'},
        ],
      },
    ];
  }
}

void main() {
  testWidgets('Selects the latest entry and switches awards at phone width', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = HallApi();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              HallOfFamePanel(api: api, rows: await api.hallOfFame('calcio')),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Squadra recente'), findsOneWidget);
    expect(find.text('Squadra storica'), findsNothing);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coppa storica').last);
    await tester.pumpAndSettle();
    expect(find.text('Squadra storica'), findsOneWidget);
    expect(find.text('Squadra recente'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Both homepage and full hall offer tournaments for the section', (
    tester,
  ) async {
    final api = HallApi();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HomeDashboard(
            api: api,
            section: 'esport',
            onTournaments: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Scegli il torneo'), 200);
    await tester.pumpAndSettle();
    expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);
    await tester.ensureVisible(find.text('Albo d’oro completo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Albo d’oro completo'));
    await tester.pumpAndSettle();
    expect(api.sections, ['esport', 'esport']);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coppa storica').last);
    await tester.pumpAndSettle();
    expect(find.text('Squadra storica'), findsOneWidget);
    expect(find.text('Squadra recente'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Empty hall displays a message without a tournament selector', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HallOfFamePanel(api: HallApi(), rows: const []),
        ),
      ),
    );
    expect(find.text('Nessun risultato disponibile.'), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<String>), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
