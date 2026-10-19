import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/match_report_logic.dart';
import 'package:tornei_old_school/venue_navigation.dart';
import 'package:tornei_old_school/staff_photo_canvas.dart';
import 'package:tornei_old_school/login_page.dart';
import 'package:tornei_old_school/theme.dart';

import 'auth_test.dart' show MemorySessionStore, testUser;

class StatusMatchApi extends DemoApi {
  StatusMatchApi(this.played);
  final bool played;
  int eventCalls = 0;
  @override
  Future<Map<String, dynamic>> match(String id) async => {
    'giocata': played ? 1 : 0,
    'squadra_casa': 'Casa',
    'squadra_ospite': 'Ospite',
    'gol_casa': 0,
    'gol_ospite': 0,
    'campo': 'Campo reale',
    'data_partita': '2026-11-01',
  };
  @override
  Future<List<Map<String, dynamic>>> events(String id) async {
    eventCalls++;
    return [
      {'nome': 'Mario', 'cognome': 'Rossi', 'squadra': 'Casa', 'voto': 7},
    ];
  }
}

void main() {
  test(
    'lineup priority is exclusive and stable, with all original event data',
    () {
      final players = <Map<String, dynamic>>[
        {'id': 1, 'voto': 7},
        {'id': 2, 'goal': 1, 'cartellino_rosso': 1},
        {'id': 3, 'is_captain': 1, 'goal': 2},
        {'id': 4, 'ruolo': 'Portiere', 'is_captain': 1},
        {'id': 5, 'cartellino_giallo': 1},
        {'id': 6, 'goal': 3},
        {'id': 7, 'voto': 6},
      ];
      final result = orderedMatchPlayers(players);
      expect(result.map((player) => player['id']), [4, 3, 2, 6, 5, 1, 7]);
      expect(result.length, players.length);
      expect(result[2]['cartellino_rosso'], 1);
      expect(identical(result[2], players[1]), isTrue);
    },
  );
  for (final played in [false, true]) {
    testWidgets('zero-zero loads events only if played=$played', (
      tester,
    ) async {
      final api = StatusMatchApi(played);
      await tester.pumpWidget(
        MaterialApp(
          home: MatchPage(api: api, id: '42'),
        ),
      );
      await tester.pumpAndSettle();
      expect(api.eventCalls, played ? 1 : 0);
      expect(
        find.text('RISULTATO FINALE'),
        played ? findsOneWidget : findsNothing,
      );
      expect(find.text('Tutti'), played ? findsOneWidget : findsNothing);
      if (!played) expect(find.text('VOTO'), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('photo editing sends source transforms and supports reset', (
    tester,
  ) async {
    final messages = <Map<String, dynamic>>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: StaffPhotoCanvas(
              image: const ColoredBox(color: Colors.grey),
              photo: const {
                'width': 1080,
                'height': 1350,
                'sx': 1,
                'sy': 1,
                'dx': 0,
                'dy': 0,
              },
              onTransform: messages.add,
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.byType(SwitchListTile));
    await tester.pumpAndSettle();
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isTrue,
    );
    final gesture = await tester.startGesture(
      tester.getCenter(find.byKey(const ValueKey('staff-photo-gesture'))),
    );
    await gesture.moveBy(const Offset(60, 5));
    await tester.pump(const Duration(milliseconds: 60));
    await gesture.moveBy(const Offset(20, 20));
    await tester.pump(const Duration(milliseconds: 60));
    await gesture.up();
    await tester.pumpAndSettle();
    expect(messages, isNotEmpty);
    expect(messages.last['dx'], greaterThan(0));
    await tester.ensureVisible(find.text('Ripristina foto'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ripristina foto'));
    await tester.pumpAndSettle();
    expect(messages.last, {'reset': true});
  });
  for (final dark in [false, true]) {
    for (final width in [320.0, 390.0, 768.0, 1024.0]) {
      testWidgets(
        'native login fits $width pixels dark=$dark with large text',
        (tester) async {
          await tester.binding.setSurfaceSize(Size(width, 900));
          addTearDown(() => tester.binding.setSurfaceSize(null));
          await tester.pumpWidget(
            MaterialApp(
              theme: dark ? siteDarkTheme() : siteTheme(),
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context)
                    .copyWith(textScaler: TextScaler.linear(1.5)),
                child: child!,
              ),
              home: NativeLoginPage(
                auth: TosAuth(DemoApi(), storage: MemorySessionStore()),
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.text('Email'), findsOneWidget);
          expect(find.text('Password'), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
  test(
    'navigation preserves the real venue and handles unavailable locations',
    () {
      expect(venueNavigationUri({'campo': 'Online'}), isNull);
      expect(venueNavigationUri({'campo': ''}), isNull);
      expect(
        venueNavigationUri({
          'campo': 'Campo & Club',
        }, platform: TargetPlatform.android)!.queryParameters['query'],
        'Campo & Club',
      );
      expect(
        venueNavigationUri({
          'campo': 'Campo reale',
        }, platform: TargetPlatform.iOS)!.host,
        'maps.apple.com',
      );
    },
  );
  test(
    'native credentials use existing session storage, refresh and logout',
    () async {
      final store = MemorySessionStore();
      final api = TosApi(
        baseUrl: Uri.parse('https://example.test'),
        client: MockClient((request) async {
          if (request.url.path.endsWith('login.php')) {
            final body = jsonDecode(request.body);
            expect(body['email'], 'existing@example.test');
            expect(body['password'], ' keep spaces ');
            expect(body['captcha'], 'verified-captcha');
            return http.Response(
              jsonEncode({
                'access_token': 'access',
                'refresh_token': 'refresh',
                'user': testUser,
              }),
              200,
            );
          }
          if (request.url.path.endsWith('me.php')) {
            return http.Response(jsonEncode({'user': testUser}), 200);
          }
          expect(request.headers['Authorization'], 'Bearer access');
          return http.Response('{}', 200);
        }),
      );
      final auth = TosAuth(api, storage: store);
      expect(
        await auth.loginWithPassword(
          ' existing@example.test ',
          ' keep spaces ',
          'verified-captcha',
        ),
        isTrue,
      );
      expect(store.value, isNot(contains('password')));
      final restored = TosAuth(api, storage: store);
      await restored.restore();
      expect(restored.user?['id'], testUser['id']);
      await restored.logout();
      expect(store.value, isNull);
      expect(restored.user, isNull);
    },
  );
}
