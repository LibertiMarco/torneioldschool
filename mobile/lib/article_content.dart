import 'package:flutter/material.dart';
import 'package:html/dom.dart' as dom;
import 'package:html/parser.dart' show parseFragment;
import 'package:markdown/markdown.dart' as markdown;

import 'theme.dart';

dom.DocumentFragment articleFragment(String content) {
  final normalized = content
      .replaceAll(RegExp(r'\r\n?'), '\n')
      .replaceAllMapped(
        RegExp(r'^==(.+)==[ \t]*$', multiLine: true),
        (match) => '### ${match[1]!.trim()}',
      )
      .replaceAllMapped(RegExp(r'==(.+?)=='), (match) => '**${match[1]}**');
  final fragment = parseFragment(
    markdown.markdownToHtml(
      normalized,
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

class ArticleContent extends StatelessWidget {
  const ArticleContent({super.key, required this.content});
  final String content;

  List<InlineSpan> inline(dom.Node node) {
    if (node is dom.Text) {
      return [TextSpan(text: node.text.replaceAll(RegExp(r'[ \t]+'), ' '))];
    }
    if (node is! dom.Element) return [];
    final tag = node.localName;
    if (tag == 'br') return [const TextSpan(text: '\n')];
    if (tag == 'ul' || tag == 'ol') return [];
    return [
      TextSpan(
        style: TextStyle(
          fontWeight: ['b', 'strong'].contains(tag) ? FontWeight.w700 : null,
          fontStyle: ['i', 'em'].contains(tag) ? FontStyle.italic : null,
          decoration: ['u', 'a'].contains(tag)
              ? TextDecoration.underline
              : null,
          color: tag == 'a' ? siteBlue : null,
        ),
        children: node.nodes.expand(inline).toList(),
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    Widget paragraph(dom.Node node, {String? marker}) => Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (marker != null) ...[
            SizedBox(
              width: 28,
              child: Text(
                marker,
                style: const TextStyle(
                  fontSize: 16,
                  height: 1.65,
                  color: siteRed,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 4),
          ],
          Expanded(
            child: SelectableText.rich(
              TextSpan(children: inline(node)),
              style: const TextStyle(
                fontSize: 16,
                height: 1.65,
                color: Color(0xff435368),
              ),
            ),
          ),
        ],
      ),
    );
    List<Widget> blocks(dom.Node node) {
      if (node is dom.Text) {
        return node.text.trim().isEmpty ? [] : [paragraph(node)];
      }
      if (node is! dom.Element) return [];
      final tag = node.localName;
      if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].contains(tag)) {
        return [
          Padding(
            padding: const EdgeInsets.only(top: 20, bottom: 12),
            child: Semantics(
              header: true,
              child: SelectableText.rich(
                TextSpan(children: inline(node)),
                style: TextStyle(
                  fontSize: tag == 'h1' || tag == 'h2' ? 23 : 19,
                  height: 1.3,
                  fontWeight: FontWeight.w700,
                  color: siteBlue,
                ),
              ),
            ),
          ),
        ];
      }
      if (tag == 'ul' || tag == 'ol') {
        var index = int.tryParse(node.attributes['start'] ?? '') ?? 1;
        final items = <Widget>[];
        for (final item in node.children.where(
          (item) => item.localName == 'li',
        )) {
          items.add(paragraph(item, marker: tag == 'ol' ? '${index++}.' : '•'));
          for (final nested in item.children.where(
            (child) => child.localName == 'ul' || child.localName == 'ol',
          )) {
            items.add(
              Padding(
                padding: const EdgeInsets.only(left: 32),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: blocks(nested),
                ),
              ),
            );
          }
        }
        return items;
      }
      if (tag == 'blockquote') {
        return [
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.fromLTRB(16, 12, 12, 0),
            decoration: const BoxDecoration(
              color: Color(0xffe8edf5),
              border: Border(left: BorderSide(color: siteRed, width: 3)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: node.nodes.expand(blocks).toList(),
            ),
          ),
        ];
      }
      if (tag == 'hr') {
        return [
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Divider(),
          ),
        ];
      }
      if (tag == 'p' || tag == 'pre') return [paragraph(node)];
      return node.nodes.expand(blocks).toList();
    }

    final fragment = articleFragment(content);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: fragment.nodes.expand(blocks).toList(),
    );
  }
}
