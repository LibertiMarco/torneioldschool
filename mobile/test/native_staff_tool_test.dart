import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/external_link_button.dart';
import 'package:tornei_old_school/native_staff_tool.dart';
import 'package:tornei_old_school/staff_dashboard.dart';
import 'package:tornei_old_school/staff_engine.dart';
import 'package:tornei_old_school/staff_rich_editor.dart';
import 'package:tornei_old_school/staff_match_forms.dart';
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
  testWidgets(
    'Grouped result form keeps both scores editable at 320px with large text',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = DemoApi(), auth = FixtureAuth(DemoApi());
      auth.user = api.users.first;
      final snapshot = {
        'type': 'snapshot',
        'document': 'result-document',
        'blocks': [
          {
            'kind': 'match-form',
            'id': 'result',
            'sections': [
              {
                'title': 'Seleziona la partita',
                'blocks': [
                  {
                    'kind': 'field',
                    'id': 'match',
                    'type': 'select',
                    'label': 'Partita',
                    'value': '25',
                    'options': [
                      {'value': '25', 'label': 'Go Ahead Eagles - Napoli'},
                    ],
                  },
                ],
              },
              {
                'title': 'Risultato',
                'blocks': [
                  for (final side in ['casa', 'ospite'])
                    {
                      'kind': 'field',
                      'id': 'goals-$side',
                      'type': 'number',
                      'score': true,
                      'label': 'Gol $side',
                      'value': '0',
                    },
                ],
              },
            ],
            'blocks': [
              {
                'kind': 'action',
                'id': 'save-result',
                'label': 'Salva modifiche',
              },
            ],
          },
        ],
      };
      late FixtureEngine engine;
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(1.6)),
            child: child!,
          ),
          home: NativeStaffTool(
            auth: auth,
            title: 'Calendario e risultati',
            description: 'Partite e risultati',
            path: 'api/gestione_partite.php',
            icon: Icons.calendar_month,
            graphicsOnly: false,
            engineFactory: (channel, receive) =>
                engine = FixtureEngine(receive, snapshot),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(StaffMatchForm), findsOneWidget);
      final goal = find.byWidgetPredicate(
        (widget) =>
            widget is TextField && widget.decoration?.labelText == 'Gol casa',
      );
      await tester.ensureVisible(goal);
      await tester.enterText(goal, '4');
      expect(engine.commands.last, {
        'document': 'result-document',
        'type': 'change',
        'id': 'goals-casa',
        'value': '4',
      });
      expect(tester.widget<TextField>(goal).style?.fontSize, 28);
      await tester.ensureVisible(find.text('Salva modifiche'));
      await tester.tap(find.text('Salva modifiche'));
      await tester.pumpAndSettle();
      expect(engine.commands.last['id'], 'save-result');
      expect(tester.takeException(), isNull);
    },
  );
  for (final scale in [1.0, 1.6]) {
    testWidgets(
      'Match lineup edits events and keeps finalization visible at 320px, scale $scale',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 1000));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = DemoApi(), auth = FixtureAuth(DemoApi());
        auth.user = api.users.first;
        Map<String, dynamic> field(
          String stat,
          String type,
          String label,
          String value,
        ) => {
          'kind': 'field',
          'id': 'player-$stat',
          'stat': stat,
          'type': type,
          'label': label,
          'value': value,
          'checked': false,
          'options': [
            {'value': '6', 'label': '6'},
            {'value': '7.5', 'label': '7,5'},
          ],
        };
        final model = {
          'kind': 'match-lineup',
          'id': 'lineup',
          'home': 'Go Ahead Eagles',
          'away': 'Napoli',
          'score': '0 - 0',
          'info': 'Brasilerao · Semifinale Gold',
          'back': {'kind': 'action', 'id': 'back', 'label': 'Torna indietro'},
          'finalize': {
            'kind': 'field',
            'id': 'finalize',
            'type': 'checkbox',
            'label': 'Finalizza partita',
            'checked': false,
          },
          'save': {
            'kind': 'action',
            'id': 'save-lineup',
            'label': 'Salva tutta la distinta',
          },
          'teams': [
            {
              'id': 'home',
              'name': 'Go Ahead Eagles',
              'actions': [
                {
                  'kind': 'action',
                  'id': 'all-present',
                  'label': 'Tutti presenti',
                },
                {'kind': 'action', 'id': 'none-present', 'label': 'Nessuno'},
              ],
              'players': [
                {
                  'id': 'player',
                  'name': 'Rossi Marco',
                  'badges': ['GK', 'C', 'DIFFIDATO'],
                  'fields': [
                    field('presenza', 'checkbox', 'Presente', ''),
                    field('goal', 'number', 'Gol', '0'),
                    field('assist', 'number', 'Assist', '0'),
                    field('autogol', 'number', 'Autogol', '0'),
                    field(
                      'cartellino_giallo',
                      'checkbox',
                      'Cartellino giallo',
                      '',
                    ),
                    field(
                      'cartellino_rosso',
                      'checkbox',
                      'Cartellino rosso',
                      '',
                    ),
                    field('voto', 'select', 'Voto', '6'),
                  ],
                },
              ],
            },
            {'id': 'away', 'name': 'Napoli', 'actions': [], 'players': []},
          ],
        };
        final snapshot = {
          'type': 'snapshot',
          'document': 'match-document',
          'blocks': [model],
        };
        late FixtureEngine engine;
        await tester.pumpWidget(
          MaterialApp(
            theme: siteTheme(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: NativeStaffTool(
              auth: auth,
              title: 'Calendario e risultati',
              description: 'Partite, referti e punteggi',
              path: 'api/gestione_partite.php',
              icon: Icons.calendar_month,
              graphicsOnly: false,
              engineFactory: (channel, receive) =>
                  engine = FixtureEngine(receive, snapshot),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.byType(StaffMatchLineup), findsOneWidget);
        expect(find.byType(SegmentedButton<String>), findsNothing);
        expect(
          find.text('Salva tutta la distinta').hitTestable(),
          findsOneWidget,
        );
        expect(find.text('Finalizza partita').hitTestable(), findsOneWidget);
        await tester.ensureVisible(find.text('Rossi Marco'));
        await tester.tap(find.text('Rossi Marco'));
        expect(engine.commands.last, {
          'document': 'match-document',
          'type': 'change',
          'id': 'player-presenza',
          'checked': true,
        });
        expect(find.text('GK'), findsOneWidget);
        expect(find.text('C'), findsOneWidget);
        final goalCounter = find.byWidgetPredicate(
          (widget) =>
              widget is StaffStatCounter && widget.field['stat'] == 'goal',
        );
        final increment = find.descendant(
          of: goalCounter,
          matching: find.byTooltip('Aumenta Gol'),
        );
        await tester.ensureVisible(increment);
        await tester.tap(increment);
        await tester.pump();
        await tester.tap(increment);
        await tester.pump();
        expect(engine.commands.last['value'], '2');
        expect(engine.commands.last['id'], 'player-goal');
        final intermediate =
            jsonDecode(jsonEncode(snapshot)) as Map<String, dynamic>;
        intermediate['blocks'][0]['teams'][0]['players'][0]['fields'][1]['value'] =
            '1';
        engine.receive(intermediate);
        await tester.pumpAndSettle();
        expect(
          tester
              .widget<TextField>(
                find.descendant(
                  of: goalCounter,
                  matching: find.byType(TextField),
                ),
              )
              .controller!
              .text,
          '2',
        );
        await tester.tap(increment);
        await tester.pump();
        expect(engine.commands.last['value'], '3');
        final decrement = find.descendant(
          of: goalCounter,
          matching: find.byTooltip('Diminuisci Gol'),
        );
        await tester.tap(decrement);
        await tester.pump();
        expect(engine.commands.last['value'], '2');
        await tester.ensureVisible(find.text('Giallo'));
        await tester.tap(find.text('Giallo'));
        expect(engine.commands.last['id'], 'player-cartellino_giallo');
        expect(engine.commands.last['checked'], true);
        await tester.ensureVisible(find.text('Rosso'));
        await tester.tap(find.text('Rosso'));
        expect(engine.commands.last['id'], 'player-cartellino_rosso');
        await tester.tap(find.text('Finalizza partita'));
        expect(engine.commands.last['id'], 'finalize');
        expect(engine.commands.last['checked'], true);
        await tester.tap(find.text('Salva tutta la distinta'));
        await tester.pumpAndSettle();
        expect(engine.commands.last, {
          'document': 'match-document',
          'type': 'click',
          'id': 'save-lineup',
        });
        await tester.tap(find.byTooltip('Torna alla partita'));
        await tester.pumpAndSettle();
        expect(engine.commands.last['id'], 'back');
        expect(tester.takeException(), isNull);
      },
    );
  }
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
  for (final theme in [siteTheme(), siteDarkTheme()]) {
    for (final tool in [...adminTools, ...graphicsTools]) {
      testWidgets(
        '${tool.title} opens native controls at 320px in ${theme.brightness.name} without production requests',
        (tester) async {
          await tester.binding.setSurfaceSize(const Size(320, 1000));
          addTearDown(() => tester.binding.setSurfaceSize(null));
          final api = DemoApi(), auth = FixtureAuth(DemoApi());
          auth.user = api.users.first;
          await tester.pumpWidget(
            MaterialApp(
              theme: theme,
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
