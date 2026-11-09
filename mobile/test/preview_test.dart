import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/admin.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/main.dart';

void main() {
  testWidgets('Preview browses roster and referto without live network', (
    tester,
  ) async {
    final api = DemoApi();
    await tester.pumpWidget(TosApp(api: api, auth: DemoAuth(api)));
    await tester.pumpAndSettle();
    expect(find.text('Anteprima · dati dimostrativi'), findsOneWidget);
    await tester.tap(find.text('Tornei'));
    await tester.pumpAndSettle();
    // Tornei and classifiche now require authentication, including in preview.
    await tester.tap(find.text('Accedi'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Old School Cup'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Old School Cup'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Rose'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Old School FC'), 150);
    await tester.ensureVisible(find.text('Old School FC'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Old School FC'));
    await tester.pumpAndSettle();
    expect(find.text('Luca Rossi'), findsOneWidget);
    await tester.ensureVisible(find.text('Luca Rossi'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Luca Rossi'));
    await tester.pumpAndSettle();
    expect(find.text('Media voti'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Calendario'));
    await tester.pumpAndSettle();
    // Select the round control independently of the date-sensitive default.
    final roundSelector = find.byType(DropdownButtonFormField<String>).last;
    await tester.ensureVisible(roundSelector);
    await tester.pumpAndSettle();
    await tester.tap(roundSelector);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tutte').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('3  :  1'));
    await tester.tap(find.text('3  :  1'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Referto giocatori'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Referto giocatori'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Luca Rossi'),
      150,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Luca Rossi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Admin searches a demo account and changes only its profile', (
    tester,
  ) async {
    final api = DemoApi();
    await tester.pumpWidget(TosApp(api: api, auth: DemoAuth(api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Account'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Accedi come admin demo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Gestione'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byType(TextField),
      120,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(find.byType(TextField), 'Utenti');
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Gestione account'),
      150,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Gestione account'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Cerca account per nome o email'),
      'Luca',
    );
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();
    expect(find.text('Marco Demo'), findsNothing);
    await tester.ensureVisible(find.text('Luca Demo'));
    await tester.tap(find.text('Luca Demo'));
    await tester.pumpAndSettle();
    expect(find.text('Totocalcio'), findsNothing);
    await tester.ensureVisible(find.byType(DropdownButtonFormField<String>));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Grafico').last);
    await tester.pumpAndSettle();
    expect(api.users[1]['ruolo'], 'user');
    await tester.ensureVisible(find.text('Salva profilo'));
    await tester.tap(find.text('Salva profilo'));
    await tester.pumpAndSettle();
    expect(api.users[1]['ruolo'], 'grafico');
    expect(api.users[1]['email'], 'giocatore@example.test');
    expect((api.users[1]['feature_flags'] as Map)['totocalcio'], false);
    expect(find.text('Profilo aggiornato.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Legacy demo flags still require confirmation', (tester) async {
    final api = DemoApi();
    final auth = DemoAuth(api)..user = api.users.first;
    await tester.pumpWidget(
      MaterialApp(
        home: AdminFeaturesPage(auth: auth, user: api.users[1]),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(SwitchListTile, 'Totocalcio'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Salva abilitazioni'));
    await tester.pumpAndSettle();
    expect((api.users[1]['feature_flags'] as Map)['totocalcio'], false);
    await tester.tap(find.text('Annulla'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Salva abilitazioni'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Salva'));
    await tester.pumpAndSettle();
    expect((api.users[1]['feature_flags'] as Map)['totocalcio'], true);
    expect(find.text('Abilitazioni salvate.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('Demo never forwards unknown endpoints to production', () async {
    expect(() => DemoApi().request('api/delete.php'), throwsA(isA<ApiError>()));
  });
}
