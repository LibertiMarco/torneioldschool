import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';

import 'api.dart';

abstract class SessionStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class SecureSessionStore implements SessionStore {
  const SecureSessionStore();
  static const _storage = FlutterSecureStorage();
  @override
  Future<String?> read(String key) => _storage.read(key: key);
  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);
  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

class TosAuth extends ChangeNotifier {
  TosAuth(this.api, {SessionStore? storage})
    : storage = storage ?? const SecureSessionStore();
  static const callback = 'tosoldschool://auth/callback';
  final TosApi api;
  final SessionStore storage;
  Map<String, dynamic>? user;
  String? _access;
  String? _refresh;
  Future<void>? _refreshing;
  bool busy = false;
  String? error;
  String get _storageKey => 'tos.session.${api.baseUrl}';
  bool get admin => (user?['permissions'] as Map?)?['admin'] == true;
  bool get graphics => (user?['permissions'] as Map?)?['graphics'] == true;
  static String randomValue() => base64UrlEncode(
    List<int>.generate(32, (_) => Random.secure().nextInt(256)),
  ).replaceAll('=', '');
  static String challenge(String verifier) =>
      base64UrlEncode(sha256.convert(ascii.encode(verifier)).bytes)
          .replaceAll('=', '');

  Future<void> _save(Map<String, dynamic> tokens) async {
    final access = tokens['access_token'] as String;
    final refresh = tokens['refresh_token'] as String;
    await storage.write(
      _storageKey,
      jsonEncode({'access': access, 'refresh': refresh}),
    );
    _access = access;
    _refresh = refresh;
    user = Map<String, dynamic>.from(tokens['user'] as Map);
    notifyListeners();
  }

  Future<void> restore() async {
    try {
      final saved = await storage.read(_storageKey);
      if (saved == null) return;
      final tokens = jsonDecode(saved) as Map;
      _access = tokens['access'] as String;
      _refresh = tokens['refresh'] as String;
      final response = await authorized('api/mobile/v1/me.php');
      user = Map<String, dynamic>.from(response['user'] as Map);
    } on ApiError catch (failure) {
      if (failure.status == 401) await _clear();
      error = failure.message;
    } catch (_) {
      await _clear();
      error = 'Accedi nuovamente al tuo account.';
    }
    notifyListeners();
  }

  Future<void> login() async {
    if (busy) return;
    busy = true;
    error = null;
    notifyListeners();
    try {
      final verifier = randomValue();
      final state = randomValue();
      final url = api.uri('api/mobile/v1/authorize.php', {
        'client_id': 'tos-mobile',
        'response_type': 'code',
        'redirect_uri': callback,
        'code_challenge_method': 'S256',
        'code_challenge': challenge(verifier),
        'state': state,
      });
      final returned = Uri.parse(
        await FlutterWebAuth2.authenticate(
          url: url.toString(),
          callbackUrlScheme: 'tosoldschool',
        ),
      );
      if (returned.scheme != 'tosoldschool' ||
          returned.host != 'auth' ||
          returned.path != '/callback' ||
          returned.queryParameters['state'] != state ||
          returned.queryParameters['code'] == null) {
        throw const ApiError('Accesso non valido. Riprova.');
      }
      final response = await api.request(
        'api/mobile/v1/token.php',
        body: {
          'client_id': 'tos-mobile',
          'grant_type': 'authorization_code',
          'redirect_uri': callback,
          'code': returned.queryParameters['code'],
          'code_verifier': verifier,
        },
      );
      await _save(Map<String, dynamic>.from(response as Map));
    } on ApiError catch (failure) {
      error = failure.message;
    } catch (_) {
      error = 'Accesso interrotto. Puoi riprovare.';
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> _rotate() async {
    if (_refresh == null) {
      throw const ApiError('Accesso richiesto.', status: 401);
    }
    try {
      final response = await api.request(
        'api/mobile/v1/token.php',
        body: {
          'client_id': 'tos-mobile',
          'grant_type': 'refresh_token',
          'refresh_token': _refresh,
        },
      );
      await _save(Map<String, dynamic>.from(response as Map));
    } on ApiError catch (failure) {
      if (failure.status == 401) await _clear();
      rethrow;
    }
  }

  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) async {
    if (_access == null) {
      throw const ApiError('Accesso richiesto.', status: 401);
    }
    final attemptedAccess = _access;
    try {
      return await api.request(
        path,
        body: body,
        query: query,
        accessToken: attemptedAccess,
        uploads: uploads,
      );
    } on ApiError catch (failure) {
      if (failure.status != 401) rethrow;
      if (_access == attemptedAccess) {
        final pending = _refreshing ??= _rotate();
        try {
          await pending;
        } finally {
          if (identical(_refreshing, pending)) _refreshing = null;
        }
      }
      return api.request(
        path,
        body: body,
        query: query,
        accessToken: _access,
        uploads: uploads,
      );
    }
  }

  void updateProfile(Map<String, dynamic> profile) {
    if (user == null) return;
    user = {...user!, ...profile};
    notifyListeners();
  }

  Future<void> _clear() async {
    _access = null;
    _refresh = null;
    user = null;
    await storage.delete(_storageKey);
    notifyListeners();
  }

  Future<void> logout() async {
    if (busy) return;
    busy = true;
    error = null;
    notifyListeners();
    try {
      await authorized('api/mobile/v1/logout.php', body: {});
      await _clear();
    } on ApiError catch (failure) {
      error = failure.message;
    } finally {
      busy = false;
      notifyListeners();
    }
  }
}
