import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/theme.dart';
import 'package:tornei_old_school/tournament_page.dart';

class DisciplineApi extends DemoApi {
  final requested = <String>[];
  Completer<List<Map<String, dynamic>>>? pending;
  bool unavailable = false;

  @override
  Future<List<Map<String, dynamic>>> cautionedPlayers(String slug) async {
    requested.add(slug);
    if (unavailable) throw const ApiError('Diffidati non disponibili.');
    if (pending != null) return pending!.future;
    return [];
  }
}

class BrasilApi extends DemoApi {
  @override
  Future<Map<String, dynamic>> tournamentInfo(String slug) async => {
    'nome': 'Brasilerao',
    'sezione': 'calcio',
    'config': {
      'formato': 'campionato',
      'campionato_squadre': 22,
      'totale_squadre': 16,
      'qualificati_gold': 16,
      'qualificati_silver': 8,
    },
    'mobile_layout': {
      'group_mode': 'template',
      'config_overrides': {
        'totale_squadre': 22,
        'qualificati_gold': 16,
        'qualificati_silver': 6,
      },
    },
  };
}

Widget tournament(DemoApi api, {String slug = 'demo'}) => MaterialApp(
  theme: siteTheme(),
  home: TournamentPage(
    api: api,
    tournament: {'nome': slug, 'filetorneo': '$slug.php'},
  ),
);

void main() {
  testWidgets(
    'Diffidati loads the tournament and shows caution days at 320 px',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DisciplineApi()
        ..pending = Completer<List<Map<String, dynamic>>>();
      await tester.pumpWidget(tournament(api));
      await tester.pumpAndSettle();
      expect(api.requested, isEmpty);
      await tester.tap(find.text('Diffidati'));
      await tester.pump();
      expect(api.requested, ['demo']);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      api.pending!.complete([
        {
          'nome': 'Mario',
          'cognome': 'Rossi',
          'squadra': 'Squadra con un nome particolarmente lungo',
          'giornate': [2, null],
        },
      ]);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Giornata non indicata'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Rossi Mario'), findsOneWidget);
      expect(
        find.text('Squadra con un nome particolarmente lungo'),
        findsOneWidget,
      );
      expect(find.text('Giornata 2'), findsOneWidget);
      expect(find.text('1 giocatore'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Diffidati error can be retried without blocking tournament tabs',
    (tester) async {
      final api = DisciplineApi()..unavailable = true;
      await tester.pumpWidget(tournament(api));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Diffidati'));
      await tester.pumpAndSettle();
      expect(find.text('Diffidati non disponibili.'), findsOneWidget);
      api.unavailable = false;
      await tester.ensureVisible(find.text('Riprova'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Riprova'));
      await tester.pumpAndSettle();
      expect(find.text('Nessun giocatore diffidato.'), findsOneWidget);
      expect(api.requested, ['demo', 'demo']);
      await tester.ensureVisible(find.text('Calendario'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Calendario'));
      await tester.pumpAndSettle();
      expect(find.text('Giornata 4'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Brasilerao displays the Silver legend and cup phase', (
    tester,
  ) async {
    await tester.pumpWidget(tournament(BrasilApi(), slug: 'Brasilerao'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('6 posti · Coppa Silver'),
      150,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('16 posti · Coppa Gold'), findsOneWidget);
    await tester.ensureVisible(find.text('Regular season'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Regular season'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coppa Silver').last);
    await tester.pumpAndSettle();
    expect(find.text('Coppa Silver'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
