import 'package:flutter/material.dart';

import 'auth.dart';

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
