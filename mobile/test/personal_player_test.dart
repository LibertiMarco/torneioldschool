import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/personal_player_page.dart';
import 'package:tornei_old_school/player_history.dart';
import 'package:tornei_old_school/theme.dart';

class PersonalApi extends DemoApi {
  final queries = <Map<String, String>>[];
  bool linked = true;
  bool fail = false;
  bool empty = false;

  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/player_stats.php') {
      queries.add({...?query});
      if (fail) throw const ApiError('Statistiche non disponibili.');
      if (!linked) return {'player': null, 'teams': []};
      if (empty) {
        return {
          'player': {
            'id': 10,
            'nome': 'Luca',
            'cognome': 'Rossi',
            'presenze': 0,
            'reti': 0,
          },
          'teams': [],
        };
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

Future<void> captureProfile(
  WidgetTester tester,
  GlobalKey key,
  String name,
) async {
  if (!const bool.fromEnvironment('CAPTURE_PROFILE')) return;
  final boundary =
      key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory('build/profile-review').create(recursive: true);
    await File('build/profile-review/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final roboto = FontLoader('Roboto')
      ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf'));
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await roboto.load();
    await icons.load();
  });
  testWidgets(
    'Bottom profile tab opens own authenticated stats and history at 320px',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = PersonalApi();
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(TosApp(api: api, auth: auth));
      await tester.pumpAndSettle();
      expect(find.byType(NavigationDestination), findsNWidgets(5));
      await tester.tap(find.text('Profilo'));
      await tester.pumpAndSettle();
      expect(find.byType(PersonalPlayerPage), findsOneWidget);
      expect(api.queries.single, {'section': 'calcio'});
      expect(find.text('Luca Rossi'), findsOneWidget);
      expect(find.byType(AppBar), findsOneWidget);
      expect(find.text('Profilo'), findsOneWidget);
      await tester.tap(find.text('Esport'));
      await tester.pumpAndSettle();
      expect(api.queries.last, {'section': 'esport'});
      await tester.scrollUntilVisible(
        find.text('Storico prestazioni'),
        100,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Storico prestazioni'));
      await tester.pumpAndSettle();
      expect(find.byType(PlayerHistoryPage), findsOneWidget);
      expect(api.queries.last, {
        'section': 'esport',
        'type': 'presenze',
        'page': '1',
      });
      await tester.tap(find.text('Marcatori'));
      await tester.pumpAndSettle();
      expect(api.queries.last, {
        'section': 'esport',
        'type': 'gol',
        'page': '1',
      });
      expect(api.queries.every((q) => !q.containsKey('giocatore_id')), true);
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Account'));
      await tester.pumpAndSettle();
      expect(find.text('Le mie statistiche'), findsNothing);
      expect(find.text('Apri le mie statistiche'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Guest can log in from profile; logout clears personal data', (
    tester,
  ) async {
    final api = PersonalApi();
    final auth = DemoAuth(api);
    await tester.pumpWidget(TosApp(api: api, auth: auth));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Profilo'));
    await tester.pumpAndSettle();
    expect(api.queries, isEmpty);
    await tester.scrollUntilVisible(find.text('Accedi al tuo profilo'), 100);
    await tester.ensureVisible(find.text('Accedi al tuo profilo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Accedi al tuo profilo'));
    await tester.pumpAndSettle();
    expect(find.text('Luca Rossi'), findsOneWidget);
    await auth.logout();
    await tester.pumpAndSettle();
    expect(find.text('Luca Rossi'), findsNothing);
    await tester.scrollUntilVisible(find.text('Accedi al tuo profilo'), 100);
    expect(find.text('Accedi al tuo profilo'), findsOneWidget);
    expect(find.text('Gestione'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Unlinked player state can recheck association without choosing an ID',
    (tester) async {
      final api = PersonalApi()..linked = false;
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(TosApp(api: api, auth: auth));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Profilo'));
      await tester.pumpAndSettle();
      expect(find.text('La tua scheda ti aspetta'), findsOneWidget);
      api.linked = true;
      await tester.scrollUntilVisible(find.text('Verifica associazione'), 100);
      await tester.tap(find.text('Verifica associazione'));
      await tester.pumpAndSettle();
      expect(find.text('Luca Rossi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Failed personal request offers retry; zeros and absent teams remain truthful',
    (tester) async {
      final api = PersonalApi()..fail = true;
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: Scaffold(
            body: PersonalPlayerPage(auth: auth, section: 'calcio'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Statistiche non disponibili.'), findsOneWidget);
      api.fail = false;
      api.empty = true;
      await tester.tap(find.text('Riprova'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Presenze'), 100);
      expect(find.text('0'), findsWidgets);
      expect(find.text('Media voto'), findsNothing);
      await tester.scrollUntilVisible(
        find.text('Non risultano squadre associate in questa sezione.'),
        100,
      );
      expect(find.text('Gol per squadra'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [320.0, 390.0, 430.0]) {
    testWidgets('Profile layout and team graph at ${width.toInt()}px', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = PersonalApi();
      final auth = DemoAuth(api);
      await auth.login();
      final captureKey = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: captureKey,
          child: TosApp(api: api, auth: auth),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Profilo'));
      await tester.pumpAndSettle();
      await captureProfile(tester, captureKey, 'profile-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.text('Gol per squadra'),
        100,
        scrollable: find.byType(Scrollable).first,
      );
      final requestCount = api.queries.length;
      await tester.ensureVisible(find.widgetWithText(ChoiceChip, 'Presenze'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Presenze'));
      await tester.pumpAndSettle();
      expect(find.text('Presenze per squadra'), findsOneWidget);
      expect(api.queries.length, requestCount);
      await tester.tap(find.widgetWithText(ChoiceChip, 'Gol'));
      await tester.pumpAndSettle();
      expect(find.text('Gol per squadra'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Vedi squadra'),
        100,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Diffidato'), findsOneWidget);
      expect(find.text('Presenze: 3'), findsOneWidget);
      await tester.ensureVisible(find.text('Vedi squadra'));
      await tester.pumpAndSettle();
      await captureProfile(tester, captureKey, 'teams-${width.toInt()}');
      await tester.tap(find.text('Vedi squadra'));
      await tester.pumpAndSettle();
      expect(find.text('Rosa'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('Profile supports large text without horizontal overflow', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = PersonalApi();
    final auth = DemoAuth(api);
    await auth.login();
    await tester.pumpWidget(
      MaterialApp(
        theme: siteTheme(),
        home: MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(2)),
          child: Scaffold(
            body: PersonalPlayerPage(auth: auth, section: 'calcio'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Vedi squadra'), 100);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Home retains rankings without a duplicate personal dashboard', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HomeDashboard(
            api: DemoApi(),
            section: 'calcio',
            onTournaments: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Classifica giocatori totale'), findsOneWidget);
    expect(find.text('Le mie statistiche'), findsNothing);
  });
}
