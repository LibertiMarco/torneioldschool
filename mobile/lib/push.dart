import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'auth.dart';

Uri? pushLink(String? value) {
  final uri = Uri.tryParse(value ?? '');
  if (uri == null ||
      uri.scheme != 'https' ||
      uri.host != 'torneioldschool.it' ||
      uri.userInfo.isNotEmpty ||
      (uri.hasPort && uri.port != 443)) {
    return null;
  }
  return uri;
}

abstract class PushBackend {
  Future<void> initialize();
  Future<bool> allowed();
  Future<bool> requestPermission();
  Future<String?> token();
  Future<void> deleteToken();
  Stream<String> get tokenChanges;
}

class FirebasePushBackend implements PushBackend {
  static const _appleRegistration = MethodChannel('tos/push_registration');
  @override
  Future<void> initialize() async {
    if (Firebase.apps.isEmpty) await Firebase.initializeApp();
  }

  @override
  Future<bool> allowed() async =>
      (await FirebaseMessaging.instance.getNotificationSettings())
          .authorizationStatus ==
      AuthorizationStatus.authorized;
  @override
  Future<bool> requestPermission() async =>
      (await FirebaseMessaging.instance.requestPermission())
          .authorizationStatus ==
      AuthorizationStatus.authorized;
  @override
  Future<String?> token() async {
    await FirebaseMessaging.instance.setAutoInitEnabled(true);
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      await _appleRegistration.invokeMethod<void>('register');
      // APNs registration can complete after the permission dialog closes.
      for (var attempt = 0; attempt <= 60; attempt++) {
        if (await FirebaseMessaging.instance.getAPNSToken() != null) {
          return FirebaseMessaging.instance.getToken();
        }
        final registrationError = await _appleRegistration.invokeMethod<String>(
          'registrationError',
        );
        if (registrationError != null) {
          throw StateError(
            'Registrazione notifiche Apple non riuscita: $registrationError',
          );
        }
        if (attempt == 60) break;
        await Future<void>.delayed(const Duration(milliseconds: 500));
      }
      throw StateError(
        'Apple non ha completato la registrazione notifiche. Verifica la connessione e riprova.',
      );
    }
    return FirebaseMessaging.instance.getToken();
  }

  @override
  Future<void> deleteToken() async {
    await FirebaseMessaging.instance.setAutoInitEnabled(false);
    await FirebaseMessaging.instance.deleteToken();
  }

  @override
  Stream<String> get tokenChanges => FirebaseMessaging.instance.onTokenRefresh;
}

class PushController extends ChangeNotifier with WidgetsBindingObserver {
  PushController({
    required this.auth,
    required this.backend,
    required this.supported,
  });
  final TosAuth auth;
  final PushBackend backend;
  final bool supported;
  bool ready = false, busy = false, enabled = false;
  String? message;
  String? _token, _userId;
  bool _disposed = false;
  Timer? _startupTimer;
  StreamSubscription<String>? _tokenSubscription;
  Future<void> _queue = Future.value();
  String get _preferenceKey =>
      'tos.push.${auth.api.baseUrl}.${auth.user?['id']}';

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  Future<void> start() async {
    if (!supported) return;
    WidgetsBinding.instance.addObserver(this);
    auth.addListener(_authChanged);
    try {
      final initialized = Completer<void>();
      _startupTimer = Timer(const Duration(seconds: 15), () {
        if (!initialized.isCompleted) {
          initialized.completeError(
            TimeoutException('Firebase initialization'),
          );
        }
      });
      unawaited(
        backend.initialize().then(
          (_) {
            if (!initialized.isCompleted) initialized.complete();
          },
          onError: (Object error, StackTrace trace) {
            if (!initialized.isCompleted) {
              initialized.completeError(error, trace);
            }
          },
        ),
      );
      try {
        await initialized.future;
      } finally {
        _startupTimer?.cancel();
      }
      if (_disposed) return;
      ready = true;
      _tokenSubscription = backend.tokenChanges.listen((token) {
        if (enabled && auth.user != null) {
          unawaited(
            _serialize(() => _register(token)).catchError((Object _) {
              message = 'Impossibile aggiornare le notifiche. Riprova.';
              _notify();
            }),
          );
        }
      });
      await _serialize(_restore);
    } catch (_) {
      message = 'Notifiche temporaneamente non disponibili. Riapri l’app per riprovare.';
    }
    _notify();
  }

  Future<void> _serialize(Future<void> Function() action) {
    final next = _queue.then((_) async {
      if (!_disposed) await action();
    });
    _queue = next.catchError((Object _) {});
    return next;
  }

  void _authChanged() {
    final id = auth.user?['id']?.toString();
    if (id == _userId) return;
    _userId = id;
    enabled = false;
    _token = null;
    message = null;
    _notify();
    if (ready) unawaited(_serialize(_restore));
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && ready) {
      unawaited(_serialize(_restore));
    }
  }

  Future<void> _register(String token) async {
    if (auth.user == null) return;
    final userId = auth.user?['id']?.toString();
    final preferenceKey = _preferenceKey;
    final old = _token ?? await auth.storage.read('$preferenceKey.token');
    _requireUser(userId);
    await auth.authorized(
      'api/mobile/v1/push.php',
      body: {'action': 'subscribe', 'token': token},
    );
    _requireUser(userId);
    _token = token;
    await auth.storage.write('$preferenceKey.token', token);
    if (old != null && old != token) {
      await auth.authorized(
        'api/mobile/v1/push.php',
        body: {'action': 'unsubscribe', 'token': old},
      );
    }
  }

  void _requireUser(String? id) {
    if (_disposed || id == null || auth.user?['id']?.toString() != id) {
      throw StateError('Account cambiato. Riprova dal tuo profilo.');
    }
  }

  Future<void> _restore() async {
    final userId = auth.user?['id']?.toString();
    _userId = userId;
    if (userId == null) return;
    final preferenceKey = _preferenceKey;
    try {
      final optedIn = await auth.storage.read(preferenceKey) == '1';
      final savedToken = await auth.storage.read('$preferenceKey.token');
      final allowed = optedIn && await backend.allowed();
      _requireUser(userId);
      _token ??= savedToken;
      enabled = allowed;
      if (enabled) {
        final token = await backend.token();
        _requireUser(userId);
        if (token != null) await _register(token);
      } else if (optedIn) {
        if (_token != null) {
          await auth.authorized(
            'api/mobile/v1/push.php',
            body: {'action': 'unsubscribe', 'token': _token},
          );
        }
        _requireUser(userId);
        await backend.deleteToken();
        await auth.storage.delete('$preferenceKey.token');
        await auth.storage.delete(preferenceKey);
        _requireUser(userId);
        _token = null;
      }
    } catch (_) {
      if (userId == auth.user?['id']?.toString()) {
        message = 'Impossibile aggiornare le notifiche. Riprova.';
      }
    }
    _notify();
  }

  Future<void> setEnabled(bool value) => _perform(() async {
    final userId = auth.user?['id']?.toString();
    final preferenceKey = _preferenceKey;
    if (value) {
      // Check server availability before asking the Android permission.
      final status = await auth.authorized('api/mobile/v1/push.php');
      if (status['configured'] != true) {
        throw StateError(
          'Le notifiche sono in preparazione. Riprova più tardi.',
        );
      }
      if (!await backend.requestPermission()) {
        throw StateError(
          'Consenti le notifiche nelle impostazioni del telefono per Tornei Old School.',
        );
      }
      _requireUser(userId);
      final token = await backend.token();
      _requireUser(userId);
      if (token == null) {
        throw StateError('Dispositivo non disponibile. Riprova.');
      }
      await _register(token);
      _requireUser(userId);
      await auth.storage.write(preferenceKey, '1');
      _requireUser(userId);
      enabled = true;
      message = 'Notifiche attivate su questo telefono.';
    } else {
      final token = _token ?? await backend.token();
      if (token != null) {
        await auth.authorized(
          'api/mobile/v1/push.php',
          body: {'action': 'unsubscribe', 'token': token},
        );
      }
      await backend.deleteToken();
      await auth.storage.delete(preferenceKey);
      await auth.storage.delete('$preferenceKey.token');
      _requireUser(userId);
      _token = null;
      enabled = false;
      message = 'Notifiche disattivate su questo telefono.';
    }
  });

  Future<void> sendTest() => _perform(() async {
    if (!enabled || _token == null) return;
    await auth.authorized(
      'api/mobile/v1/push.php',
      body: {'action': 'test', 'token': _token},
    );
    message = 'Test inviato. Con l’app aperta compare un avviso; con l’app in background una notifica sul telefono.';
  });

  Future<void> _perform(Future<void> Function() action) async {
    if (busy || !ready || auth.user == null) return;
    busy = true;
    message = null;
    _notify();
    try {
      await _serialize(action);
    } catch (error) {
      message = error is StateError
          ? error.message.toString()
          : error.toString();
    } finally {
      busy = false;
      _notify();
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _startupTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    auth.removeListener(_authChanged);
    _tokenSubscription?.cancel();
    super.dispose();
  }
}

class PushScope extends InheritedNotifier<PushController> {
  const PushScope({
    super.key,
    required PushController controller,
    required super.child,
  }) : super(notifier: controller);
}

class NativePushSettings extends StatelessWidget {
  const NativePushSettings({super.key});
  @override
  Widget build(BuildContext context) {
    final push = context
        .dependOnInheritedWidgetOfExactType<PushScope>()
        ?.notifier;
    if (push == null || !push.supported) {
      return const Text(
        'Le notifiche sul telefono sono disponibili nell’app Android e iPhone.',
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Ricevi aggiornamenti sui risultati e sulle risposte ai tuoi commenti, anche con l’app chiusa.',
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: !push.ready || push.busy
              ? null
              : () => push.setEnabled(!push.enabled),
          icon: Icon(
            push.enabled
                ? Icons.notifications_off_outlined
                : Icons.notifications_active_outlined,
          ),
          label: Text(
            push.busy
                ? 'Aggiornamento…'
                : push.enabled
                ? 'Disattiva notifiche'
                : 'Attiva notifiche',
          ),
        ),
        if (push.enabled)
          TextButton(
            onPressed: push.busy ? null : push.sendTest,
            child: const Text('Invia notifica di test'),
          ),
        if (push.message != null) ...[
          const SizedBox(height: 8),
          Text(push.message!),
        ],
      ],
    );
  }
}
