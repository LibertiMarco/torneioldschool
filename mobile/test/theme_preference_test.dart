import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/auth.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/main.dart';

class MemoryThemeStore implements SessionStore {
  final values = <String, String>{};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String value) async => values[key] = value;
  @override
  Future<void> delete(String key) async => values.remove(key);
}

void main() {
  testWidgets('Theme choice switches the app and is restored on next launch', (
    tester,
  ) async {
    final api = DemoApi();
    final storage = MemoryThemeStore();
    Widget app() =>
        TosApp(api: api, auth: DemoAuth(api), themeStorage: storage);

    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.text('TORNEI OLD SCHOOL'))).brightness,
      Brightness.light,
    );

    await tester.tap(find.byTooltip('Aspetto chiaro o scuro'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Scuro'));
    await tester.pumpAndSettle();
    expect(storage.values['tos.app.theme'], 'dark');
    expect(
      Theme.of(tester.element(find.text('TORNEI OLD SCHOOL'))).brightness,
      Brightness.dark,
    );

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.text('TORNEI OLD SCHOOL'))).brightness,
      Brightness.dark,
    );
  });
}
