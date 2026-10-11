import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/external_link_button.dart';
import 'package:tornei_old_school/native_staff_tool.dart';
import 'package:tornei_old_school/staff_dashboard.dart';
import 'package:tornei_old_school/staff_engine.dart';
import 'package:tornei_old_school/staff_rich_editor.dart';
import 'package:tornei_old_school/theme.dart';

class FixtureAuth extends DemoAuth {
  FixtureAuth(super.api);
  int starts = 0;
  @override
  Future<Map<String, String>> staffSessionHeaders() async {
    starts++;
    return {};
  }
}

class FixtureEngine implements StaffEngine {
  FixtureEngine(this.receive, this.snapshot);
  final void Function(StaffMessage) receive;
  final StaffMessage snapshot;
  final commands = <StaffMessage>[];
  Uri? uri;
  bool disposed = false;
  int exports = 0;
  @override
  Widget get host => const SizedBox.shrink();
  @override
  Future<void> start(Uri uri, Map<String, String> headers) async {
    this.uri = uri;
    receive(snapshot);
  }

  @override
  void send(StaffMessage message) => commands.add(message);
  @override
  Future<void> save(StaffMessage file, Rect shareOrigin) async {
    exports++;
  }

  @override
  void dispose() {
    disposed = true;
  }
}

void main() {
  test('Protocol rejects wrong channels and malformed data', () {
    expect(safeStaffFilename('../../coppa/a.png'), isNot(contains('/')));
    expect(safeStaffFilename('../../coppa/a.png'), isNot(startsWith('.')));
    expect(decodeStaffMessage('broken', 'trusted'), isNull);
    expect(
      decodeStaffMessage(
        jsonEncode({'channel': 'other', 'type': 'snapshot'}),
        'trusted',
      ),
      isNull,
    );
    expect(
      decodeStaffMessage(
        jsonEncode({'channel': 'trusted', 'type': 'snapshot'}),
        'trusted',
      )?['type'],
      'snapshot',
    );
  });
  test(
    'Article editor preserves formatting, sections and escapes unsafe text',
    () {
      final delta = staffHtmlDelta(
        '<h2>Sezione</h2><p>Ciao <strong>Marco</strong> e <em>Luca</em> &lt;test&gt;</p><ul><li>Uno</li></ul>',
      );
      final html = staffDeltaHtml(delta);
      expect(html, contains('<h2>Sezione</h2>'));
      expect(html, contains('<strong>Marco</strong>'));
      expect(html, contains('<em>Luca</em>'));
      expect(html, contains('&lt;test&gt;'));
      expect(html, contains('<ul><li>Uno</li></ul>'));
      expect(
        staffDeltaHtml([
          {
            'insert': 'danger',
            'attributes': {'link': 'javascript:alert(1)'},
          },
          {'insert': '\n'},
        ]),
        isNot(contains('href')),
      );
    },
  );
  for (final tool in [...adminTools, ...graphicsTools]) {
    testWidgets(
      '${tool.title} opens native controls at 320px without production requests',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 1000));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = DemoApi(), auth = FixtureAuth(DemoApi());
        auth.user = api.users.first;
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            home: StaffToolPage(auth: auth, tool: tool),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.byType(NativeStaffTool), findsOneWidget);
        expect(find.byType(ExternalLinkButton), findsNothing);
        expect(auth.starts, 0);
        expect(tester.takeException(), isNull);
        if (!tool.path.contains('fantacalcio') &&
            !tool.path.contains('admin_mobile')) {
          expect(find.byType(StaffNativeField), findsWidgets);
        }
        if (tool.path.contains('blog')) {
          expect(find.byType(StaffRichEditor), findsOneWidget);
        }
      },
    );
  }
  testWidgets(
    'Live renderer changes values, searches select options, confirms writes and drops revoked roles',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1100));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi(), auth = FixtureAuth(DemoApi());
      auth.user = api.users.first;
      late FixtureEngine engine;
      final snapshot = <String, dynamic>{
        'type': 'snapshot',
        'document': '42',
        'blocks': [
          {
            'kind': 'form',
            'id': 'form',
            'blocks': [
              {
                'kind': 'field',
                'id': 'name',
                'type': 'text',
                'label': 'Nome',
                'value': 'Marco',
              },
              {
                'kind': 'field',
                'id': 'team',
                'type': 'select',
                'label': 'Squadra',
                'value': '',
                'options': [
                  {'value': '', 'label': 'Scegli…'},
                  {'value': '25', 'label': 'Go Ahead Eagles'},
                ],
              },
              {'kind': 'action', 'id': 'save', 'label': 'Salva'},
            ],
          },
        ],
      };
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: NativeStaffTool(
            auth: auth,
            title: 'Giocatori',
            description: 'Gestisci i giocatori',
            path: 'api/gestione_giocatori.php',
            icon: Icons.person_outline,
            graphicsOnly: false,
            engineFactory: (channel, receive) =>
                engine = FixtureEngine(receive, snapshot),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(engine.uri?.path, '/app-preview/staff-engine.php');
      expect(engine.uri?.queryParameters['tool'], 'api/gestione_giocatori.php');
      expect(engine.uri?.queryParameters['channel']?.length, 43);
      await tester.enterText(find.byType(TextField).first, 'Luca');
      expect(engine.commands.last, {
        'document': '42',
        'type': 'change',
        'id': 'name',
        'value': 'Luca',
      });
      await tester.tap(find.text('Scegli…'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).last, 'eagles');
      await tester.pumpAndSettle();
      expect(find.text('Go Ahead Eagles'), findsOneWidget);
      await tester.tap(find.text('Go Ahead Eagles'));
      await tester.pumpAndSettle();
      expect(engine.commands.last['value'], '25');
      await tester.tap(find.text('Salva'));
      await tester.pumpAndSettle();
      expect(engine.commands.last['id'], 'save');
      engine.receive({
        'type': 'confirm',
        'message': 'Eliminare il giocatore?',
        'action': {'type': 'click', 'id': 'delete', 'document': '42'},
      });
      await tester.pumpAndSettle();
      final before = engine.commands.length;
      await tester.tap(find.text('Annulla'));
      await tester.pumpAndSettle();
      expect(engine.commands.length, before);
      engine.receive({
        'type': 'confirm',
        'message': 'Eliminare il giocatore?',
        'action': {'type': 'click', 'id': 'delete', 'document': '42'},
      });
      await tester.pumpAndSettle();
      await tester.tap(find.text('Conferma'));
      await tester.pumpAndSettle();
      expect(engine.commands.last['approved'], 'Eliminare il giocatore?');
      auth.updateProfile({
        'permissions': {'admin': false, 'graphics': true},
      });
      await tester.pumpAndSettle();
      expect(engine.disposed, true);
      expect(find.text('Area riservata allo staff.'), findsOneWidget);
      engine.receive(snapshot);
      await tester.pumpAndSettle();
      expect(find.text('Salva'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Long record lists use native search and pagination', (
    tester,
  ) async {
    final table = {
      'rows': [
        for (var i = 1; i <= 45; i++)
          {
            'cells': [
              {
                'label': 'Nome',
                'blocks': [
                  {'kind': 'text', 'text': 'Giocatore $i'},
                ],
              },
            ],
          },
      ],
    };
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: StaffNativeRecords(
              table: table,
              render: (block) => Text('${block['text']}'),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Giocatore 1'), findsOneWidget);
    expect(find.text('Giocatore 21'), findsNothing);
    await tester.enterText(find.byType(TextField), 'Giocatore 45');
    await tester.pumpAndSettle();
    expect(
      find
          .text('Giocatore 45', skipOffstage: false)
          .evaluate()
          .where((element) => element.widget is Text)
          .length,
      1,
    );
    expect(find.text('Giocatore 1'), findsNothing);
  });
}
