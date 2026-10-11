import 'package:flutter/material.dart';

import 'api.dart';
import 'player_event_marks.dart';
import 'ranking_mode_selector.dart';
import 'details.dart' show MatchPage;
import 'home_dashboard.dart' show SiteImage, openPage;
import 'theme.dart' show siteBlue;

class PlayerHistoryPage extends StatefulWidget {
  const PlayerHistoryPage({
    super.key,
    required this.api,
    required this.player,
    this.initialType = 'gol',
  });
  final TosApi api;
  final Map<String, dynamic> player;
  final String initialType;
  @override
  State<PlayerHistoryPage> createState() => _PlayerHistoryPageState();
}

class _PlayerHistoryPageState extends State<PlayerHistoryPage> {
  late String type;
  final matches = <Map<String, dynamic>>[];
  Map<String, dynamic> player = {};
  int page = 0, generation = 0;
  bool loading = false, hasMore = true;
  Object? error;
  @override
  void initState() {
    super.initState();
    type = widget.initialType;
    player = widget.player;
    load();
  }

  Future<void> load({bool reset = false}) async {
    if (reset) {
      generation++;
      matches.clear();
      page = 0;
      hasMore = true;
    } else if (loading) {
      return;
    }
    final requestGeneration = generation;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.api.playerMatches(
        '${widget.player['id']}',
        type: type,
        page: page + 1,
      );
      if (!mounted || requestGeneration != generation) return;
      setState(() {
        player = Map<String, dynamic>.from(result['player'] as Map);
        matches.addAll(TosApi.rows(result['matches']));
        page++;
        hasMore = (result['pagination'] as Map?)?['has_more'] == true;
        loading = false;
      });
    } catch (failure) {
      if (!mounted || requestGeneration != generation) return;
      setState(() {
        error = failure;
        loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final totals = player['totali'] as Map? ?? widget.player;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          '${player['nome'] ?? ''} ${player['cognome'] ?? ''}'.trim(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  SiteImage(
                    api: widget.api,
                    path: '${player['foto'] ?? ''}',
                    width: 72,
                    height: 72,
                    fallback: Icons.person_outline,
                  ),
                  const SizedBox(height: 12),
                  if ('${player['ruolo'] ?? ''}'.isNotEmpty)
                    Text('${player['ruolo']}'),
                  Text(
                    '${totals['gol'] ?? 0} gol · ${totals['presenze'] ?? 0} presenze',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          RankingModeSelector(
            value: type,
            onChanged: (value) {
              type = value;
              load(reset: true);
            },
          ),
          const SizedBox(height: 16),
          Text(
            type == 'gol'
                ? 'Partite in cui ha segnato'
                : 'Partite in cui ha giocato',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 12),
          if (!loading && error == null && matches.isEmpty)
            const Text('Nessuna partita trovata per questo filtro.'),
          ...matches.map(
            (match) => PlayerHistoryMatchCard(
              api: widget.api,
              match: match,
              type: type,
            ),
          ),
          if (error != null) ...[
            Text(
              error is ApiError
                  ? '$error'
                  : 'Servizio temporaneamente non disponibile.',
            ),
            TextButton(onPressed: () => load(), child: const Text('Riprova')),
          ] else if (loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (hasMore)
            TextButton(
              onPressed: () => load(),
              child: const Text('Carica altre partite'),
            ),
        ],
      ),
    );
  }
}

class PlayerHistoryMatchCard extends StatelessWidget {
  const PlayerHistoryMatchCard({
    super.key,
    required this.api,
    required this.match,
    required this.type,
  });
  final TosApi api;
  final Map<String, dynamic> match;
  final String type;

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse('${match['data_partita']}');
    final dateText = date == null
        ? ''
        : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
    final round = '${match['fase_round'] ?? ''}'.trim();
    final stage = round.isNotEmpty
        ? round
        : [
            if ('${match['fase'] ?? ''}'.isNotEmpty) '${match['fase']}',
            if (match['giornata'] != null) 'Giornata ${match['giornata']}',
          ].join(' · ');
    Widget team(String side) => Expanded(
      child: Column(
        children: [
          SiteImage(
            api: api,
            path: '${match['logo_$side'] ?? ''}',
            width: 36,
            height: 36,
            preserveShape: true,
            fallback: Icons.shield_outlined,
          ),
          const SizedBox(height: 8),
          Text('${match['squadra_$side'] ?? ''}', textAlign: TextAlign.center),
        ],
      ),
    );
    return Card(
      child: InkWell(
        onTap: () => openPage(
          context,
          MatchPage(
            api: api,
            id: '${match['partita_id']}',
            previewMatch: {...match, 'id': match['partita_id']},
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${match['torneo_nome'] ?? match['torneo'] ?? ''}',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: siteBlue,
                ),
              ),
              Text(
                [
                  stage,
                  dateText,
                  '${match['ora_partita'] ?? ''}'.trim(),
                ].where((s) => s.isNotEmpty).join(' · '),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  team('casa'),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: Text(
                      '${match['gol_casa'] ?? '–'} - ${match['gol_ospite'] ?? '–'}',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  team('ospite'),
                ],
              ),
              PlayerEventMarks(stats: match),
              if (type == 'presenze' && match['voto'] != null)
                Text('Voto: ${match['voto']}'),
            ],
          ),
        ),
      ),
    );
  }
}
