import 'package:flutter/widgets.dart';
import 'package:web/web.dart' as web;

Widget staffToolEmbed({required Uri uri, required Widget fallback}) =>
    HtmlElementView.fromTagName(
      key: ValueKey(uri.toString()),
      tagName: 'iframe',
      onElementCreated: (element) {
        final frame = element as web.HTMLIFrameElement;
        frame.src = uri.toString();
        frame.title = 'Strumento di gestione Old School';
        frame.style
          ..border = '0'
          ..width = '100%'
          ..height = '100%';
      },
    );
