import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/notification_preferences.dart';

class PreferencesApi extends DemoApi {
  bool fail = false;
  bool remindersAvailable = false;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (fail && body != null) throw const ApiError('Errore');
    final response = await super.request(
      path,
      query: query,
      body: body,
      accessToken: accessToken,
      uploads: uploads,
    );
    if (path.contains('notification_preferences')) {
      return <String, dynamic>{...response, 'reminders_available': remindersAvailable};
    }
    return response;
  }
}

void main() {
  testWidgets('Reminder choice requires active worker and saves separately', (tester) async {
    final api = PreferencesApi();
    final auth = DemoAuth(api);
    await auth.login();
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: NotificationPreferences(auth: auth))));
    await tester.pumpAndSettle();
    expect(find.text('Promemoria partita'), findsNothing);
    api.remindersAvailable = true;
    await tester.tap(find.text('Risultati delle partite'));
    await tester.pumpAndSettle();
    expect(find.text('Promemoria partita'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('2 ore prima').last);
    await tester.pumpAndSettle();
    expect(api.notificationPreferences['reminder'], '2h');
    expect(api.notificationPreferences['comments'], true);
  });
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
