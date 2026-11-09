import 'dart:convert';
import 'dart:js_interop';

import 'package:flutter/widgets.dart';
import 'package:web/web.dart' as web;

import 'staff_engine.dart';

StaffEngine createStaffEngine(
  String channel,
  void Function(StaffMessage) receive,
) => _BrowserEngine(channel, receive);

class _BrowserEngine implements StaffEngine {
  _BrowserEngine(this.channel, this.receive);
  final String channel;
  final void Function(StaffMessage) receive;
  web.HTMLIFrameElement? _frame;
  JSFunction? _listener;
  String? _origin;
  @override
  Widget get host => const SizedBox.shrink();
  @override
  Future<void> start(Uri uri, Map<String, String> headers) async {
    _origin = uri.origin;
    final frame = web.HTMLIFrameElement()
      ..src = uri.toString()
      ..title = 'Controller staff'
      ..setAttribute('aria-hidden', 'true')
      ..tabIndex = -1;
    frame.style
      ..position = 'fixed'
      ..left = '-10000px'
      ..top = '0'
      ..width = '1200px'
      ..height = '1800px'
      ..border = '0'
      ..pointerEvents = 'none';
    _frame = frame;
    _listener = ((web.MessageEvent event) {
      if (event.origin != _origin || event.source != frame.contentWindow) {
        return;
      }
      final raw = event.data.dartify();
      if (raw is! String) return;
      final message = decodeStaffMessage(raw, channel);
      if (message != null) receive(message);
    }).toJS;
    web.window.addEventListener('message', _listener);
    web.document.body!.appendChild(frame);
  }

  @override
  void send(StaffMessage message) => _frame?.contentWindow?.postMessage(
    jsonEncode({...message, 'channel': channel}).toJS,
    _origin!.toJS,
  );

  @override
  Future<void> save(StaffMessage file, Rect shareOrigin) async {
    final files = file['files'] as List? ?? [file];
    for (final item in files) {
      final link = web.HTMLAnchorElement()
        ..href = item['data'] as String
        ..download = safeStaffFilename(item['name'] as String);
      web.document.body!.appendChild(link);
      link.click();
      link.remove();
    }
  }

  @override
  void dispose() {
    if (_listener != null) web.window.removeEventListener('message', _listener);
    _frame?.remove();
    _frame = null;
  }
}
