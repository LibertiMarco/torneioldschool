import 'dart:async';

import 'package:flutter/services.dart';

import 'auth.dart';
import 'api.dart';

class RequestNotificationActions {
  RequestNotificationActions({
    required this.auth,
    required this.openRequests,
    required this.showMessage,
    MethodChannel? channel,
  }) : channel = channel ?? const MethodChannel('tos/request_actions');
  final TosAuth auth;
  final void Function() openRequests;
  final void Function(String) showMessage;
  final MethodChannel channel;
  bool _handling = false, _disposed = false, _again = false;
  Future<void> start() async {
    channel.setMethodCallHandler((call) async {
      if (call.method == 'pending') await _drain();
    });
    await _drain();
  }

  Future<void> _drain() async {
    if (_disposed) return;
    if (_handling) {
      _again = true;
      return;
    }
    _handling = true;
    try {
      final data = await channel.invokeMapMethod<String, dynamic>('take');
      if (data == null || _disposed) return;
      await handle(data);
    } on MissingPluginException {
      /* Web preview has no operating-system notification tray. */
    } finally {
      _handling = false;
      if (_again && !_disposed) {
        _again = false;
        unawaited(_drain());
      }
    }
  }

  Future<void> handle(Map data) async {
    final id = int.tryParse('${data['request_id']}');
    final action = data['action'];
    if (id == null ||
        id <= 0 ||
        !['accept', 'reject', 'open'].contains(action)) {
      return;
    }
    if (action == 'open') {
      openRequests();
      return;
    }
    await auth.ensureRestored();
    if (_disposed) return;
    final recipient = '${data['recipient_id'] ?? ''}';
    if (auth.user == null ||
        !auth.admin ||
        (recipient.isNotEmpty && recipient != '${auth.user?['id']}')) {
      showMessage(
        'Accedi con l’account amministratore destinatario della notifica.',
      );
      openRequests();
      return;
    }
    try {
      final result = await auth.authorized(
        'api/mobile/v1/player_link_requests.php',
        body: {
          'action': 'review',
          'request_id': id,
          'accept': action == 'accept',
        },
      );
      if (!_disposed) {
        showMessage('${result['message'] ?? 'Richiesta gestita.'}');
      }
    } catch (error) {
      if (!_disposed) {
        showMessage(
          error is ApiError ? error.message : 'Operazione non riuscita. La richiesta non è stata confermata: riprova nella gestione.',
        );
      }
    }
  }

  void dispose() {
    _disposed = true;
    channel.setMethodCallHandler(null);
  }
}
