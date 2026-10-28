import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Email links are accepted only for the two account actions on our backend.
Uri? accountEmailLink(Uri base, String value) {
  final uri = Uri.tryParse(value.trim());
  if (uri == null ||
      !['http', 'https'].contains(uri.scheme) ||
      uri.host.isEmpty ||
      uri.origin != base.origin ||
      uri.userInfo.isNotEmpty) {
    return null;
  }
  if (!['verify_email.php', 'reset_password.php'].any(
    (path) => uri.path == '${base.path.replaceFirst(RegExp(r'/$'), '')}/$path',
  )) {
    return null;
  }
  return uri;
}

class AuthWebPage extends StatefulWidget {
  const AuthWebPage({super.key, required this.uri, required this.title});
  final Uri uri;
  final String title;

  @override
  State<AuthWebPage> createState() => _AuthWebPageState();
}

class _AuthWebPageState extends State<AuthWebPage> {
  late final WebViewController _controller;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final uri = Uri.tryParse(request.url);
            if (uri == null || !['http', 'https'].contains(uri.scheme)) {
              return NavigationDecision.prevent;
            }
            if (uri.origin == widget.uri.origin && uri.userInfo.isEmpty) {
              if (request.isMainFrame &&
                  uri.path == widget.uri.resolve('login.php').path) {
                Navigator.of(context).pop();
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            }
            // reCAPTCHA frames may navigate, but never replace the account page.
            return !request.isMainFrame &&
                    uri.scheme == 'https' &&
                    ['www.google.com', 'www.recaptcha.net'].contains(uri.host)
                ? NavigationDecision.navigate
                : NavigationDecision.prevent;
          },
          onWebResourceError: (failure) {
            if (mounted && failure.isForMainFrame == true) {
              setState(() => _error = 'Pagina non disponibile. Riprova.');
            }
          },
        ),
      )
      ..loadRequest(widget.uri);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.title),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Torna al login'),
        ),
      ],
    ),
    body: _error == null
        ? WebViewWidget(controller: _controller)
        : Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!),
                TextButton(
                  onPressed: () {
                    setState(() => _error = null);
                    _controller.loadRequest(widget.uri);
                  },
                  child: const Text('Riprova'),
                ),
              ],
            ),
          ),
  );
}
