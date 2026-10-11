import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_quill/flutter_quill.dart';
import 'package:html/dom.dart' as html;
import 'package:html/parser.dart' as html;

// The article controllers accept HTML. Keep formatting while editing in Flutter.
List<Map<String, dynamic>> staffHtmlDelta(String value) {
  final ops = <Map<String, dynamic>>[];
  void insert(String text, Map<String, dynamic> attrs) {
    if (text.isEmpty) return;
    ops.add({'insert': text, if (attrs.isNotEmpty) 'attributes': attrs});
  }

  void visit(html.Node node, Map<String, dynamic> attrs) {
    if (node is html.Text) {
      insert(node.text, attrs);
      return;
    }
    if (node is! html.Element) return;
    final tag = node.localName;
    final next = {...attrs};
    if (tag == 'strong' || tag == 'b') next['bold'] = true;
    if (tag == 'em' || tag == 'i') next['italic'] = true;
    if (tag == 'u') next['underline'] = true;
    if (tag == 's' || tag == 'del') next['strike'] = true;
    if (tag == 'a' && node.attributes['href'] != null) {
      next['link'] = node.attributes['href'];
    }
    if (tag == 'br') {
      insert('\n', {});
      return;
    }
    for (final child in node.nodes) {
      visit(child, next);
    }
    if ([
      'p',
      'div',
      'li',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'blockquote',
    ].contains(tag)) {
      final line = <String, dynamic>{};
      if (tag?.startsWith('h') == true) {
        line['header'] = int.parse(tag!.substring(1));
      }
      if (tag == 'li') {
        line['list'] = node.parent?.localName == 'ol' ? 'ordered' : 'bullet';
      }
      if (tag == 'blockquote') line['blockquote'] = true;
      insert('\n', line);
    }
  }

  for (final node in html.parseFragment(value).nodes) {
    visit(node, {});
  }
  if (ops.isEmpty || !(ops.last['insert'] as String).endsWith('\n')) {
    insert('\n', {});
  }
  return ops;
}

String staffDeltaHtml(List<dynamic> ops) {
  const escape = HtmlEscape(HtmlEscapeMode.element);
  const attrEscape = HtmlEscape(HtmlEscapeMode.attribute);
  final out = StringBuffer(), line = StringBuffer();
  String inline(String value, Map attrs) {
    var text = escape.convert(value);
    if (attrs['bold'] == true) text = '<strong>$text</strong>';
    if (attrs['italic'] == true) text = '<em>$text</em>';
    if (attrs['underline'] == true) text = '<u>$text</u>';
    if (attrs['strike'] == true) text = '<s>$text</s>';
    final link = attrs['link'];
    if (link is String &&
        ['http', 'https', 'mailto'].contains(Uri.tryParse(link)?.scheme)) {
      text = '<a href="${attrEscape.convert(link)}">$text</a>';
    }
    return text;
  }

  for (final op in ops) {
    if (op['insert'] is! String) continue;
    final attrs = op['attributes'] as Map? ?? {};
    final parts = (op['insert'] as String).split('\n');
    for (var i = 0; i < parts.length; i++) {
      line.write(inline(parts[i], attrs));
      if (i < parts.length - 1) {
        final header = attrs['header'];
        final tag = header is int && header >= 1 && header <= 6
            ? 'h$header'
            : attrs['blockquote'] == true
            ? 'blockquote'
            : attrs['list'] != null
            ? 'li'
            : 'p';
        final content = '<$tag>${line.isEmpty ? '<br>' : line}</$tag>';
        out.write(
          attrs['list'] == 'ordered'
              ? '<ol>$content</ol>'
              : attrs['list'] == 'bullet'
              ? '<ul>$content</ul>'
              : content,
        );
        line.clear();
      }
    }
  }
  if (line.isNotEmpty) out.write('<p>$line</p>');
  return out.toString();
}

class StaffRichEditor extends StatefulWidget {
  const StaffRichEditor({
    super.key,
    required this.value,
    required this.label,
    required this.onChanged,
  });
  final String value, label;
  final ValueChanged<String> onChanged;
  @override
  State<StaffRichEditor> createState() => _StaffRichEditorState();
}

class _StaffRichEditorState extends State<StaffRichEditor> {
  late final QuillController _controller;
  final _focus = FocusNode();
  String _last = '';
  @override
  void initState() {
    super.initState();
    _controller = QuillController(
      document: Document.fromJson(staffHtmlDelta(widget.value)),
      selection: const TextSelection.collapsed(offset: 0),
    );
    _last = staffDeltaHtml(_controller.document.toDelta().toJson());
    _controller.addListener(_changed);
  }

  void _changed() {
    final value = staffDeltaHtml(_controller.document.toDelta().toJson());
    if (value != _last) {
      _last = value;
      widget.onChanged(value);
    }
  }

  @override
  void didUpdateWidget(StaffRichEditor oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!_focus.hasFocus &&
        oldWidget.value != widget.value &&
        widget.value != _last) {
      _controller.removeListener(_changed);
      _controller.document = Document.fromJson(staffHtmlDelta(widget.value));
      _last = staffDeltaHtml(_controller.document.toDelta().toJson());
      _controller.addListener(_changed);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(widget.label, style: const TextStyle(fontWeight: FontWeight.w700)),
      const SizedBox(height: 8),
      DecoratedBox(
        decoration: BoxDecoration(
          border: Border.all(color: const Color(0xffdbe3f0)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          children: [
            QuillSimpleToolbar(
              controller: _controller,
              config: const QuillSimpleToolbarConfig(
                showFontFamily: false,
                showFontSize: false,
                showSmallButton: false,
                showColorButton: false,
                showBackgroundColorButton: false,
                showInlineCode: false,
                showListCheck: false,
                showCodeBlock: false,
                showIndent: false,
                showSearchButton: false,
                showSubscript: false,
                showSuperscript: false,
                showDividers: false,
              ),
            ),
            const Divider(height: 1),
            QuillEditor.basic(
              controller: _controller,
              focusNode: _focus,
              config: const QuillEditorConfig(
                padding: EdgeInsets.all(14),
                minHeight: 160,
                maxHeight: 320,
                placeholder: 'Scrivi il testo della sezione…',
              ),
            ),
          ],
        ),
      ),
    ],
  );
}
