import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart' show MatchPage;
import 'package:tornei_old_school/match_share.dart';
import 'package:tornei_old_school/tournament_page.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/home_next_match.dart';
import 'package:tornei_old_school/theme.dart';

class NextApi extends DemoApi {
  int calls = 0;
  bool fail = false, linked = true;
  Completer<dynamic>? pending;
  bool includeLast = false;
  bool failEvents = false;
  int eventCalls = 0;
  Map<String, dynamic>? lastMatch;
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
    if (path == 'api/get_eventi_partita.php') {
      eventCalls++;
      if (failEvents) throw const ApiError('offline');
    }
    if (path == 'api/mobile/v1/player_stats.php') {
      calls++;
      expect(query, {'section': 'calcio', 'view': 'upcoming'});
      if (fail) throw const ApiError('Errore di rete');
      if (pending != null) return pending!.future;
      return {
        'player': linked ? {'id': 10} : null,
        'upcoming_matches': upcoming,
        if (includeLast) 'last_match': lastMatch,
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
    'Last played match shows personal performance and opens the report',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = NextApi()..includeLast = true;
      api.lastMatch = {
        ...api.upcoming.first,
        'id': 7,
        'giocata': 1,
        'squadra_casa': 'Ultima Casa',
        'squadra_ospite': 'Ultima Ospite',
        'gol_casa': 3,
        'gol_ospite': 2,
        'goal': 2,
        'assist': 1,
        'voto': '7.5',
        'logo_casa': '/img/last-home.png',
        'logo_ospite': '/img/last-away.png',
      };
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('LA TUA ULTIMA PARTITA'), 150);
      expect(find.text('Ultima Casa'), findsOneWidget);
      expect(find.text('Ultima Ospite'), findsOneWidget);
      expect(find.text('Gol'), findsOneWidget);
      expect(find.text('1 assist'), findsOneWidget);
      expect(find.text('Voto'), findsOneWidget);
      expect(find.text('7,5'), findsOneWidget);
      expect(
        tester
            .widgetList<SiteImage>(find.byType(SiteImage))
            .map((image) => image.path),
        containsAll(['/img/last-home.png', '/img/last-away.png']),
      );
      await tester.scrollUntilVisible(
        find.text('Vedi risultato e prestazione').hitTestable(),
        100,
      );
      await tester.tap(find.text('Vedi risultato e prestazione'));
      await tester.pumpAndSettle();
      expect(tester.widget<MatchPage>(find.byType(MatchPage)).id, '7');
      expect(tester.takeException(), isNull);
    },
  );

  for (final dark in [false, true]) {
    testWidgets(
      'Last match keeps crests and performance readable with large text dark=$dark',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 900));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = NextApi()..includeLast = true;
        api.lastMatch = {
          ...api.upcoming.first,
          'squadra_casa': 'Squadra di casa con nome molto lungo',
          'squadra_ospite': 'Squadra ospite con nome molto lungo',
          'gol_casa': 0,
          'gol_ospite': 0,
          'goal': 0,
          'voto': null,
        };
        api.upcoming = [];
        final auth = DemoAuth(api);
        await auth.login();
        await tester.pumpWidget(
          MaterialApp(
            theme: dark ? siteDarkTheme() : siteTheme(),
            builder: (context, child) => AppAuthScope(
              auth: auth,
              child: MediaQuery(
                data: MediaQuery.of(context)
                    .copyWith(textScaler: const TextScaler.linear(2)),
                child: child!,
              ),
            ),
            home: Scaffold(
              body: ListView(
                children: [
                  HomeNextMatch(auth: auth, section: 'calcio', revision: 0),
                ],
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(find.text('Voto'), 150);
        expect(find.text('—'), findsOneWidget);
        expect(find.text('Assist'), findsNothing);
        expect(find.byType(SiteImage), findsNWidgets(2));
        expect(tester.takeException(), isNull);
      },
    );
  }

  for (final fail in [false, true]) {
    testWidgets(
      'Last match shares directly from Home with load failure=$fail',
      (tester) async {
        final api = NextApi()
          ..includeLast = true
          ..failEvents = fail;
        api.lastMatch = {
          ...api.upcoming.first,
          'id': 7,
          'giocata': 1,
          'gol_casa': 5,
          'gol_ospite': 10,
          'goal': 2,
          'voto': 7,
        };
        api.upcoming = [];
        final auth = DemoAuth(api);
        await auth.login();
        await show(tester, auth);
        await tester.pumpAndSettle();
        expect(api.eventCalls, 0);
        await tester.scrollUntilVisible(
          find.text('Condividi risultato').hitTestable(),
          150,
        );
        await tester.tap(find.text('Condividi risultato'));
        await tester.pumpAndSettle();
        expect(api.eventCalls, 1);
        if (fail) {
          expect(find.byType(MatchSharePage), findsNothing);
          expect(
            find.text('Impossibile preparare la condivisione. Riprova.'),
            findsOneWidget,
          );
        } else {
          expect(find.byType(MatchPage), findsNothing);
          final page = tester.widget<MatchSharePage>(
            find.byType(MatchSharePage),
          );
          expect(page.id, '7');
          expect(page.match['gol_ospite'], 10);
          expect(page.events, isNotEmpty);
        }
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'Linked player without past appearances gets an honest empty state',
    (tester) async {
      final api = NextApi()
        ..includeLast = true
        ..upcoming = [];
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      await tester.pumpAndSettle();
      expect(find.text('LA TUA ULTIMA PARTITA'), findsOneWidget);
      expect(
        find.text('Non risultano ancora partite giocate con una tua presenza.'),
        findsOneWidget,
      );
      expect(find.text('Vedi risultato e prestazione'), findsNothing);
    },
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
    'Unlinked profile invites linking; undated match has honest schedule',
    (tester) async {
      final api = NextApi()..linked = false;
      final auth = DemoAuth(api);
      await auth.login();
      await show(tester, auth);
      await tester.pumpAndSettle();
      await tester.pump();
      await tester.pumpAndSettle();
      expect(find.text('Collega il tuo giocatore'), findsOneWidget);
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
    if (const bool.fromEnvironment('CAPTURE_LAST_MATCH')) {
      api.includeLast = true;
      api.lastMatch = {
        ...api.upcoming.first,
        'giocata': 1,
        'gol_casa': 3,
        'gol_ospite': 2,
        'goal': 2,
        'voto': 7.5,
        'assist': 1,
      };
    }
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
    if (const bool.fromEnvironment('CAPTURE_LAST_MATCH')) {
      await tester.scrollUntilVisible(
        find.text('LA TUA ULTIMA PARTITA').hitTestable(),
        150,
      );
      await tester.ensureVisible(find.text('LA TUA ULTIMA PARTITA'));
      await tester.pumpAndSettle();
    } else {
      expect(
        tester.getTopLeft(find.text('LA TUA PROSSIMA PARTITA')).dy,
        lessThan(
          tester.getTopLeft(find.text('Classifica giocatori totale')).dy,
        ),
      );
    }
    if (const bool.fromEnvironment('CAPTURE_HOME') ||
        const bool.fromEnvironment('CAPTURE_LAST_MATCH')) {
      await tester.runAsync(() async {
        final boundary =
            key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
        final image = await boundary.toImage(pixelRatio: 2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await Directory('build/home-review').create(recursive: true);
        final name = const bool.fromEnvironment('CAPTURE_LAST_MATCH')
            ? 'last-match'
            : const bool.fromEnvironment('CAPTURE_HOME_DARK')
            ? 'next-match-dark'
            : 'next-match';
        await File('build/home-review/$name.png')
            .writeAsBytes(bytes!.buffer.asUint8List());
        image.dispose();
      });
      await tester.pump(const Duration(milliseconds: 200));
      await tester.pumpAndSettle();
    }
    expect(tester.takeException(), isNull);
  });
}
