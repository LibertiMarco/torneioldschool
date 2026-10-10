import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/tournament_rules.dart';
import 'package:tornei_old_school/theme.dart';

void main() {
  test('Real Mc League Markdown keeps headings, bold text and awards list', () {
    final source = File('test/fixtures/mcleague_rules.md').readAsStringSync();
    final fragment = tournamentRulesFragment(source);
    expect(
      fragment.querySelectorAll('h2,h3').map((e) => e.text),
      containsAll([
        'Struttura del campionato',
        'Fase 1 – Regular Season',
        'Fase 2 – Coppe',
        'Premi finali',
        'Regole di gioco',
      ]),
    );
    expect(
      fragment.querySelectorAll('strong').map((e) => e.text),
      containsAll(['7 squadre', '10 giornate', 'Coppa Gold', 'Coppa Silver']),
    );
    expect(
      fragment.querySelectorAll('li').map((e) => e.text),
      contains('Miglior Giocatore'),
    );
    expect(fragment.text, isNot(contains('**')));
    expect(fragment.text, isNot(contains('##')));
  });
  test(
    'Existing HTML rules retain formatting and exclude executable elements',
    () {
      final fragment = tournamentRulesFragment(
        '<h3>Coppe</h3>\n<p>Prime <strong>due</strong> squadre.</p>\n<ul><li>Trofeo</li></ul>\n<script>alert(1)</script>',
      );
      expect(fragment.querySelector('h3')!.text, 'Coppe');
      expect(fragment.querySelector('strong')!.text, 'due');
      expect(fragment.querySelector('li')!.text, 'Trofeo');
      expect(fragment.querySelector('script'), isNull);
    },
  );
  testWidgets(
    'Markdown rules show section cards at 320px without raw markers',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 640));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          theme: siteTheme(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: TournamentRules(
                html: File('test/fixtures/mcleague_rules.md')
                    .readAsStringSync(),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Struttura del campionato'), findsOneWidget);
      expect(find.text('Premi finali'), findsOneWidget);
      final text = tester
          .widgetList<SelectableText>(find.byType(SelectableText))
          .map((w) => w.textSpan?.toPlainText() ?? w.data ?? '')
          .join('\n');
      expect(text, isNot(contains('**')));
      expect(text, isNot(contains('###')));
      expect(tester.takeException(), isNull);
    },
  );
}
