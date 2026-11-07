import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/login_page.dart';

void main() {
  testWidgets(
    'Login shows verification before submit and guards keyboard submit',
    (tester) async {
      final auth = DemoAuth(DemoApi());
      await tester.pumpWidget(MaterialApp(home: NativeLoginPage(auth: auth)));
      expect(find.text('Non sono un robot'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      await tester.enterText(
        find.byType(TextFormField).at(0),
        'test@example.test',
      );
      await tester.enterText(find.byType(TextFormField).at(1), 'Password123!');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();
      expect(
        find.text('Completa prima la verifica Non sono un robot.'),
        findsOneWidget,
      );
      expect(find.text('Verifica di sicurezza'), findsNothing);
      expect(auth.user, isNull);
      await tester.ensureVisible(find.text('Password dimenticata?'));
      await tester.tap(find.text('Password dimenticata?'));
      await tester.pumpAndSettle();
      expect(find.text('Non sono un robot'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
    },
  );
}
