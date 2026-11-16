import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_player_link_invite.dart';

class LinkApi extends DemoApi {
  bool linked = false, fail = false;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/player_link_requests.php') {
      if (fail) throw const ApiError('offline');
      return {'linked': linked, 'request': playerLinkRequest};
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
  testWidgets(
    'invitation opens profile and can be dismissed without sending a request',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = LinkApi();
      final auth = DemoAuth(api);
      await auth.login();
      var opened = 0;
      await tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(2)),
            child: child!,
          ),
          home: Scaffold(
            body: ListView(
              children: [
                HomePlayerLinkInvite(auth: auth, onProfile: () => opened++),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Collega il tuo giocatore'));
      expect(opened, 1);
      expect(api.playerLinkRequest, isNull);
      await tester.tap(find.byTooltip('Nascondi per ora'));
      await tester.pumpAndSettle();
      expect(find.text('Collega il tuo giocatore'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  for (final state in ['pending', 'linked', 'offline']) {
    testWidgets('invitation respects server state: $state', (tester) async {
      final api = LinkApi()
        ..linked = state == 'linked'
        ..fail = state == 'offline';
      if (state == 'pending') api.playerLinkRequest = {'status': 'pending'};
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: HomePlayerLinkInvite(auth: auth)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Collega il tuo giocatore'), findsNothing);
      expect(
        find.text('Richiesta in verifica'),
        state == 'pending' ? findsOneWidget : findsNothing,
      );
      expect(tester.takeException(), isNull);
    });
  }
}
