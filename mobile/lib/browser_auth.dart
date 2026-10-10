import 'api.dart';
import 'auth.dart';

class BrowserApi extends TosApi {
  BrowserApi({required super.baseUrl, super.client});
  @override
  Future<List<Map<String, dynamic>>> tournaments(String section) async {
    final data = await request(
      'app-preview/api.php',
      query: {'action': 'tournaments', 'section': section},
    );
    return TosApi.rows(data['tournaments']);
  }
}

class BrowserAuth extends TosAuth {
  BrowserAuth(super.api, {required this.navigate});
  final void Function(String) navigate;
  String? _csrf;
  @override
  Future<void> restore() async {
    try {
      final data = await api.request(
        'app-preview/api.php',
        query: {'action': 'session'},
      );
      user = Map<String, dynamic>.from(data['user'] as Map);
      _csrf = data['csrf'] as String;
      error = null;
    } on ApiError catch (failure) {
      user = null;
      _csrf = null;
      error = failure.status == 401 ? null : failure.message;
    } catch (_) {
      error = 'Impossibile caricare la sessione. Ricarica la pagina.';
    }
    notifyListeners();
  }

  @override
  Future<void> login() async => navigate(api.uri('app-preview/').toString());
  @override
  Future<void> logout() async {
    user = null;
    _csrf = null;
    notifyListeners();
    navigate(api.uri('logout.php').toString());
  }

  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    final action = switch (path) {
      'api/mobile/v1/admin/users.php' => 'users',
      'api/mobile/v1/admin/user_features.php' => 'features',
      _ => throw const ApiError(
        'Funzione non disponibile in questa anteprima.',
      ),
    };
    if (body != null && _csrf == null) {
      throw const ApiError('Ricarica la pagina prima di salvare.', status: 401);
    }
    try {
      return await api.request(
        'app-preview/api.php',
        query: {...?query, 'action': action},
        body: body == null ? null : {...body, '_csrf': _csrf},
      );
    } on ApiError catch (failure) {
      if (failure.status == 401 || failure.status == 403) {
        user = null;
        _csrf = null;
        error = failure.message;
        notifyListeners();
      }
      rethrow;
    }
  }
}
