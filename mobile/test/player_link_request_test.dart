import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/personal_player_page.dart';
import 'package:tornei_old_school/player_link_request_page.dart';
import 'package:tornei_old_school/theme.dart';

import 'staff_dark_review_test.dart' show capture;

class LinkApi extends DemoApi {
  bool linked = false, failSubmit = false;
  Map<String, dynamic>? currentRequest;
  final writes = <Map<String, dynamic>>[];
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/player_stats.php' && !linked) {
      return {'player': null, 'teams': []};
    }
    if (path == 'api/mobile/v1/player_link_requests.php') {
      if (body != null) {
        if (failSubmit) throw const ApiError('Invio non riuscito. Riprova.');
        writes.add({...body});
        currentRequest = {
          'id': 1,
          'player_id': body['giocatore_id'],
          'status': 'pending',
          'nome': 'Luca',
          'cognome': 'Rossi',
        };
      }
      return {
        'linked': linked,
        'request': currentRequest,
        'players': query?['q'] == null
            ? []
            : [
                {
                  'id': 10,
                  'nome': 'Luca',
                  'cognome': 'Rossi',
                  'ruolo': 'Attaccante',
                },
              ],
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

Future<void> visible(WidgetTester tester, Finder target) async {
  final scroll = find
      .descendant(of: find.byType(ListView), matching: find.byType(Scrollable))
      .first;
  await tester.scrollUntilVisible(target, 100, scrollable: scroll);
  await Scrollable.ensureVisible(tester.element(target), alignment: .5);
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });
  for (final width in [320.0, 430.0]) {
    testWidgets(
      'Profile requests a player and shows approval at $width pixels',
      (tester) async {
        await tester.binding.setSurfaceSize(Size(width, 900));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = LinkApi();
        final auth = DemoAuth(api);
        await auth.login();
        final key = GlobalKey();
        await tester.pumpWidget(
          RepaintBoundary(
            key: key,
            child: MaterialApp(
              theme: siteDarkTheme(),
              home: Scaffold(
                appBar: AppBar(title: const Text('Profilo')),
                body: PersonalPlayerPage(auth: auth, section: 'calcio'),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        await tester.enterText(find.byType(TextField), 'Luca');
        await tester.pump(const Duration(milliseconds: 400));
        await tester.pumpAndSettle();
        await visible(tester, find.text('Luca Rossi'));
        await tester.tap(find.text('Luca Rossi'));
        await tester.pumpAndSettle();
        await visible(tester, find.text('Invia richiesta agli admin'));
        expect(
          tester
              .widget<FilledButton>(
                find.ancestor(
                  of: find.text('Invia richiesta agli admin'),
                  matching: find.byType(FilledButton),
                ),
              )
              .onPressed,
          isNull,
        );
        await tester.tap(find.byType(CheckboxListTile));
        await tester.pumpAndSettle();
        await capture(tester, key, 'player-link-choice-${width.toInt()}');
        await tester.tap(find.text('Invia richiesta agli admin'));
        await tester.pumpAndSettle();
        expect(api.writes.single, {'giocatore_id': 10});
        expect(
          find.text('Richiesta in attesa di approvazione'),
          findsOneWidget,
        );
        expect(find.byType(TextField), findsNothing);
        await capture(tester, key, 'player-link-pending-${width.toInt()}');
        api.linked = true;
        api.currentRequest!['status'] = 'accepted';
        await visible(tester, find.text('Verifica associazione'));
        await tester.tap(find.text('Verifica associazione'));
        await tester.pumpAndSettle();
        expect(find.byType(PlayerLinkRequestPage), findsNothing);
        expect(find.text('Luca Rossi'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'Rejected request shows reason and permits a new request with large text',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = LinkApi()
        ..currentRequest = {
          'id': 1,
          'status': 'rejected',
          'nome': 'Luca',
          'cognome': 'Rossi',
          'reason': 'Giocatore non corrispondente',
        };
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          theme: siteDarkTheme(),
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(1.6)),
            child: child!,
          ),
          home: Scaffold(
            body: PlayerLinkRequestPage(auth: auth, onRefresh: () async {}),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await visible(tester, find.text('Richiesta rifiutata'));
      expect(find.text('Giocatore non corrispondente'), findsOneWidget);
      await visible(tester, find.byType(TextField));
      await tester.enterText(find.byType(TextField), 'Luca');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      await visible(tester, find.widgetWithText(ListTile, 'Luca Rossi'));
      await tester.tap(find.widgetWithText(ListTile, 'Luca Rossi'));
      await tester.pumpAndSettle();
      await visible(tester, find.byType(CheckboxListTile));
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      api.failSubmit = true;
      await visible(tester, find.text('Invia richiesta agli admin'));
      await tester.tap(find.text('Invia richiesta agli admin'));
      await tester.pumpAndSettle();
      await visible(tester, find.text('Invio non riuscito. Riprova.'));
      expect(api.writes, isEmpty);
      expect(tester.takeException(), isNull);
    },
  );
}
