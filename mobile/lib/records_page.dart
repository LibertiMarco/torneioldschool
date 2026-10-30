import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;

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
                return const Text('Dati portieri non disponibili.');
              }
              if (!snapshot.hasData) {
                return const LinearProgressIndicator();
              }
              final groups = Map<String, dynamic>.from(
                snapshot.data['groups'] as Map,
              );
              if (groups.isEmpty) {
                return const Text('Nessuna presenza in porta verificabile.');
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final entry in groups.entries)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Text(
                        '${entry.key}\nPresenze: ${entry.value['goalkeeper']['appearances']} · Gol subiti: ${entry.value['goalkeeper']['goals_conceded']}\nMedia: ${entry.value['goalkeeper']['average'] ?? '—'} · Porte inviolate: ${entry.value['goalkeeper']['clean_sheets']} · ${entry.value['goalkeeper']['clean_sheet_percentage'] == null ? '—' : '${entry.value['goalkeeper']['clean_sheet_percentage']}%'}',
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
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) =>
                    RecordsPage(api: widget.api, playerId: widget.playerId),
              ),
            ),
            child: const Text('Storico, tornei e formati'),
          ),
        ],
      ),
    ),
  );
}

class RecordsPage extends StatefulWidget {
  const RecordsPage({super.key, required this.api, this.playerId});
  final TosApi api;
  final String? playerId;
  @override
  State<RecordsPage> createState() => _RecordsPageState();
}

class _RecordsPageState extends State<RecordsPage> {
  String tournament = '', format = '';
  late Future<dynamic> data = load();
  Future<dynamic> load() => widget.api.request(
    'api/records.php',
    query: {
      'torneo': tournament,
      'formato': format,
      if (widget.playerId != null) 'player_id': widget.playerId!,
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
    appBar: AppBar(
      title: Text(widget.playerId == null ? 'Record' : 'Statistiche portiere'),
    ),
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
                if (widget.playerId != null) _keeper(entry.value as Map),
                if (widget.playerId == null)
                  for (final record in (entry.value['records'] as Map).values)
                    Card(
                      child: ExpansionTile(
                        title: Text('${record['label']}'),
                        subtitle: Text(
                          record['value'] == null
                              ? 'Dati insufficienti'
                              : '${record['value']} · ${(record['holders'] as List).length} detentori',
                        ),
                        children: [
                          for (final holder in record['holders'] as List)
                            ListTile(
                              title: Text('${holder['subject']}'),
                              subtitle: Text(
                                '${holder['match']['squadra_casa']} ${holder['match']['gol_casa']}–${holder['match']['gol_ospite']} ${holder['match']['squadra_ospite']}\n${holder['match']['torneo_nome'] ?? holder['match']['torneo']} · ${holder['match']['data_partita']}',
                              ),
                              isThreeLine: true,
                              trailing: holderLinks(holder),
                              onTap: () => Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => MatchPage(
                                    api: widget.api,
                                    id: '${holder['match']['id']}',
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
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

  Widget holderLinks(Map holder) => PopupMenuButton<String>(
    tooltip: 'Profili',
    itemBuilder: (_) => [
      if (holder['player_id'] != null)
        const PopupMenuItem(value: 'player', child: Text('Profilo giocatore')),
      for (final side in ['squadra_casa', 'squadra_ospite'])
        PopupMenuItem(value: side, child: Text('${holder['match'][side]}')),
    ],
    onSelected: (value) => Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => value == 'player'
            ? PlayerPage(
                api: widget.api,
                player: {
                  'id': holder['player_id'],
                  'nome': holder['subject'],
                  'cognome': '',
                },
              )
            : TeamPage(
                api: widget.api,
                slug: '${holder['match']['torneo']}',
                team: {'nome': holder['match'][value]},
              ),
      ),
    ),
  );
}
