import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/original_emblem.dart';

class AccountAuth extends DemoAuth {
  AccountAuth(super.api);
  void select(String? email) {
    user = email == null ? null : {'email': email};
    notifyListeners();
  }
}

void main() {
  testWidgets(
    'Account override survives global changes and follows the session',
    (tester) async {
      final api = DemoApi()..originalLogos.value = true;
      final auth = AccountAuth(api)..select(' OLDSCHOOLTORNEI@gmail.com ');
      await tester.pumpWidget(
        MaterialApp(
          home: AppAuthScope(
            auth: auth,
            child: Column(
              children: [
                SiteImage(
                  api: api,
                  path: '',
                  identityName: 'Eredivisie (Fascia C)',
                ),
                SiteImage(api: api, path: '', identityName: 'Amatori Borgo'),
                SiteImage(api: api, path: '', fallback: Icons.person_outline),
              ],
            ),
          ),
        ),
      );
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNWidgets(2));
      expect(api.originalLogos.value, isTrue);
      api.originalLogos.value = false;
      await tester.pump();
      api.originalLogos.value = true;
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNWidgets(2));
      auth.select('other@example.com');
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNothing);
      auth.select('oldschooltornei@gmail.com');
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNWidgets(2));
      auth.select(null);
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNothing);
      api.originalLogos.value = false;
      await tester.pump();
      expect(find.byType(OriginalEmblem), findsNWidgets(2));
      expect(tester.takeException(), isNull);
    },
  );
}
