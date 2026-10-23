import 'package:flutter/material.dart';

import 'access_gate.dart';
import 'account_page.dart';
import 'api.dart';
import 'auth.dart';
import 'details.dart' show MatchPage;
import 'home_dashboard.dart' show ArticlePage;
import 'push.dart' show pushLink;

enum PushScreen { match, article, account }

class PushDestination {
  const PushDestination(this.screen, {this.id, this.section = 'calcio'});
  final PushScreen screen;
  final String? id;
  final String section;
}

PushDestination? pushDestination(String? value) {
  final url = pushLink(value);
  if (url == null) return null;
  final section = url.queryParameters['sezione'] == 'esport'
      ? 'esport'
      : 'calcio';
  if (url.path == '/tornei/partita_eventi.php' || url.path == '/articolo.php') {
    final ids = url.queryParametersAll['id'];
    if (ids == null ||
        ids.length != 1 ||
        !RegExp(r'^[1-9][0-9]{0,9}$').hasMatch(ids.single)) {
      return null;
    }
    final id = ids.single;
    if (int.parse(id) > 2147483647) return null;
    return PushDestination(
      url.path == '/articolo.php' ? PushScreen.article : PushScreen.match,
      id: id,
      section: section,
    );
  }
  // Unknown website destinations remain in the app; never launch a browser.
  return PushDestination(PushScreen.account, section: section);
}

/// Notification taps can arrive before UpdateGate has attached the navigator.
/// Keep the latest tapped destination until the first app route is ready.
class NotificationNavigator extends NavigatorObserver {
  NotificationNavigator({
    required this.api,
    required this.auth,
    required this.navigatorKey,
  });
  final TosApi api;
  final TosAuth auth;
  final GlobalKey<NavigatorState> navigatorKey;
  PushDestination? _pending;
  final Set<String> _seen = {};
  bool _scheduled = false;
  bool _disposed = false;
  bool _sessionReady = false;
  bool _waitingForSession = false;

  bool open(String? value, {String? messageId}) {
    if (_disposed) return false;
    final destination = pushDestination(value);
    if (destination == null) return false;
    if (messageId != null && messageId.isNotEmpty) {
      if (!_seen.add(messageId)) return false;
      if (_seen.length > 32) _seen.remove(_seen.first);
    }
    _pending = destination;
    _schedule();
    return true;
  }

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    super.didPush(route, previousRoute);
    _schedule();
  }

  void _schedule() {
    if (_disposed || _scheduled || _pending == null) return;
    if (!_sessionReady) {
      if (!_waitingForSession) {
        _waitingForSession = true;
        auth.ensureRestored().then((_) {
          if (_disposed) return;
          _sessionReady = true;
          _schedule();
        });
      }
      return;
    }
    _scheduled = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _scheduled = false;
      if (_disposed) return;
      final target = _pending;
      final nav = navigatorKey.currentState;
      if (target == null || nav == null || !nav.mounted) return;
      _pending = null;
      nav.push(
        MaterialPageRoute<void>(
          builder: (_) => AccessGate(
            fullPage: true,
            builder: (_) => switch (target.screen) {
              PushScreen.match => MatchPage(api: api, id: target.id!),
              PushScreen.article => ArticlePage(api: api, id: target.id!),
              PushScreen.account => AccountPage(
                auth: auth,
                section: target.section,
              ),
            },
          ),
        ),
      );
    });
    WidgetsBinding.instance.ensureVisualUpdate();
  }

  void dispose() {
    _disposed = true;
    _pending = null;
    _seen.clear();
  }
}
