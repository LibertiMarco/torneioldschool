import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'api.dart';
import 'auth.dart';
import 'external_link_button.dart';

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
  @override
  void dispose() {
    _email.dispose();
    _password.clear();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_pending || !_form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _pending = true;
      _message = null;
    });
    try {
      final captcha = await Navigator.of(context).push<String>(
        MaterialPageRoute(
          builder: (_) => _CaptchaPage(
            uri: widget.auth.api.uri('api/mobile/v1/captcha.php'),
          ),
        ),
      );
      if (!mounted || captcha == null || captcha.isEmpty) return;
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
                  if (_message != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Semantics(
                        liveRegion: true,
                        child: Text(_message!),
                      ),
                    ),
                  FilledButton(
                    onPressed: _pending ? null : _submit,
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
                            _message = null;
                            _password.clear();
                          }),
                    child: Text(
                      _recovery ? 'Torna al login' : 'Password dimenticata?',
                    ),
                  ),
                  if (!_recovery)
                    ExternalLinkButton(
                      uri: widget.auth.api.uri('register.php'),
                      label: 'Crea un account',
                      icon: Icons.person_add_alt_outlined,
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

/// Only the anti-abuse challenge is a web surface; credentials stay in Flutter.
class _CaptchaPage extends StatefulWidget {
  const _CaptchaPage({required this.uri});
  final Uri uri;
  @override
  State<_CaptchaPage> createState() => _CaptchaPageState();
}

class _CaptchaPageState extends State<_CaptchaPage> {
  late final WebViewController _controller;
  String? error;
  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..addJavaScriptChannel(
        'TosCaptcha',
        onMessageReceived: (message) {
          if (mounted &&
              message.message.isNotEmpty &&
              message.message.length < 8192) {
            Navigator.pop(context, message.message);
          }
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final uri = Uri.tryParse(request.url);
            return uri != null &&
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
                () => error =
                    'Verifica non disponibile. Torna indietro e riprova.',
              );
            }
          },
        ),
      )
      ..loadRequest(widget.uri);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Verifica di sicurezza')),
    body: error == null
        ? WebViewWidget(controller: _controller)
        : Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(error!),
            ),
          ),
  );
}
