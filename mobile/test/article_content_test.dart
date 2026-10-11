import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/article_content.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/home_dashboard.dart';

class ArticleApi extends DemoApi {
  @override
  Future<Map<String, dynamic>> article(String id) async => {
    'titolo': 'Nuova stagione',
    'data': '10/10/2026',
    'contenuto': '==Iscrizioni==\n\nAperte le **iscrizioni**.\nSeconda riga.\n\n## Calendario\n\n- Prima giornata\n- Finale',
  };
}

void main() {
  test('Site section markers and bold syntax retain their formatting', () {
    final fragment = articleFragment(
      '==Sezione uno==\r\n\r\nUn **grassetto** e ==altro grassetto==.\n\n# Titolo\n\n## Sezione due',
    );
    expect(fragment.querySelector('h3')!.text, 'Sezione uno');
    expect(fragment.querySelector('h2')!.text, 'Sezione due');
    expect(fragment.querySelector('h1')!.text, 'Titolo');
    expect(fragment.querySelectorAll('strong').map((node) => node.text), [
      'grassetto',
      'altro grassetto',
    ]);
    expect(fragment.text, isNot(contains('**')));
    expect(fragment.text, isNot(contains('==')));
  });

  test(
    'HTML emphasis, lists and entities survive without executable content',
    () {
      final fragment = articleFragment(
        '<h2>Notizie</h2><p>A &amp; B <strong>insieme</strong><br>ancora</p><ul><li>Finale</li></ul><script>alert(1)</script><iframe>bad</iframe>',
      );
      expect(fragment.querySelector('strong')!.text, 'insieme');
      expect(fragment.querySelector('li')!.text, 'Finale');
      expect(fragment.text, contains('A & B'));
      expect(fragment.querySelector('script'), isNull);
      expect(fragment.querySelector('iframe'), isNull);
    },
  );

  testWidgets(
    'Opening an article renders bold sections and lists on a small phone',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: NewsCard(
              api: ArticleApi(),
              row: const {'id': 1, 'titolo': 'Nuova stagione'},
            ),
          ),
        ),
      );
      await tester.tap(find.text('Nuova stagione'));
      await tester.pumpAndSettle();
      expect(find.byType(ArticleContent), findsOneWidget);
      final texts = tester.widgetList<SelectableText>(
        find.byType(SelectableText),
      );
      expect(
        texts.map((text) => text.textSpan?.toPlainText()),
        contains('Iscrizioni'),
      );
      expect(
        texts.map((text) => text.textSpan?.toPlainText()),
        contains('Calendario'),
      );
      final bold = <String>[];
      void visit(InlineSpan span, FontWeight? inherited) {
        if (span is TextSpan) {
          final weight = span.style?.fontWeight ?? inherited;
          if (weight == FontWeight.w700 && span.text != null) {
            bold.add(span.text!);
          }
          for (final child in span.children ?? <InlineSpan>[]) {
            visit(child, weight);
          }
        }
      }

      for (final text in texts) {
        visit(text.textSpan!, text.style?.fontWeight);
      }
      expect(bold, contains('iscrizioni'));
      expect(find.text('•'), findsNWidgets(2));
      expect(tester.takeException(), isNull);
    },
  );
}
