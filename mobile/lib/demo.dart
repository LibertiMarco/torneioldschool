import 'api.dart';
import 'auth.dart';

// Preview data never uses the live service or credentials.
class DemoApi extends TosApi {
  DemoApi() : super(baseUrl: Uri.parse('https://preview.invalid'));
  @override
  bool get preview => true;
  final users = <Map<String, dynamic>>[
    {
      'id': 1,
      'nome': 'Marco',
      'cognome': 'Demo',
      'email': 'admin@example.test',
      'ruolo': 'admin',
      'feature_flags': {'totocalcio': true, 'fantacalcio': true},
      'permissions': {'admin': true, 'graphics': true},
    },
    {
      'id': 2,
      'nome': 'Luca',
      'cognome': 'Demo',
      'email': 'giocatore@example.test',
      'ruolo': 'user',
      'feature_flags': {'totocalcio': false, 'fantacalcio': true},
      'permissions': {'admin': false, 'graphics': false},
    },
  ];
  int revision = 1;
  List<Map<String, dynamic>> get teams => [
    {
      'id': 1,
      'nome': 'Old School FC',
      'punti': 9,
      'giocate': 3,
      'vinte': 3,
      'pareggiate': 0,
      'perse': 0,
      'gol_fatti': 8,
      'gol_subiti': 2,
      'differenza_reti': 6,
      'girone': 'A',
    },
    {
      'id': 2,
      'nome': 'Real Academy',
      'punti': 6,
      'giocate': 3,
      'vinte': 2,
      'pareggiate': 0,
      'perse': 1,
      'gol_fatti': 5,
      'gol_subiti': 3,
      'differenza_reti': 2,
      'girone': 'A',
    },
    {
      'id': 3,
      'nome': 'Sporting Club',
      'punti': 3,
      'giocate': 3,
      'vinte': 1,
      'pareggiate': 0,
      'perse': 2,
      'gol_fatti': 4,
      'gol_subiti': 6,
      'differenza_reti': -2,
      'girone': 'A',
    },
  ];
  List<Map<String, dynamic>> get games => [
    {
      'id': 1,
      'squadra_casa': 'Old School FC',
      'squadra_ospite': 'Real Academy',
      'gol_casa': 3,
      'gol_ospite': 1,
      'giocata': 1,
      'data_partita': '2026-10-09',
      'ora_partita': '20:30',
      'campo': 'Campo Old School',
      'giornata': 3,
      'fase': 'Girone A',
      'arbitro': 'Arbitro Demo',
    },
    {
      'id': 2,
      'squadra_casa': 'Sporting Club',
      'squadra_ospite': 'Old School FC',
      'giocata': 0,
      'data_partita': '2026-10-16',
      'ora_partita': '21:00',
      'campo': 'Campo Old School',
      'giornata': 4,
      'fase': 'Girone A',
    },
  ];
  List<Map<String, dynamic>> get players => [
    {
      'id': 10,
      'nome': 'Luca',
      'cognome': 'Rossi',
      'ruolo': 'Attaccante',
      'is_captain': 1,
      'presenze': 3,
      'reti': 4,
      'assist': 1,
      'gialli': 1,
      'rossi': 0,
      'media_voti': 7.5,
    },
    {
      'id': 11,
      'nome': 'Andrea',
      'cognome': 'Bianchi',
      'ruolo': 'Centrocampista',
      'is_captain': 0,
      'presenze': 3,
      'reti': 2,
      'assist': 3,
      'gialli': 0,
      'rossi': 0,
      'media_voti': 7.0,
    },
  ];
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 150));
    switch (path) {
      case 'api/mobile/v1/account.php':
        final user = users.first;
        if (body != null && body['nome'] != null) {
          user['nome'] = body['nome'];
          user['cognome'] = body['cognome'];
        }
        return {
          'profile': {
            'id': user['id'],
            'nome': user['nome'],
            'cognome': user['cognome'],
            'email': user['email'],
            'avatar': '',
          },
          'consents': {
            'newsletter': body?['consenso_newsletter'] == 1,
            'marketing': body?['consenso_marketing'] == 1,
            'tracking': body?['consenso_tracking'] == 1,
          },
          'player': {'id': 10, 'nome': 'Luca', 'cognome': 'Rossi', 'foto': ''},
          'message': body == null
              ? ''
              : 'Impostazioni aggiornate con successo.',
        };
      case 'api/mobile/v1/tournaments.php':
        final esport = query?['section'] == 'esport';
        return {
          'tournaments': [
            {
              'id': esport ? 2 : 1,
              'nome': esport ? 'Old School Esport Cup' : 'Old School Cup',
              'filetorneo': 'demo.php',
              'categoria': esport ? 'Esport' : 'Calcio a 7',
              'stato': 'in corso',
            },
          ],
        };
      case 'api/classifica_giocatori.php':
        return {
          'data': players
              .map(
                (p) => {
                  ...p,
                  'gol': p['reti'],
                  'posizione': p['id'] == 10 ? 1 : 2,
                },
              )
              .toList(),
          'pagination': {'page': 1, 'total_pages': 1},
        };
      case 'api/esport_ranking.php':
        return {
          'data': [
            {
              'nome': 'Luca',
              'cognome': 'Rossi',
              'posizione': 1,
              'punti': 40,
              'tornei_giocati': 2,
            },
          ],
          'meta': {'can_view_full': true, 'has_more': false},
        };
      case 'api/diffidati.php':
        return [
          {
            'giocatore_id': 10,
            'nome': 'Luca',
            'cognome': 'Rossi',
            'squadra': 'Old School FC',
            'giornate': [2, 3],
          },
        ];
      case 'api/albo_doro.php':
        return {
          'data': [
            {
              'competizione': 'Old School Cup',
              'anno': 2026,
              'filetorneo': 'demo.php',
              'premi': [
                {'premio': 'Vincitore', 'vincitrice': 'Old School FC'},
              ],
            },
          ],
        };
      case 'api/blog.php':
        if (query?['azione'] == 'articolo') {
          return {
            'titolo': 'La nuova stagione',
            'contenuto':
                '<p>Una nuova stagione <strong>Old School</strong>.</p>',
            'data': '10/10/2026',
          };
        }
        return [
          {'id': 1, 'titolo': 'La nuova stagione', 'data': '10/10/2026'},
        ];
      case 'api/get_torneo_by_slug.php':
        return {
          'nome': 'Old School Cup',
          'config': {
            'formato': 'campionato',
            'totale_squadre': 3,
            'qualificati_gold': 1,
            'qualificati_silver': 2,
            'regole_html':
                '<p>Tre punti per la vittoria, uno per il pareggio.</p>',
          },
        };
      case 'api/get_squadre_torneo.php':
        return teams;
      case 'api/classifica_marcatori.php':
        return players
            .map((p) => {...p, 'gol': p['reti'], 'squadra': 'Old School FC'})
            .toList();
      case 'api/leggiClassifica.php':
        return teams;
      case 'api/get_partite.php':
        return games;
      case 'api/get_rosa.php':
        return players;
      case 'api/get_partita.php':
        return games.firstWhere((row) => '${row['id']}' == query?['id']);
      case 'api/get_eventi_partita.php':
        if (query?['partita'] == '2') return [];
        return players
            .map(
              (row) => {
                ...row,
                'squadra': 'Old School FC',
                'goal': row['id'] == 10 ? 2 : 1,
                'assist': 1,
                'voto': row['media_voti'],
                'cartellino_giallo': row['gialli'],
                'cartellino_rosso': 0,
              },
            )
            .toList();
      case 'api/mobile/v1/admin/users.php':
        return {'users': users, 'has_more': false};
      case 'api/mobile/v1/admin/user_features.php':
        final id = '${body?['id'] ?? query?['id']}';
        final user = users.firstWhere((row) => '${row['id']}' == id);
        if (body != null) {
          if (body['revision'] != '$revision') {
            throw const ApiError(
              'Account modificato. Ricarica prima di salvare.',
              status: 409,
            );
          }
          user['feature_flags'] = Map<String, bool>.from(
            body['feature_flags'] as Map,
          );
          revision++;
        }
        return {
          'user': user,
          'revision': '$revision',
          'definitions': [
            {
              'key': 'totocalcio',
              'label': 'Totocalcio',
              'description': 'Mostra Totocalcio nel menu utente.',
            },
            {
              'key': 'fantacalcio',
              'label': 'Fantacalcio',
              'description': 'Mostra Fantacalcio nel menu utente.',
            },
          ],
        };
      default:
        throw const ApiError('Funzione non disponibile in questa anteprima.');
    }
  }
}

class DemoAuth extends TosAuth {
  DemoAuth(DemoApi super.api);
  @override
  Future<void> restore() async {}
  @override
  Future<void> login() async {
    user = (api as DemoApi).users.first;
    error = null;
    notifyListeners();
  }

  @override
  Future<void> logout() async {
    user = null;
    notifyListeners();
  }

  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) {
    if (!admin) throw const ApiError('Accesso richiesto.', status: 401);
    return api.request(path, body: body, query: query, uploads: uploads);
  }
}
