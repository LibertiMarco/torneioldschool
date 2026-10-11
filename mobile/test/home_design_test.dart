import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/home_dashboard.dart';
import 'package:tornei_old_school/theme.dart';

Future<void> captureHome(
  WidgetTester tester,
  GlobalKey key,
  String name,
) async {
  if (!const bool.fromEnvironment('CAPTURE_HOME')) return;
  await tester.runAsync(() async {
    await precacheImage(
      const AssetImage('assets/emoji/football.png'),
      key.currentContext!,
    );
    await precacheImage(
      const AssetImage('assets/emoji/clipboard.png'),
      key.currentContext!,
    );
  });
  await tester.pumpAndSettle();
  final boundary =
      key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory('build/home-review').create(recursive: true);
    await File('build/home-review/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

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
    testWidgets('Editorial home remains navigable at ${width.toInt()}px', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi();
      final auth = DemoAuth(api);
      await auth.login();
      final key = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: TosApp(api: api, auth: auth),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Classifica giocatori totale'), findsOneWidget);
      await captureHome(tester, key, 'home-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.text('La nuova stagione'),
        100,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('La nuova stagione'));
      await tester.pumpAndSettle();
      await captureHome(tester, key, 'news-${width.toInt()}');
      await tester.tap(find.text('La nuova stagione'));
      await tester.pumpAndSettle();
      expect(find.text('Notizia'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tornei'));
      await tester.pumpAndSettle();
      expect(find.text('Old School Cup'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('Compact home rankings support large text', (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        theme: siteTheme(),
        home: MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(2)),
          child: Scaffold(
            body: HomeDashboard(
              api: DemoApi(),
              section: 'calcio',
              onTournaments: () {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Classifica completa'), 100);
    await tester.ensureVisible(find.text('Classifica completa'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Classifica completa'));
    await tester.pumpAndSettle();
    expect(find.byType(GlobalRankingPage), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
