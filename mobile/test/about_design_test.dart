import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/about_page.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/theme.dart';

import 'home_design_test.dart' show captureHome;

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final roboto = FontLoader('Roboto')
      ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf'));
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await roboto.load();
    await icons.load();
  });
  for (final width in [320.0, 390.0, 430.0]) {
    testWidgets('About editorial layout and people at ${width.toInt()}px', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final key = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: siteTheme(),
            home: AboutPage(api: DemoApi()),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await captureHome(tester, key, 'about-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.text('Frank'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('Organizzatori'));
      await tester.pumpAndSettle();
      expect(find.text('Emanuele'), findsOneWidget);
      await captureHome(tester, key, 'organizers-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.text('Arbitro 1'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('Arbitro 1'));
      await tester.pumpAndSettle();
      await captureHome(tester, key, 'staff-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.text('Videomaker 2'),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets(
    'About supports large text through contacts, organizers and staff',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: siteTheme(),
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(2)),
            child: child!,
          ),
          home: AboutPage(api: DemoApi()),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Videomaker 2'),
        150,
        maxScrolls: 100,
        scrollable: find.byType(Scrollable).first,
      );
      expect(tester.takeException(), isNull);
    },
  );
}
