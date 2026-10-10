import 'package:flutter/material.dart';
import 'package:html/dom.dart' as dom;
import 'package:html/parser.dart' show parseFragment;
import 'package:markdown/markdown.dart' as markdown;

import 'theme.dart';

dom.DocumentFragment tournamentRulesFragment(String content) {
  final fragment = parseFragment(
    markdown.markdownToHtml(
      content,
      extensionSet: markdown.ExtensionSet.commonMark,
    ),
  );
  for (final element in fragment.querySelectorAll(
    'script, style, iframe, object, embed',
  )) {
    element.remove();
  }
  return fragment;
}

class TournamentRules extends StatelessWidget {
  const TournamentRules({super.key, required this.html});
  final String html;

  List<InlineSpan> inline(dom.Node node) {
    if (node is dom.Text) {
      return [TextSpan(text: node.text.replaceAll(RegExp(r'\s+'), ' '))];
    }
    if (node is! dom.Element) return [];
    if (node.localName == 'br') return [const TextSpan(text: '\n')];
    final bold = ['strong', 'b'].contains(node.localName);
    final italic = ['em', 'i'].contains(node.localName);
    final color = node.classes.contains('gold')
        ? const Color(0xff9a6a00)
        : node.classes.contains('silver')
        ? const Color(0xff586579)
        : null;
    return [
      TextSpan(
        style: TextStyle(
          fontWeight: bold || color != null ? FontWeight.w700 : null,
          fontStyle: italic ? FontStyle.italic : null,
          color: color,
        ),
        children: node.nodes.expand(inline).toList(),
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final fragment = tournamentRulesFragment(html);
    final sections = <({String title, List<Widget> blocks})>[];
    String heading = 'Regolamento';
    var blocks = <Widget>[];
    void finish() {
      if (blocks.isNotEmpty) sections.add((title: heading, blocks: blocks));
      blocks = [];
    }

    Widget paragraph(dom.Node node, {String? marker}) => Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (marker != null) ...[
            SizedBox(
              width: 24,
              child: Text(
                marker,
                style: const TextStyle(
                  color: siteRed,
                  fontWeight: FontWeight.bold,
                  height: 1.65,
                ),
              ),
            ),
            const SizedBox(width: 6),
          ],
          Expanded(
            child: SelectableText.rich(
              TextSpan(children: inline(node)),
              style: const TextStyle(
                fontSize: 15,
                height: 1.65,
                color: Color(0xff435368),
              ),
            ),
          ),
        ],
      ),
    );
    void visit(dom.Node node) {
      if (node is dom.Text) {
        if (node.text.trim().isNotEmpty) blocks.add(paragraph(node));
        return;
      }
      if (node is! dom.Element) return;
      final tag = node.localName;
      if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].contains(tag)) {
        finish();
        heading = node.text.trim();
      } else if (tag == 'ul' || tag == 'ol') {
        var index = 0;
        for (final child in node.children) {
          if (child.localName == 'li') {
            blocks.add(
              paragraph(child, marker: tag == 'ol' ? '${++index}.' : '•'),
            );
          }
        }
      } else if (tag == 'p' || tag == 'blockquote' || tag == 'tr') {
        if (node.text.trim().isNotEmpty) blocks.add(paragraph(node));
      } else if (node.classes.contains('premi-grid')) {
        for (final child in node.children) {
          blocks.add(paragraph(child, marker: '•'));
        }
      } else {
        for (final child in node.nodes) {
          visit(child);
        }
      }
    }

    for (final node in fragment.nodes) {
      visit(node);
    }
    finish();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.only(bottom: 20),
          child: Text(
            'Formula, qualificazioni e regole di gioco',
            style: TextStyle(color: Color(0xff778496), fontSize: 14),
          ),
        ),
        for (var i = 0; i < sections.length; i++)
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: const Color(0xffe5eaf1)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(9),
                      decoration: BoxDecoration(
                        color: const Color(0xffe9edf3),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${i + 1}'.padLeft(2, '0'),
                        style: const TextStyle(
                          color: siteBlue,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          sections[i].title,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: siteBlue,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                ...sections[i].blocks,
              ],
            ),
          ),
      ],
    );
  }
}
