import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/tournament_page.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/home_next_match.dart';
import 'package:tornei_old_school/theme.dart';

class NextApi extends DemoApi {
  int calls = 0;
  bool fail = false, linked = true;
  Completer<dynamic>? pending;
  List<Map<String, dynamic>> upcoming = [
    {
      'id': 12,
      'squadra_casa': 'Old School FC',
      'squadra_ospite': 'Atletico Roma',
      'logo_casa': '/img/home-fixture.png',
      'logo_ospite': '/img/away-fixture.png',
      'torneo_nome': 'Torneo autunnale',
      'torneo': 'legacy-name',
      'torneo_file': 'demo.php',
      'torneo_id': 1,
      'data_partita': '2026-10-25',
      'ora_partita': '20:30:00',
      'campo': 'Centro sportivo Old School',
    },
  ];

  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/player_stats.php') {
      calls++;
      expect(query, {'section': 'calcio'});
      if (fail) throw const ApiError('Errore di rete');
      if (pending != null) return pending!.future;
      return {
        'player': linked ? {'id': 10} : null,
        'upcoming_matches': upcoming,
      };
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

void main() {
  Future<void> show(WidgetTester tester, DemoAuth auth, {int revision = 0}) =>
      tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => AppAuthScope(auth: auth, child: child!),
          home: Scaffold(
            body: ListView(
              children: [
                HomeNextMatch(
                  auth: auth,
                  section: 'calcio',
                  revision: revision,
                ),
              ],
            ),
          ),
        ),
      );

  testWidgets(
    'Guest makes no personal request; login loads and logout clears it',
    (tester) async {
      final api = NextApi();
      final auth = DemoAuth(api);
      await show(tester, auth);
      await tester.pumpAndSettle();
      expect(api.calls, 0);
      await auth.login();
      await tester.pumpAndSettle();
      expect(find.text('Old School FC'), findsOneWidget);
      expect(find.text('Atletico Roma'), findsOneWidget);
      expect(find.byType(SiteImage), findsNWidgets(2));
      expect(
        tester.widgetList<SiteImage>(find.byType(SiteImage)).map((w) => w.path),
        ['/img/home-fixture.png', '/img/away-fixture.png'],
      );
      expect(find.text('25/10/2026 · 20:30'), findsOneWidget);
      expect(find.text('Centro sportivo Old School'), findsOneWidget);
      const launcher = MethodChannel('plugins.flutter.io/url_launcher');
      String? launched;
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(launcher, (
        call,
      ) async {
        if (call.method == 'launch') {
          launched = (call.arguments as Map)['url'] as String;
        }
        return true;
      });
      addTearDown(
        () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          launcher,
          null,
        ),
      );
      await tester.tap(find.text('Centro sportivo Old School'));
      await tester.pumpAndSettle();
      final navigation = Uri.parse(launched!);
      expect(navigation.host, 'www.google.com');
      expect(navigation.queryParameters['query'], 'Centro sportivo Old School');
      await tester.tap(find.text('Vai al torneo'));
      await tester.pumpAndSettle();
      final page = tester.widget<TournamentPage>(find.byType(TournamentPage));
      expect(TosApi.tournamentSlug(page.tournament), 'demo');
      expect(page.initialTab, 'Calendario');
      await tester.pageBack();
      await tester.pumpAndSettle();
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.text('Old School FC'), findsNothing);
    },
  );

  testWidgets('Failed load retries; refresh revision replaces the match', (
    tester,
  ) async {
    final api = NextApi()..fail = true;
    final auth = DemoAuth(api);
    await auth.login();
    await show(tester, auth);
    await tester.pumpAndSettle();
    expect(find.text('Riprova'), findsOneWidget);
    api.fail = false;
    await tester.tap(find.text('Riprova'));
    await tester.pumpAndSettle();
    expect(find.text('Old School FC'), findsOneWidget);
    api.upcoming = [];
    await show(tester, auth, revision: 1);
    await tester.pumpAndSettle();
    expect(api.calls, 3);
    expect(find.text('Old School FC'), findsNothing);
    expect(
      find.text('Non ci sono partite da disputare per le tue squadre.'),
      findsOneWidget,
    );
    expect(find.text('Vai al torneo'), findsNothing);
  });

  testWidgets(
    'Unlinked profile hides card; undated match has honest schedule',
    (tester) async {
      final api = NextApi()..linked = false;
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      await tester.pumpAndSettle();
      expect(find.byType(Card), findsNothing);
      api.linked = true;
      api.upcoming.first.remove('data_partita');
      api.upcoming.first.remove('ora_partita');
      api.upcoming.first.remove('campo');
      await show(tester, auth, revision: 1);
      await tester.pumpAndSettle();
      expect(find.text('Data da definire'), findsOneWidget);
      expect(find.textContaining('null'), findsNothing);
      expect(find.text('Centro sportivo Old School'), findsNothing);
    },
  );

  testWidgets('Late personal response stays hidden after logout', (
    tester,
  ) async {
    final api = NextApi()..pending = Completer<dynamic>();
    final auth = DemoAuth(api);
    await auth.login();
    await show(tester, auth);
    await tester.pump();
    await auth.logout();
    await tester.pump();
    api.pending!.complete({
      'player': {'id': 10},
      'upcoming_matches': api.upcoming,
    });
    await tester.pumpAndSettle();
    expect(find.byType(Card), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final dark in [false, true]) {
    testWidgets('Home card at 320px with large text, dark=$dark', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = NextApi();
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          theme: dark ? siteDarkTheme() : siteTheme(),
          home: MediaQuery(
            data: const MediaQueryData(textScaler: TextScaler.linear(2)),
            child: Scaffold(
              body: HomeDashboard(
                api: api,
                auth: auth,
                section: 'calcio',
                onTournaments: () {},
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('LA TUA PROSSIMA PARTITA'), findsOneWidget);
      if (dark) {
        final colors = siteDarkTheme().colorScheme;
        final card = tester.widget<Card>(
          find.widgetWithText(Card, 'LA TUA PROSSIMA PARTITA'),
        );
        expect(card.color, colors.surfaceContainer);
        expect(
          DefaultTextStyle.of(tester.element(find.text('Old School FC')))
              .style
              .color,
          colors.onSurface,
        );
      }
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('Home shows personal match above rankings', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final fonts = FontLoader('Roboto')
      ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf'));
    await fonts.load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    final api = NextApi();
    final auth = DemoAuth(api);
    await auth.login();
    final key = GlobalKey();
    await tester.pumpWidget(
      RepaintBoundary(
        key: key,
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: const bool.fromEnvironment('CAPTURE_HOME_DARK')
              ? siteDarkTheme()
              : siteTheme(),
          home: Scaffold(
            body: HomeDashboard(
              api: api,
              auth: auth,
              section: 'calcio',
              onTournaments: () {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.getTopLeft(find.text('LA TUA PROSSIMA PARTITA')).dy,
      lessThan(tester.getTopLeft(find.text('Classifica giocatori totale')).dy),
    );
    if (const bool.fromEnvironment('CAPTURE_HOME')) {
      await tester.runAsync(() async {
        final boundary =
            key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
        final image = await boundary.toImage(pixelRatio: 2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await Directory('build/home-review').create(recursive: true);
        final name = const bool.fromEnvironment('CAPTURE_HOME_DARK')
            ? 'next-match-dark'
            : 'next-match';
        await File('build/home-review/$name.png')
            .writeAsBytes(bytes!.buffer.asUint8List());
        image.dispose();
      });
    }
    expect(tester.takeException(), isNull);
  });
}
