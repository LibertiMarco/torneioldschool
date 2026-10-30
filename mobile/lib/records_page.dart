import 'package:flutter/material.dart';

import 'api.dart';

class GoalkeeperSummary extends StatefulWidget {
  const GoalkeeperSummary({
    super.key,
    required this.api,
    required this.playerId,
  });
  final TosApi api;
  final String playerId;
  @override
  State<GoalkeeperSummary> createState() => _GoalkeeperSummaryState();
}

class _GoalkeeperSummaryState extends State<GoalkeeperSummary> {
  late Future<dynamic> data = load();
  Future<dynamic> load() => widget.api.request(
    'api/records.php',
    query: {'player_id': widget.playerId},
  );
  @override
  void didUpdateWidget(covariant GoalkeeperSummary oldWidget) {
    super.didUpdateWidget(oldWidget);
    data = load();
  }

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Statistiche portiere',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          FutureBuilder<dynamic>(
            future: data,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _metrics(null),
                    const Text(
                      'Caricamento non riuscito. I dati non sono stati sostituiti con degli zeri.',
                    ),
                    TextButton(
                      onPressed: () => setState(() {
                        data = load();
                      }),
                      child: const Text('Riprova'),
                    ),
                  ],
                );
              }
              if (!snapshot.hasData) {
                return const LinearProgressIndicator();
              }
              final groups = Map<String, dynamic>.from(
                snapshot.data['groups'] as Map,
              );
              if (groups.isEmpty) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _metrics(null),
                    const Text(
                      'Nessun tabellino con dati portieri completi e verificati. Le statistiche saranno disponibili dopo la verifica dello staff.',
                    ),
                  ],
                );
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final entry in groups.entries)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            entry.key,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          _metrics(
                            entry.value['verified_keeper_matches'] == 0
                                ? null
                                : Map<String, dynamic>.from(
                                    entry.value['goalkeeper'] as Map,
                                  ),
                          ),
                        ],
                      ),
                    ),
                  const Text(
                    'Solo partite con dati portieri completi verificati. Porta inviolata solo per una partita intera in porta.',
                  ),
                ],
              );
            },
          ),
          TextButton(
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => GoalkeeperStatsPage(
                    api: widget.api,
                    playerId: widget.playerId,
                  ),
                ),
              );
              if (mounted) {
                setState(() {
                  data = load();
                });
              }
            },
            child: const Text('Storico, tornei e formati'),
          ),
        ],
      ),
    ),
  );
  Widget _metrics(Map<String, dynamic>? stats) => Column(
    children: [
      for (final entry in {
        'Presenze in porta': stats?['appearances'],
        'Gol subiti': stats?['goals_conceded'],
        'Media gol subiti': stats?['average'],
        'Porte inviolate': stats?['clean_sheets'],
        'Percentuale porte inviolate': stats?['clean_sheet_percentage'] == null
            ? null
            : '${stats!['clean_sheet_percentage']}%',
      }.entries)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: Text(entry.key)),
              const SizedBox(width: 12),
              Text(
                '${entry.value ?? '—'}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
    ],
  );
}

class GoalkeeperStatsPage extends StatefulWidget {
  const GoalkeeperStatsPage({
    super.key,
    required this.api,
    required this.playerId,
  });
  final TosApi api;
  final String playerId;
  @override
  State<GoalkeeperStatsPage> createState() => _GoalkeeperStatsPageState();
}

class _GoalkeeperStatsPageState extends State<GoalkeeperStatsPage> {
  String tournament = '', format = '';
  late Future<dynamic> data = load();
  Future<dynamic> load() => widget.api.request(
    'api/records.php',
    query: {
      'torneo': tournament,
      'formato': format,
      'player_id': widget.playerId,
    },
  );
  void reload() => setState(() {
    data = load();
  });
  Widget filter(
    String label,
    List<dynamic> values,
    String current,
    ValueChanged<String> changed,
  ) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: DropdownButtonFormField<String>(
      initialValue: current,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        DropdownMenuItem(
          value: '',
          child: Text(label == 'Torneo' ? 'Storico TOS' : 'Tutti (separati)'),
        ),
        ...values.map(
          (v) => DropdownMenuItem(
            value: '$v',
            child: Text('$v', overflow: TextOverflow.ellipsis),
          ),
        ),
      ],
      onChanged: (value) {
        changed(value ?? '');
        reload();
      },
    ),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Statistiche portiere')),
    body: FutureBuilder<dynamic>(
      future: data,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Statistiche non disponibili.'),
                TextButton(onPressed: reload, child: const Text('Riprova')),
              ],
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final d = Map<String, dynamic>.from(snapshot.data as Map);
        final groups = Map<String, dynamic>.from(d['groups'] as Map);
        return RefreshIndicator(
          onRefresh: () async {
            reload();
            await data;
          },
          child: ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              Text('${d['coverage']}'),
              filter(
                'Torneo',
                d['tournaments'] as List,
                tournament,
                (v) => tournament = v,
              ),
              filter(
                'Formato',
                d['formats'] as List,
                format,
                (v) => format = v,
              ),
              if (groups.isEmpty)
                const Text(
                  'Nessun dato verificato disponibile per questi filtri.',
                ),
              for (final entry in groups.entries) ...[
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(
                    entry.key,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                _keeper(entry.value as Map),
              ],
            ],
          ),
        );
      },
    ),
  );
  Widget _keeper(Map group) {
    final k = group['goalkeeper'] as Map;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Presenze in porta: ${k['appearances']}'),
            Text('Gol subiti: ${k['goals_conceded']}'),
            Text('Media: ${k['average'] ?? '—'}'),
            Text('Porte inviolate: ${k['clean_sheets']}'),
            Text(
              'Percentuale: ${k['clean_sheet_percentage'] == null ? '—' : '${k['clean_sheet_percentage']}%'}',
            ),
            Text(
              'Copertura: ${group['verified_keeper_matches']} / ${group['eligible_matches']} partite con dati portieri completi. Zero presenze: nessuna presenza verificabile. Porta inviolata solo per una partita intera in porta.',
            ),
          ],
        ),
      ),
    );
  }
}
