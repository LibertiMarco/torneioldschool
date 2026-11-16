import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/match_share.dart';
import 'package:tornei_old_school/theme.dart';

class ShareMatchApi extends DemoApi {
  ShareMatchApi(this.played);
  final bool played;
  @override
  Future<Map<String, dynamic>> match(String id) async => {
    ...await super.match(id),
    'giocata': played ? 1 : 0,
  };
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf')))
        .load();
  });
  final match = <String, dynamic>{
    'id': 12,
    'torneo': 'Coppa Estate & Amici',
    'torneo_nome': 'Coppa Old School',
    'squadra_casa': 'Old School Football Club con nome lungo',
    'squadra_ospite': 'Atletico Napoli Football Academy',
    'gol_casa': 12,
    'gol_ospite': 12,
    'giocata': 1,
    'decisa_rigori': 1,
    'rigori_casa': 4,
    'rigori_ospite': 3,
    'data_partita': '2026-11-16',
    'giornata': 2,
  };
  final events = List.generate(
    12,
    (i) => <String, dynamic>{
      'nome': 'Giocatore',
      'cognome': 'Numero $i',
      'goal': i == 0 ? 0 : 1,
      'presenza': 1,
      'voto': i.isEven ? 7.5 : null,
      'ruolo_squadra': i == 0 ? 'POR' : 'DIF',
      'is_captain': i == 0 || i == 1 ? 1 : 0,
      'autogol': i == 0 ? 1 : 0,
      'squadra': match[i.isEven ? 'squadra_casa' : 'squadra_ospite'],
    },
  );

  test('shared match URL preserves identity and encodes tournament', () {
    final url = sharedMatchUrl(
      TosApi(baseUrl: Uri.parse('https://example.test/')),
      '12',
      match,
    );
    expect(url.path, '/tornei/partita_eventi.php');
    expect(url.queryParameters, {'id': '12', 'torneo': 'Coppa Estate & Amici'});
  });

  test('share includes non-scorers and excludes explicitly absent players', () {
    final rows = [
      {'nome': 'Presente senza gol', 'presenza': 1, 'goal': 0},
      {'nome': 'Assente', 'presenza': 0, 'goal': 2},
      {'nome': 'Legacy row', 'goal': 0},
    ];
    expect(shareParticipants(rows).map((row) => row['nome']), [
      'Presente senza gol',
      'Legacy row',
    ]);
  });

  for (final played in [false, true]) {
    testWidgets('match offers result sharing only when played=$played', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: MatchPage(api: ShareMatchApi(played), id: '1'),
        ),
      );
      await tester.pumpAndSettle();
      if (played) {
        await tester.scrollUntilVisible(find.text('Condividi risultato'), 150);
        await tester.tap(find.text('Condividi risultato'));
        await tester.pumpAndSettle();
        expect(find.byType(MatchSharePage), findsOneWidget);
        expect(
          tester.widget<MatchSharePage>(find.byType(MatchSharePage)).id,
          '1',
        );
      } else {
        expect(find.text('Condividi risultato'), findsNothing);
      }
      expect(tester.takeException(), isNull);
    });
  }

  for (final story in [false, true]) {
    testWidgets(
      'share artwork $story fits long names, penalties and many scorers',
      (tester) async {
        await tester.binding.setSurfaceSize(Size(400, story ? 720 : 450));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final key = GlobalKey();
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: Scaffold(
              body: SingleChildScrollView(
                child: Center(
                  child: RepaintBoundary(
                    key: key,
                    child: MatchShareArtwork(
                      api: TosApi(baseUrl: Uri.parse('https://example.test/')),
                      match: match,
                      events: events,
                      story: story,
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        await tester.runAsync(
          () => precacheImage(
            const AssetImage('assets/logo_old_school.png'),
            key.currentContext!,
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Rigori 4 – 3'), findsOneWidget);
        expect(find.text('Autogol: 1'), findsOneWidget);
        expect(find.text('POR'), findsOneWidget);
        expect(find.text('C'), findsNWidgets(2));
        for (var i = 0; i < events.length; i++) {
          expect(find.text('Giocatore Numero $i'), findsOneWidget);
        }
        expect(find.text('7,5'), findsNWidgets(6));
        expect(find.text('—'), findsNWidgets(6));
        expect(find.text('Gol'), findsNWidgets(2));
        expect(find.text('Voto'), findsNWidgets(2));
        expect(
          tester.getTopLeft(find.text('Giocatore Numero 0')).dx,
          lessThan(tester.getTopLeft(find.text('Giocatore Numero 1')).dx),
        );
        expect(find.textContaining('nel referto'), findsNothing);
        expect(tester.takeException(), isNull);
        await tester.runAsync(() async {
          final boundary =
              key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
          final image = await boundary.toImage(pixelRatio: 3);
          try {
            expect(image.width, 1080);
            expect(image.height, 1920);
            if (const bool.fromEnvironment('CAPTURE_SHARE')) {
              final data = await image.toByteData(
                format: ui.ImageByteFormat.png,
              );
              await Directory('build/share-review').create(recursive: true);
              await File('build/share-review/${story ? 'story' : 'square'}.png')
                  .writeAsBytes(data!.buffer.asUint8List());
            }
          } finally {
            image.dispose();
          }
        });
      },
    );
  }

  testWidgets(
    'share screen at 320px supports large text and a single story format',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(2)),
            child: child!,
          ),
          home: MatchSharePage(
            api: TosApi(baseUrl: Uri.parse('https://example.test/')),
            id: '12',
            match: match,
            events: events,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Formato storia Instagram'), findsOneWidget);
      expect(
        tester.widget<MatchShareArtwork>(find.byType(MatchShareArtwork)).story,
        isTrue,
      );
      expect(find.text('Condividi immagine'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('large rosters keep every player in one fixed story', (
    tester,
  ) async {
    final players = List.generate(
      40,
      (i) => <String, dynamic>{
        'nome': 'Nome molto lungo',
        'cognome': 'Giocatore $i',
        'presenza': 1,
        'squadra': match[i.isEven ? 'squadra_casa' : 'squadra_ospite'],
        'goal': 0,
        'voto': 6,
      },
    );
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Center(
              child: MatchShareArtwork(
                api: TosApi(baseUrl: Uri.parse('https://example.test/')),
                match: match,
                events: players,
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.getSize(find.byType(MatchShareArtwork)),
      const Size(360, 640),
    );
    for (var i = 0; i < 40; i++) {
      expect(find.text('Nome molto lungo Giocatore $i'), findsOneWidget);
    }
    expect(tester.takeException(), isNull);
  });

  testWidgets('share action exports a PNG with match link and iPad anchor', (
    tester,
  ) async {
    var sent = false;
    await tester.pumpWidget(
      MaterialApp(
        home: MatchSharePage(
          api: TosApi(baseUrl: Uri.parse('https://example.test/')),
          id: '12',
          match: match,
          events: events,
          sender: (bytes, text, origin) async {
            expect(bytes.take(8), [137, 80, 78, 71, 13, 10, 26, 10]);
            expect(text, contains('id=12'));
            expect(text, contains('12–12'));
            expect(origin.width, greaterThan(0));
            expect(origin.height, greaterThan(0));
            sent = true;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.runAsync(
      () => precacheImage(
        const AssetImage('assets/logo_old_school.png'),
        tester.element(find.byType(MatchShareArtwork)),
      ),
    );
    await tester.runAsync(() async {
      await tester.tap(find.text('Condividi immagine'));
      for (var frame = 0; frame < 30 && !sent; frame++) {
        await tester.pump();
        await Future<void>.delayed(const Duration(milliseconds: 30));
      }
    });
    await tester.pumpAndSettle();
    expect(sent, isTrue);
    expect(find.text('Preparazione…'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
