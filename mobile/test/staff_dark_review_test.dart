import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/staff_dashboard.dart';
import 'package:tornei_old_school/staff_controls.dart';
import 'package:tornei_old_school/theme.dart';

Future<void> capture(WidgetTester tester, GlobalKey key, String name) async {
  if (!const bool.fromEnvironment('CAPTURE_STAFF')) return;
  final boundary =
      key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory('build/staff-review').create(recursive: true);
    await File('build/staff-review/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/Roboto-Regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/Roboto-Bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });
  for (final width in [320.0, 430.0]) {
    testWidgets('Dark management search and filters at $width pixels', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 950));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi();
      final auth = DemoAuth(api)..user = api.users.first;
      final key = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: MaterialApp(
            theme: siteDarkTheme(),
            home: Scaffold(
              appBar: AppBar(title: const Text('Gestione')),
              body: StaffDashboard(auth: auth),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await capture(tester, key, 'dashboard-${width.toInt()}');
      await tester.enterText(find.byType(TextField), 'referti');
      await tester.pumpAndSettle();
      final scroll = find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first;
      await tester.scrollUntilVisible(
        find.text('Calendario e risultati'),
        180,
        scrollable: scroll,
      );
      expect(find.text('Calendario e risultati'), findsOneWidget);
      expect(find.text('Squadre'), findsNothing);
      expect(find.text('1 strumento'), findsOneWidget);
      final title = tester.widget<Text>(find.text('Calendario e risultati'));
      expect(title.style?.color, siteDarkTheme().colorScheme.onSurface);
      final row = find.ancestor(
        of: find.text('Calendario e risultati'),
        matching: find.byType(ListTile),
      );
      expect(tester.getSize(row).width, closeTo(width - 32, 2));
      expect(tester.getSize(row).height, lessThan(110));
      await Scrollable.ensureVisible(
        tester.element(find.text('Calendario e risultati')),
        alignment: .5,
      );
      await tester.pumpAndSettle();
      await capture(tester, key, 'search-${width.toInt()}');
      await tester.enterText(find.byType(TextField), 'nessuno123');
      await tester.pumpAndSettle();
      final reset = find.text('Reimposta ricerca e filtri');
      await tester.scrollUntilVisible(reset, 180, scrollable: scroll);
      await Scrollable.ensureVisible(tester.element(reset), alignment: .5);
      await tester.pumpAndSettle();
      await tester.tap(reset);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('${adminTools.length + graphicsTools.length + 1} strumenti'),
        -100,
        scrollable: scroll,
      );
      expect(
        find.text('${adminTools.length + graphicsTools.length + 1} strumenti'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('Dark staff actions and filters keep contrasting foregrounds', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: siteDarkTheme(),
        home: StaffTheme(
          child: Scaffold(
            body: Column(
              children: [
                StaffFilter(
                  label: 'Selezionato',
                  selected: true,
                  onSelected: (_) {},
                ),
                StaffActionButton(label: 'Salva', onPressed: () {}),
                StaffActionButton(
                  label: 'Elimina',
                  danger: true,
                  onPressed: () {},
                ),
              ],
            ),
          ),
        ),
      ),
    );
    final context = tester.element(find.byType(StaffFilter));
    final colors = Theme.of(context).colorScheme;
    final chip = tester.widget<ChoiceChip>(find.byType(ChoiceChip));
    expect(chip.labelStyle?.color, colors.onPrimary);
    final button = tester.widget<FilledButton>(find.byType(FilledButton));
    final foreground =
        button.style?.foregroundColor ??
        Theme.of(context).filledButtonTheme.style?.foregroundColor;
    expect(foreground?.resolve({}), colors.onPrimary);
    expect(tester.takeException(), isNull);
  });
  for (final tool in [adminTools[1], adminTools[2], adminTools[8]]) {
    testWidgets('Dark management visual review: ${tool.title}', (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 950));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi();
      final auth = DemoAuth(api)..user = api.users.first;
      final key = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: MaterialApp(
            theme: siteDarkTheme(),
            home: StaffToolPage(auth: auth, tool: tool),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await capture(tester, key, 'tool-${tool.path.split('/').last}');
      expect(tester.takeException(), isNull);
    });
  }
}
