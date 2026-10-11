import 'dart:convert';

import 'package:flutter/widgets.dart';
import 'package:share_plus/share_plus.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'staff_engine.dart';

StaffEngine createStaffEngine(
  String channel,
  void Function(StaffMessage) receive,
) => _MobileEngine(channel, receive);

class _MobileEngine implements StaffEngine {
  _MobileEngine(this.channel, this.receive);
  final String channel;
  final void Function(StaffMessage) receive;
  WebViewController? _controller;
  bool _disposed = false;
  Future<void> _commands = Future<void>.value();
  @override
  Widget get host => _controller == null
      ? const SizedBox.shrink()
      : ExcludeSemantics(
          child: IgnorePointer(child: WebViewWidget(controller: _controller!)),
        );
  @override
  Future<void> start(Uri uri, Map<String, String> headers) async {
    await WebViewCookieManager().clearCookies();
    if (_disposed) return;
    final controller = WebViewController();
    _controller = controller;
    await controller.setJavaScriptMode(JavaScriptMode.unrestricted);
    await controller.addJavaScriptChannel(
      'TosStaff',
      onMessageReceived: (event) {
        final message = decodeStaffMessage(event.message, channel);
        if (!_disposed && message != null) receive(message);
      },
    );
    await controller.setNavigationDelegate(
      NavigationDelegate(
        onNavigationRequest: (request) =>
            Uri.parse(request.url).origin == uri.origin
            ? NavigationDecision.navigate
            : NavigationDecision.prevent,
        onWebResourceError: (error) {
          if (!_disposed && error.isForMainFrame == true) {
            receive({
              'type': 'error',
              'message': 'Connessione allo strumento non riuscita. Riprova.',
            });
          }
        },
      ),
    );
    if (!_disposed) {
      await controller.loadRequest(uri, headers: headers);
    }
  }

  @override
  void send(StaffMessage message) {
    _commands = _commands
        .then((_) async {
          if (_disposed) return;
          Future<void> deliver(StaffMessage value) async {
            if (!_disposed) {
              await _controller?.runJavaScript(
                'window.tosStaffCommand(${jsonEncode({...value, 'channel': channel})});',
              );
            }
          }

          // Keep photographs out of a single oversized WebView evaluation call.
          if (message['type'] == 'files') {
            final context = {
              'document': message['document'],
              'id': message['id'],
            };
            final files = (message['files'] as List).cast<Map>();
            await deliver({
              ...context,
              'type': 'fileBegin',
              'files': [
                for (final file in files)
                  {'name': file['name'], 'mime': file['mime']},
              ],
            });
            for (var index = 0; index < files.length; index++) {
              final data = files[index]['data'] as String;
              for (var offset = 0; offset < data.length; offset += 65536) {
                await deliver({
                  ...context,
                  'type': 'fileChunk',
                  'index': index,
                  'data': data.substring(
                    offset,
                    (offset + 65536).clamp(0, data.length),
                  ),
                });
              }
            }
            await deliver({...context, 'type': 'fileEnd'});
          } else {
            await deliver(message);
          }
        })
        .catchError((Object error) {
          if (!_disposed) {
            receive({
              'type': 'error',
              'message': 'Operazione non riuscita. Riprova.',
            });
          }
        });
  }

  @override
  Future<void> save(StaffMessage file, Rect shareOrigin) async {
    final files = (file['files'] as List? ?? [file]).cast<Map>();
    final names = files
        .map((item) => safeStaffFilename('${item['name']}'))
        .toList();
    await SharePlus.instance.share(
      ShareParams(
        files: [
          for (var i = 0; i < files.length; i++)
            XFile.fromData(
              base64Decode((files[i]['data'] as String).split(',').last),
              name: names[i],
              mimeType: files[i]['mime'] as String?,
            ),
        ],
        fileNameOverrides: names,
        sharePositionOrigin: shareOrigin,
      ),
    );
  }

  @override
  void dispose() {
    _disposed = true;
    _controller?.loadRequest(Uri.parse('about:blank'));
    _controller = null;
  }
}
