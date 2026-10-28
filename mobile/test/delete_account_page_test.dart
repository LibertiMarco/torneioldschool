import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/delete_account_page.dart';

class DeleteAuth extends TosAuth {
  DeleteAuth() : super(TosApi(baseUrl: Uri.parse('https://example.test')));
  int calls = 0;
  @override
  Future<void> deleteAccount(String password) async {
    calls++;
    expect(password, ' exact password ');
    throw const ApiError('Password non corretta.', status: 403);
  }
}

void main() {
  testWidgets(
    'Deletion requires confirmation and password, and shows server errors',
    (tester) async {
      final auth = DeleteAuth();
      await tester.pumpWidget(MaterialApp(home: DeleteAccountPage(auth: auth)));
      final button = find.widgetWithText(
        FilledButton,
        'Elimina definitivamente',
      );
      expect(tester.widget<FilledButton>(button).onPressed, isNull);
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pump();
      await tester.tap(button);
      await tester.pump();
      expect(auth.calls, 0);
      expect(find.text('Inserisci la password attuale.'), findsOneWidget);
      await tester.enterText(find.byType(TextField), ' exact password ');
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(auth.calls, 1);
      expect(find.text('Password non corretta.'), findsOneWidget);
      expect(find.byType(DeleteAccountPage), findsOneWidget);
    },
  );
}
