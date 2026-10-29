import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/logo_settings_card.dart';
import 'package:tornei_old_school/original_emblem.dart';

class FailingLogoAuth extends DemoAuth {
  FailingLogoAuth(super.api);

  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) async => throw const ApiError('Salvataggio fallito');
}

void main() {
  testWidgets('Admin toggle updates existing logos and can restore emblems', (
    tester,
  ) async {
    final api = DemoApi();
    final auth = DemoAuth(api);
    await auth.login();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              LogoSettingsCard(auth: auth),
              SiteImage(
                api: api,
                path: '',
                identityName: 'Eredivisie (Fascia C)',
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(OriginalEmblem), findsOneWidget);
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(api.originalLogos.value, isTrue);
    expect(find.byType(OriginalEmblem), findsNothing);
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(api.originalLogos.value, isFalse);
    expect(find.byType(OriginalEmblem), findsOneWidget);
  });

  testWidgets(
    'Failed save preserves the previous setting and reports failure',
    (tester) async {
      final api = DemoApi();
      final auth = FailingLogoAuth(api);
      await auth.login();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: LogoSettingsCard(auth: auth)),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byType(Switch));
      await tester.pumpAndSettle();
      expect(api.originalLogos.value, isFalse);
      expect(find.text('Salvataggio non riuscito. Riprova.'), findsOneWidget);
    },
  );

  testWidgets('Guests do not see the administration switch', (tester) async {
    await tester.pumpWidget(
      MaterialApp(home: LogoSettingsCard(auth: DemoAuth(DemoApi()))),
    );
    await tester.pumpAndSettle();
    expect(find.byType(Switch), findsNothing);
  });
}
