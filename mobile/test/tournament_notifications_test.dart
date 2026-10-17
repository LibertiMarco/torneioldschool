import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/tournament_notifications.dart';

import 'push_test.dart' show PushAuth;

class FollowAuth extends PushAuth {
  bool followed = false;
  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) async {
    expect(path, 'api/mobile/v1/follow.php');
    expect(body?['torneo'] ?? query?['torneo'], 'summer');
    if (body != null) followed = body['followed'] == true;
    return {'followed': followed};
  }
}

void main() {
  testWidgets(
    'load, follow, unfollow and logout update tournament preference',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = FollowAuth();
      await tester.pumpWidget(
        MaterialApp(
          home: AppAuthScope(
            auth: auth,
            child: const Scaffold(
              body: TournamentNotifications(slug: 'summer'),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester.getSize(find.byType(TournamentNotifications)).height,
        lessThanOrEqualTo(80),
      );
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Segui'));
      await tester.pumpAndSettle();
      expect(auth.followed, isTrue);
      expect(find.text('Seguito'), findsOneWidget);
      await tester.tap(find.text('Seguito'));
      await tester.pumpAndSettle();
      expect(auth.followed, isFalse);
      auth.signOut();
      await tester.pumpAndSettle();
      expect(find.text('Segui'), findsNothing);
      expect(find.text('Accedi per seguire il torneo'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      auth.dispose();
    },
  );
}
