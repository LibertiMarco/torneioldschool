import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiError implements Exception {
  const ApiError(this.message, {this.status = 0});
  final String message;
  final int status;
  @override
  String toString() => message;
}

class TosApi {
  TosApi({required this.baseUrl, http.Client? client})
    : client = client ?? http.Client() {
    if (baseUrl.scheme != 'https' &&
        !(kDebugMode && baseUrl.scheme == 'http')) {
      throw const ApiError('È richiesta una connessione HTTPS.');
    }
    if (baseUrl.host.isEmpty ||
        baseUrl.hasQuery ||
        baseUrl.hasFragment ||
        baseUrl.userInfo.isNotEmpty) {
      throw const ApiError('Indirizzo del servizio non valido.');
    }
  }
  final Uri baseUrl;
  final http.Client client;
  bool get preview => false;
  Uri uri(String path, [Map<String, String>? query]) => baseUrl.replace(
    path: '${baseUrl.path.replaceFirst(RegExp(r'/$'), '')}/$path',
    queryParameters: query,
  );

  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
  }) async {
    final headers = <String, String>{'Accept': 'application/json'};
    if (accessToken != null) headers['Authorization'] = 'Bearer $accessToken';
    try {
      final http.Response response;
      if (body == null) {
        response = await client
            .get(uri(path, query), headers: headers)
            .timeout(const Duration(seconds: 20));
      } else {
        headers['Content-Type'] = 'application/json';
        response = await client
            .post(uri(path, query), headers: headers, body: jsonEncode(body))
            .timeout(const Duration(seconds: 20));
      }
      dynamic data;
      try {
        data = jsonDecode(utf8.decode(response.bodyBytes));
      } on FormatException {
        throw ApiError(
          'Servizio temporaneamente non disponibile.',
          status: response.statusCode,
        );
      }
      if (response.statusCode >= 400 ||
          (data is Map && data['error'] != null)) {
        final error = data is Map ? data['error'] : null;
        final message =
            error is Map &&
                (path.startsWith('api/mobile/v1/') ||
                    path == 'app-preview/api.php')
            ? error['message']?.toString() ?? 'Richiesta non riuscita.'
            : 'Servizio temporaneamente non disponibile.';
        throw ApiError(message, status: response.statusCode);
      }
      return data;
    } on ApiError {
      rethrow;
    } catch (_) {
      throw const ApiError('Connessione non disponibile. Riprova.');
    }
  }

  Future<List<Map<String, dynamic>>> tournaments(String section) async {
    final data = await request(
      'api/mobile/v1/tournaments.php',
      query: {'section': section},
    );
    return rows(data['tournaments']);
  }

  Future<List<Map<String, dynamic>>> standings(String slug) async =>
      rows(await request('api/leggiClassifica.php', query: {'torneo': slug}));
  Future<Map<String, dynamic>> tournamentInfo(String slug) async =>
      Map<String, dynamic>.from(
        await request('api/get_torneo_by_slug.php', query: {'slug': slug})
            as Map,
      );
  Future<List<Map<String, dynamic>>> tournamentTeams(String slug) async => rows(
    await request('api/get_squadre_torneo.php', query: {'torneo': slug}),
  );
  Future<List<Map<String, dynamic>>> scorers(String slug) async => rows(
    await request('api/classifica_marcatori.php', query: {'torneo': slug}),
  );
  Future<Map<String, dynamic>> playerRanking({
    String order = 'gol',
    int page = 1,
    int perPage = 5,
    String search = '',
  }) async => Map<String, dynamic>.from(
    await request(
      'api/classifica_giocatori.php',
      query: {
        'ordine': order,
        'page': '$page',
        'per_page': '$perPage',
        'search': search,
      },
    ) as Map,
  );
  Future<Map<String, dynamic>> esportRanking({int limit = 5}) async =>
      Map<String, dynamic>.from(
        await request('api/esport_ranking.php', query: {'limit': '$limit'})
            as Map,
      );
  Future<List<Map<String, dynamic>>> news(
    String section, {
    bool all = false,
  }) async => rows(
    await request(
      'api/blog.php',
      query: {'azione': all ? 'lista' : 'ultimi', 'sezione': section},
    ),
  );
  Future<Map<String, dynamic>> article(String id) async =>
      Map<String, dynamic>.from(
        await request('api/blog.php', query: {'azione': 'articolo', 'id': id})
            as Map,
      );
  Future<List<Map<String, dynamic>>> hallOfFame(String section) async => rows(
    (await request('api/albo_doro.php', query: {'sezione': section}))['data'],
  );
  Future<List<Map<String, dynamic>>> matches(String slug) async =>
      flattenMatches(
        await request('api/get_partite.php', query: {'torneo': slug}),
      );
  Future<List<Map<String, dynamic>>> roster(String slug, String team) async =>
      rows(
        await request(
          'api/get_rosa.php',
          query: {'torneo': slug, 'squadra': team},
        ),
      );
  Future<Map<String, dynamic>> match(String id) async =>
      Map<String, dynamic>.from(
        await request('api/get_partita.php', query: {'id': id}) as Map,
      );
  Future<List<Map<String, dynamic>>> events(String id) async =>
      rows(await request('api/get_eventi_partita.php', query: {'partita': id}));
  static List<Map<String, dynamic>> rows(dynamic data) => data is List
      ? data
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList()
      : [];
  static List<Map<String, dynamic>> flattenMatches(dynamic data) {
    if (data is List) {
      if (data.any((item) => item is List)) return data.expand(rows).toList();
      return rows(data);
    }
    if (data is Map) return data.values.expand(rows).toList();
    return [];
  }

  static String tournamentSlug(Map<String, dynamic> tournament) =>
      (tournament['filetorneo'] ?? '')
          .toString()
          .split('/')
          .last
          .replaceFirst(RegExp(r'\.(php|html?)$', caseSensitive: false), '');
}
