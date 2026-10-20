import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_favorites.dart';
import 'package:tornei_old_school/team_page.dart';
import 'package:tornei_old_school/tournament_page.dart';
import 'package:tornei_old_school/theme.dart';

class FavoritesApi extends DemoApi {
  int reads = 0, writes = 0;
  bool fail = false;
  Completer<dynamic>? pending;
  List<Map<String, dynamic>>? items;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/follow.php') {
      if (body != null) writes++;
      if (query?['list'] == '1') {
        reads++;
        if (fail) throw const ApiError('Offline');
        if (pending != null) return pending!.future;
        if (items != null) return {'items': items};
      }
    }
    return super.request(
      path,
      query: query,
      body: body,
      accessToken: accessToken,
      uploads: uploads,
    );
  }
}

Map<String, dynamic> favorite(int id, {bool team = false}) => {
  'tipo': team ? 'squadra' : 'torneo',
  'torneo_slug': 'demo',
  'torneo_nome': 'Coppa $id',
  'torneo_id': id,
  'filetorneo': 'demo.php',
  'torneo_logo': '',
  'squadra_nome': 'Squadra $id',
  'squadra_logo': '',
};

void main() {
  Future<void> show(
    WidgetTester tester,
    DemoAuth auth, {
    bool dark = false,
    double scale = 1,
    GlobalKey? capture,
  }) async {
    await tester.pumpWidget(
      RepaintBoundary(
        key: capture,
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: dark ? siteDarkTheme() : siteTheme(),
          builder: (context, child) => AppAuthScope(auth: auth, child: child!),
          home: MediaQuery(
            data: MediaQueryData(textScaler: TextScaler.linear(scale)),
            child: Scaffold(
              body: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: HomeFavorites(auth: auth, section: 'calcio'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> tap(WidgetTester tester, Finder target) async {
    await tester.ensureVisible(target);
    await tester.pumpAndSettle();
    await tester.tap(target);
    await tester.pumpAndSettle();
  }

  testWidgets(
    'No guest request; login shows existing follows and logout removes them',
    (tester) async {
      final api = FavoritesApi()
        ..items = [favorite(1), favorite(2, team: true)];
      final auth = DemoAuth(api);
      await show(tester, auth);
      expect(api.reads, 0);
      await auth.login();
      await tester.pumpAndSettle();
      expect(find.text('Coppa 1'), findsOneWidget);
      expect(find.text('Squadra 2'), findsOneWidget);
      expect(api.writes, 0);
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.text('Squadra 2'), findsNothing);
      expect(find.text('I tuoi preferiti'), findsNothing);
    },
  );

  testWidgets(
    'Shortcuts open the correct team and tournament; full list shows all',
    (tester) async {
      final api = FavoritesApi()
        ..items = [for (var i = 1; i <= 5; i++) favorite(i, team: i == 2)];
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      expect(find.text('Coppa 4'), findsNothing);
      await tap(tester, find.text('Squadra 2'));
      expect(
        tester.widget<TeamPage>(find.byType(TeamPage)).team['nome'],
        'Squadra 2',
      );
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tap(tester, find.text('Coppa 1'));
      expect(
        tester
            .widget<TournamentPage>(find.byType(TournamentPage))
            .tournament['id'],
        1,
      );
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tap(tester, find.text('Mostra tutti (5)'));
      expect(find.text('Coppa 5'), findsOneWidget);
    },
  );

  testWidgets(
    'Manage searches, saves a team and removes it; changes refresh Home',
    (tester) async {
      final api = FavoritesApi();
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      await tap(tester, find.text('Gestisci'));
      await tester.enterText(find.byType(TextField), 'inesistente');
      await tester.pumpAndSettle();
      expect(find.text('Nessun torneo trovato.'), findsOneWidget);
      await tester.enterText(find.byType(TextField), '');
      await tester.pumpAndSettle();
      await tap(tester, find.byType(ExpansionTile));
      await tap(tester, find.text('Segui'));
      expect(api.writes, 0);
      await tap(tester, find.text('Tutte le squadre'));
      await tap(tester, find.text('Old School FC'));
      await tap(tester, find.text('Salva'));
      expect(api.writes, 1);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Old School FC'), findsOneWidget);
      await tap(tester, find.text('Gestisci'));
      await tap(tester, find.byType(ExpansionTile));
      await tap(tester, find.byTooltip('Scegli le squadre da seguire'));
      await tap(tester, find.text('Rimuovi'));
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Old School FC'), findsNothing);
      expect(api.writes, 2);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Listing error retries without writing preferences', (
    tester,
  ) async {
    final api = FavoritesApi()..fail = true;
    final auth = DemoAuth(api);
    await auth.login();
    await show(tester, auth);
    expect(find.text('Riprova'), findsOneWidget);
    api.fail = false;
    await tap(tester, find.text('Riprova'));
    expect(find.text('Gestisci'), findsOneWidget);
    expect(api.writes, 0);
  });

  testWidgets('Late response after logout never displays personal shortcuts', (
    tester,
  ) async {
    final api = FavoritesApi()..pending = Completer<dynamic>();
    final auth = DemoAuth(api);
    await auth.login();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HomeFavorites(auth: auth, section: 'calcio'),
        ),
      ),
    );
    await tester.pump();
    await auth.logout();
    await tester.pump();
    api.pending!.complete({
      'items': [favorite(1)],
    });
    await tester.pumpAndSettle();
    expect(find.text('Coppa 1'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final dark in [false, true]) {
    testWidgets('Favorites fit 320px and large text, dark=$dark', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = FavoritesApi()
        ..items = [favorite(1), favorite(2, team: true)];
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth, dark: dark, scale: 2);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('Capture favorites with local fixture', (tester) async {
    if (!const bool.fromEnvironment('CAPTURE_FAVORITES')) return;
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    final api = FavoritesApi()..items = [favorite(1), favorite(2, team: true)];
    final auth = DemoAuth(api);
    await auth.login();
    final key = GlobalKey();
    await show(tester, auth, capture: key);
    await tester.runAsync(() async {
      final boundary =
          key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final image = await boundary.toImage(pixelRatio: 2);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      await Directory('build/home-review').create(recursive: true);
      await File('build/home-review/favorites.png')
          .writeAsBytes(bytes!.buffer.asUint8List());
      image.dispose();
    });
  });
}
