import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/account_page.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/theme.dart';

class AccountApi extends DemoApi {
  Map<String, dynamic>? saved;
  bool fail = false;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    if (path == 'api/mobile/v1/account.php' && body != null) {
      if (fail) throw const ApiError('Salvataggio non riuscito.', status: 422);
      saved = body;
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
  for (final theme in [siteTheme(), siteDarkTheme()]) {
    testWidgets(
      'Account at 320px edits own profile and saves real consent fields in ${theme.brightness.name} mode',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 900));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = AccountApi();
        final auth = DemoAuth(api);
        await auth.login();
        await tester.pumpWidget(
          MaterialApp(
            theme: theme,
            home: Scaffold(body: AccountPage(auth: auth)),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Marco Demo'), findsOneWidget);
        final section = tester.widget<Container>(
          find
              .ancestor(
                of: find.text('Il mio account'),
                matching: find.byType(Container),
              )
              .first,
        );
        expect(
          (section.decoration as BoxDecoration).color,
          theme.colorScheme.surfaceContainerLow,
        );
        expect(
          tester
              .widget<TextField>(find.byType(TextField).first)
              .decoration
              ?.filled,
          theme.brightness == Brightness.dark,
        );
        await tester.enterText(find.byType(TextFormField).at(0), 'Nuovo');
        await tester.scrollUntilVisible(
          find.text('Newsletter'),
          150,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        final newsletter = find.byType(SwitchListTile).first;
        await tester.ensureVisible(newsletter);
        await tester.pumpAndSettle();
        await tester.tap(newsletter);
        await tester.scrollUntilVisible(
          find.text('Salva modifiche'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Salva modifiche'));
        await tester.pumpAndSettle();
        expect(api.saved?['nome'], 'Nuovo');
        expect(api.saved?['consenso_newsletter'], 1);
        expect(api.saved?.containsKey('id'), false);
        expect(api.saved?.containsKey('email'), false);
        expect(auth.user?['nome'], 'Nuovo');
        expect(auth.admin, true);
        await tester.scrollUntilVisible(
          find.text('Revoca tutti i consensi facoltativi'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Revoca tutti i consensi facoltativi'));
        await tester.pumpAndSettle();
        expect(api.saved, {'revoca_consensi': '1'});
        await tester.scrollUntilVisible(
          find.text('Newsletter'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        expect(tester.widget<SwitchListTile>(newsletter).value, false);
        api.fail = true;
        await tester.scrollUntilVisible(
          find.text('Salva modifiche'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Salva modifiche'));
        await tester.pumpAndSettle();
        expect(find.text('Salvataggio non riuscito.'), findsOneWidget);
        expect(
          tester
              .widget<Text>(find.text('Salvataggio non riuscito.'))
              .style
              ?.color,
          theme.colorScheme.onErrorContainer,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }
}
