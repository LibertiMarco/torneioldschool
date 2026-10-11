import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'details.dart' show DataPage, TeamPage;
import 'home_dashboard.dart' show SiteImage, openPage;
import 'player_event_marks.dart';
import 'player_history.dart';
import 'theme.dart';

class PersonalPlayerPage extends StatelessWidget {
  const PersonalPlayerPage({
    super.key,
    required this.auth,
    required this.section,
  });
  final TosAuth auth;
  final String section;

  Future<Map<String, dynamic>> load({String? type, int page = 1}) async =>
      Map<String, dynamic>.from(
        await auth.authorized(
          'api/mobile/v1/player_stats.php',
          query: {
            'section': section,
            if (type != null) ...{'type': type, 'page': '$page'},
          },
        ) as Map,
      );

  Widget statistics(Map<String, dynamic> stats) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      PlayerEventMarks(
        stats: {
          'goal': stats['reti'],
          'cartellino_giallo': stats['gialli'],
          'cartellino_rosso': stats['rossi'],
        },
      ),
      if ((num.tryParse('${stats['presenze']}') ?? 0) > 0)
        Padding(
          padding: const EdgeInsets.only(top: 12),
          child: Text('Presenze: ${stats['presenze']}'),
        ),
      if ((num.tryParse('${stats['media_voti']}') ?? 0) > 0)
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text('Media voto: ${stats['media_voti']}'),
        ),
    ],
  );

  @override
  Widget build(BuildContext context) => DataPage(
    title: 'Le mie statistiche',
    load: load,
    render: (value) {
      final data = Map<String, dynamic>.from(value as Map);
      if (data['player'] == null) {
        return const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'Non hai ancora un profilo giocatore collegato. Contatta un amministratore.',
            ),
          ),
        );
      }
      final player = Map<String, dynamic>.from(data['player'] as Map);
      void history(String type) => openPage(
        context,
        PlayerHistoryPage(
          api: auth.api,
          player: player,
          initialType: type,
          loadMatches: (type, page) async {
            final result = await load(type: type, page: page);
            if (result['player'] == null) {
              throw const ApiError('Il profilo giocatore non è più collegato.');
            }
            return result;
          },
        ),
      );
      final teams = TosApi.rows(data['teams']);
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      SiteImage(
                        api: auth.api,
                        path: '${player['foto'] ?? ''}',
                        width: 64,
                        height: 64,
                        fallback: Icons.person_outline,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${player['nome'] ?? ''} ${player['cognome'] ?? ''}',
                              style: const TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.bold,
                                color: siteBlue,
                              ),
                            ),
                            if ('${player['ruolo'] ?? ''}'.isNotEmpty)
                              Text('${player['ruolo']}'),
                          ],
                        ),
                      ),
                    ],
                  ),
                  statistics(player),
                  const SizedBox(height: 20),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      FilledButton.icon(
                        onPressed: () => history('gol'),
                        icon: const Icon(Icons.sports_soccer_rounded),
                        label: const Text('Partite con gol'),
                      ),
                      OutlinedButton.icon(
                        onPressed: () => history('presenze'),
                        icon: const Icon(Icons.assignment_outlined),
                        label: const Text('Partite giocate'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Text(
              'Le mie squadre',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.bold,
                color: siteBlue,
              ),
            ),
          ),
          if (teams.isEmpty)
            const Text('Non risultano squadre associate in questa sezione.'),
          for (final team in teams)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        SiteImage(
                          api: auth.api,
                          path: '${team['logo'] ?? ''}',
                          width: 44,
                          height: 44,
                          preserveShape: true,
                          fallback: Icons.shield_outlined,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${team['nome'] ?? ''}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 18,
                                ),
                              ),
                              Text(
                                '${team['torneo_nome'] ?? team['torneo'] ?? ''}',
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    statistics(team),
                    if (team['diffidato'] == true)
                      const Padding(
                        padding: EdgeInsets.only(top: 12),
                        child: Chip(label: Text('Diffidato')),
                      ),
                    const SizedBox(height: 12),
                    TextButton.icon(
                      onPressed: () => openPage(
                        context,
                        TeamPage(
                          api: auth.api,
                          slug: '${team['torneo']}',
                          team: team,
                        ),
                      ),
                      icon: const Icon(Icons.groups_outlined),
                      label: const Text('Vedi squadra'),
                    ),
                  ],
                ),
              ),
            ),
        ],
      );
    },
  );
}
