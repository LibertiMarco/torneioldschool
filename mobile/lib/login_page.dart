import 'dart:async';

import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'api.dart';
import 'auth.dart';
import 'auth_web_page.dart';

class NativeLoginPage extends StatefulWidget {
  const NativeLoginPage({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<NativeLoginPage> createState() => _NativeLoginPageState();
}

class _NativeLoginPageState extends State<NativeLoginPage> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController(), _password = TextEditingController();
  bool _hidden = true, _pending = false, _recovery = false;
  String? _message;
  String? _captcha;
  Timer? _captchaExpiry;
  bool _verifying = false;

  void _verifyCaptcha() {
    if (_pending || _verifying) return;
    FocusScope.of(context).unfocus();
    setState(() => _verifying = true);
  }

  void _captchaCompleted(String token) {
    _captchaExpiry?.cancel();
    setState(() {
      _captcha = token.isEmpty ? null : token;
      _message = null;
      if (token.isNotEmpty) _verifying = false;
    });
    if (token.isNotEmpty) {
      _captchaExpiry = Timer(const Duration(seconds: 110), () {
        if (mounted) setState(() => _captcha = null);
      });
    }
  }

  Future<void> _openEmailLink() async {
    var input = '';
    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Link ricevuto via email'),
        content: TextField(
          onChanged: (value) => input = value,
          decoration: const InputDecoration(
            labelText: 'Incolla il link di conferma o recupero',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Annulla'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, input),
            child: const Text('Apri'),
          ),
        ],
      ),
    );
    if (!mounted || value == null) return;
    final uri = accountEmailLink(widget.auth.api.baseUrl, value);
    if (uri == null) {
      setState(
        () => _message =
            'Inserisci un link di conferma o recupero di Tornei Old School.',
      );
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            AuthWebPage(uri: uri, title: 'Conferma account o password'),
      ),
    );
  }

  @override
  void dispose() {
    _captchaExpiry?.cancel();
    _email.dispose();
    _password.clear();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_pending || _verifying || !_form.currentState!.validate()) return;
    final captcha = _captcha;
    if (captcha == null) {
      setState(
        () => _message = 'Completa prima la verifica Non sono un robot.',
      );
      return;
    }
    _captchaExpiry?.cancel();
    FocusScope.of(context).unfocus();
    setState(() {
      _pending = true;
      _captcha = null;
      _message = null;
    });
    try {
      if (_recovery) {
        final response = await widget.auth.api.request(
          'api/mobile/v1/password_reset.php',
          body: {'email': _email.text.trim(), 'captcha': captcha},
        );
        if (mounted) setState(() => _message = '${response['message']}');
      } else {
        final success = await widget.auth.loginWithPassword(
          _email.text,
          _password.text,
          captcha,
        );
        if (!mounted) return;
        if (success) {
          _password.clear();
          Navigator.pop(context);
        } else {
          setState(() => _message = widget.auth.error);
        }
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _message = error is ApiError
              ? error.message
              : 'Servizio non disponibile. Riprova.',
        );
      }
    } finally {
      if (mounted) setState(() => _pending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_recovery ? 'Recupera password' : 'Accedi')),
    body: Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 480),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _form,
            child: AutofillGroup(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(
                    Icons.sports_soccer_rounded,
                    size: 48,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(height: 20),
                  Text(
                    _recovery
                        ? 'Ritrova il tuo account'
                        : 'Bentornato in campo',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 8),
                  const Text('Usa lo stesso account di Tornei Old School.'),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: _email,
                    enabled: !_pending,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [
                      AutofillHints.username,
                      AutofillHints.email,
                    ],
                    autocorrect: false,
                    textInputAction: _recovery
                        ? TextInputAction.done
                        : TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      prefixIcon: Icon(Icons.mail_outline),
                    ),
                    validator: (value) =>
                        value != null &&
                            RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                .hasMatch(value.trim())
                        ? null
                        : 'Inserisci una email valida.',
                  ),
                  if (!_recovery) ...[
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      enabled: !_pending,
                      obscureText: _hidden,
                      autofillHints: const [AutofillHints.password],
                      autocorrect: false,
                      enableSuggestions: false,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: _hidden
                              ? 'Mostra password'
                              : 'Nascondi password',
                          onPressed: () => setState(() => _hidden = !_hidden),
                          icon: Icon(
                            _hidden
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                        ),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Inserisci la password.'
                          : null,
                    ),
                  ],
                  const SizedBox(height: 20),
                  if (_verifying)
                    _InlineCaptcha(
                      uri: widget.auth.api.uri('api/mobile/v1/captcha.php'),
                      onToken: _captchaCompleted,
                    )
                  else
                    OutlinedButton.icon(
                      onPressed: _pending || _verifying || _captcha != null
                          ? null
                          : _verifyCaptcha,
                      icon: Icon(
                        _captcha != null
                            ? Icons.check_circle_outline
                            : Icons.check_box_outline_blank,
                      ),
                      label: Text(
                        _verifying
                            ? 'Verifica in corso…'
                            : _captcha != null
                            ? 'Verifica completata'
                            : 'Non sono un robot',
                      ),
                    ),
                  const SizedBox(height: 12),
                  if (_message != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Semantics(
                        liveRegion: true,
                        child: Text(_message!),
                      ),
                    ),
                  FilledButton(
                    onPressed: _pending || _verifying || _captcha == null
                        ? null
                        : _submit,
                    child: Text(
                      _pending
                          ? 'Attendi…'
                          : _recovery
                          ? 'Invia link di recupero'
                          : 'Accedi',
                    ),
                  ),
                  TextButton(
                    onPressed: _pending
                        ? null
                        : () => setState(() {
                            _recovery = !_recovery;
                            _verifying = false;
                            _message = null;
                            _password.clear();
                            _captcha = null;
                            _captchaExpiry?.cancel();
                          }),
                    child: Text(
                      _recovery ? 'Torna al login' : 'Password dimenticata?',
                    ),
                  ),
                  if (!_recovery)
                    TextButton.icon(
                      onPressed: _pending
                          ? null
                          : () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => AuthWebPage(
                                  uri: widget.auth.api.uri('register.php'),
                                  title: 'Crea un account',
                                ),
                              ),
                            ),
                      label: const Text('Crea un account'),
                      icon: const Icon(Icons.person_add_alt_outlined),
                    ),
                  TextButton(
                    onPressed: _pending ? null : _openEmailLink,
                    child: const Text('Apri link ricevuto via email'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

/// The security challenge stays inside the credential form.
class _InlineCaptcha extends StatefulWidget {
  const _InlineCaptcha({required this.uri, required this.onToken});
  final ValueChanged<String> onToken;
  final Uri uri;
  @override
  State<_InlineCaptcha> createState() => _InlineCaptchaState();
}

class _InlineCaptchaState extends State<_InlineCaptcha> {
  late final WebViewController _controller;
  String? error;
  double _height = 166;
  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..addJavaScriptChannel(
        'TosCaptcha',
        onMessageReceived: (message) {
          if (mounted && message.message.length < 8192) {
            widget.onToken(message.message);
          }
        },
      )
      ..addJavaScriptChannel(
        'TosCaptchaStatus',
        onMessageReceived: (_) {
          if (mounted) {
            setState(() => error = 'Verifica non disponibile. Tocca Riprova.');
          }
        },
      )
      ..addJavaScriptChannel(
        'TosCaptchaLayout',
        onMessageReceived: (message) {
          final height = double.tryParse(message.message);
          if (mounted && height != null && height.isFinite) {
            setState(() => _height = height.clamp(166, 700));
          }
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final uri = Uri.tryParse(request.url);
            return uri != null &&
                    (uri.scheme == 'https' || uri.scheme == 'http') &&
                    (uri.origin == widget.uri.origin ||
                        (uri.scheme == 'https' &&
                            [
                              'www.google.com',
                              'www.recaptcha.net',
                            ].contains(uri.host)))
                ? NavigationDecision.navigate
                : NavigationDecision.prevent;
          },
          onWebResourceError: (failure) {
            if (mounted && failure.isForMainFrame == true) {
              setState(
                () => error = 'Verifica non disponibile. Tocca Riprova.',
              );
            }
          },
        ),
      )
      ..loadRequest(widget.uri);
  }

  @override
  Widget build(BuildContext context) => error == null
      ? SizedBox(
          height: _height,
          child: WebViewWidget(controller: _controller),
        )
      : Column(
          children: [
            Text(error!),
            TextButton.icon(
              onPressed: () {
                setState(() => error = null);
                _controller.loadRequest(widget.uri);
              },
              icon: const Icon(Icons.refresh),
              label: const Text('Riprova verifica'),
            ),
          ],
        );
}
