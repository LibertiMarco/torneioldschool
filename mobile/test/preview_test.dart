import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
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
    await tester.ensureVisible(find.text('Old School Cup'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Old School Cup'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Rose'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Old School FC'));
    await tester.pumpAndSettle();
    expect(find.text('Luca Rossi · Capitano'), findsOneWidget);
    await tester.tap(find.text('Luca Rossi · Capitano'));
    await tester.pumpAndSettle();
    expect(find.text('Media voti'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Calendario'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Giornata 4'));
    await tester.tap(find.text('Giornata 4'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tutte').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('3 : 1'));
    await tester.tap(find.text('3 : 1'));
    await tester.pumpAndSettle();
    expect(find.text('Referto giocatori'), findsOneWidget);
    expect(find.text('Luca Rossi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Admin saves demo flags only after confirmation', (tester) async {
    final api = DemoApi();
    await tester.pumpWidget(TosApp(api: api, auth: DemoAuth(api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Account'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Accedi come admin demo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Gestione'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Luca Demo'));
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
