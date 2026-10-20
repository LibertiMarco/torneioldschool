import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/notification_preferences.dart';

class PreferencesApi extends DemoApi {
  bool fail = false;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) {
    if (fail && body != null) throw const ApiError('Errore');
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
    'Saves each preference separately and preserves saved values on failure',
    (tester) async {
      final api = PreferencesApi();
      final auth = DemoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: NotificationPreferences(auth: auth)),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Risultati delle partite'));
      await tester.pumpAndSettle();
      expect(api.notificationPreferences['results'], false);
      expect(api.notificationPreferences['comments'], true);
      api.fail = true;
      await tester.tap(find.text('Risposte ai tuoi commenti'));
      await tester.pumpAndSettle();
      expect(
        tester.widget<SwitchListTile>(find.byType(SwitchListTile).last).value,
        true,
      );
      expect(
        find.text('Impossibile aggiornare le preferenze. Riprova.'),
        findsOneWidget,
      );
    },
  );
}
