import 'package:flutter/material.dart';

import 'auth.dart';
import 'api.dart';

class AccessRequiredPanel extends StatelessWidget {
  const AccessRequiredPanel({super.key, required this.retry});
  final VoidCallback retry;

  static bool handles(BuildContext context, Object? error) =>
      error is ApiError &&
      (error.accessRequired ||
          (error.status == 403 && AppAuthScope.of(context)?.user == null));

  @override
  Widget build(BuildContext context) {
    final auth = AppAuthScope.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.lock_outline, size: 48),
            const SizedBox(height: 16),
            const Text(
              'Accedi per visualizzare questo contenuto.',
              textAlign: TextAlign.center,
            ),
            if (auth != null) ...[
              const SizedBox(height: 24),
              AnimatedBuilder(
                animation: auth,
                builder: (context, _) => FilledButton.icon(
                  onPressed: auth.busy
                      ? null
                      : () async {
                          await auth.login();
                          if (context.mounted && auth.user != null) retry();
                        },
                  icon: const Icon(Icons.login),
                  label: Text(auth.busy ? 'Accesso in corso…' : 'Accedi'),
                ),
              ),
              AnimatedBuilder(
                animation: auth,
                builder: (context, _) => auth.error == null
                    ? const SizedBox.shrink()
                    : Padding(
                        padding: const EdgeInsets.only(top: 16),
                        child: Text(auth.error!, textAlign: TextAlign.center),
                      ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class AppAuthScope extends InheritedWidget {
  const AppAuthScope({super.key, required this.auth, required super.child});
  final TosAuth auth;
  static TosAuth? of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AppAuthScope>()?.auth;
  @override
  bool updateShouldNotify(AppAuthScope oldWidget) => auth != oldWidget.auth;
}

class AccessGate extends StatelessWidget {
  const AccessGate({super.key, required this.builder, this.fullPage = false});
  final WidgetBuilder builder;
  final bool fullPage;

  @override
  Widget build(BuildContext context) {
    final auth = AppAuthScope.of(context);
    if (auth == null) return builder(context);
    return AnimatedBuilder(
      animation: auth,
      builder: (context, _) {
        if (auth.user != null) return builder(context);
        final prompt = Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.lock_outline, size: 48),
                const SizedBox(height: 16),
                const Text(
                  'Accedi per consultare i tornei e le classifiche complete.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: auth.busy ? null : auth.login,
                  icon: const Icon(Icons.login),
                  label: Text(auth.busy ? 'Accesso in corso…' : 'Accedi'),
                ),
                if (auth.error != null) ...[
                  const SizedBox(height: 16),
                  Text(auth.error!, textAlign: TextAlign.center),
                ],
              ],
            ),
          ),
        );
        return fullPage
            ? Scaffold(
                appBar: AppBar(title: const Text('Accesso richiesto')),
                body: prompt,
              )
            : prompt;
      },
    );
  }
}
