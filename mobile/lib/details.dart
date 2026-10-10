import 'package:flutter/material.dart';

import 'api.dart';
import 'home_dashboard.dart' show SiteImage;

class DataPage extends StatefulWidget {
  const DataPage({
    super.key,
    required this.title,
    required this.load,
    required this.render,
  });
  final String title;
  final Future<dynamic> Function() load;
  final Widget Function(dynamic) render;
  @override
  State<DataPage> createState() => _DataPageState();
}

class _DataPageState extends State<DataPage> {
  late Future<dynamic> data;
  @override
  void initState() {
    super.initState();
    data = widget.load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: FutureBuilder<dynamic>(
      future: data,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    snapshot.error is ApiError
                        ? snapshot.error.toString()
                        : 'Servizio temporaneamente non disponibile.',
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => setState(() => data = widget.load()),
                    child: const Text('Riprova'),
                  ),
                ],
              ),
            ),
          );
        }
        return widget.render(snapshot.data);
      },
    ),
  );
}

class TeamPage extends StatelessWidget {
  const TeamPage({
    super.key,
    required this.api,
    required this.slug,
    required this.team,
  });
  final TosApi api;
  final String slug;
  final Map<String, dynamic> team;
  @override
  Widget build(BuildContext context) => DataPage(
    title: '${team['nome']}',
    load: () => api.roster(slug, '${team['nome']}'),
    render: (data) {
      final players = TosApi.rows(data);
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${team['nome']}',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${team['punti'] ?? 0} punti · ${team['giocate'] ?? 0} partite giocate',
                  ),
                  Text(
                    '${team['vinte'] ?? 0} vinte · ${team['pareggiate'] ?? 0} pareggiate · ${team['perse'] ?? 0} perse',
                  ),
                  Text(
                    'Gol fatti ${team['gol_fatti'] ?? 0} · subiti ${team['gol_subiti'] ?? 0}',
                  ),
                ],
              ),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Text(
              'Rosa',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
          ),
          if (players.isEmpty) const Text('Rosa non ancora disponibile.'),
          ...players.map(
            (player) => Card(
              child: ListTile(
                leading: SiteImage(api: api, path: '${player['foto'] ?? ''}', width: 44, height: 44, fallback: Icons.person_outline),
                title: Text(
                  '${player['nome']} ${player['cognome']}${player['is_captain'].toString() == '1' ? ' · Capitano' : ''}',
                ),
                subtitle: Text(
                  '${player['ruolo_squadra'] ?? player['ruolo'] ?? ''}\n${player['presenze'] ?? 0} presenze · ${player['reti'] ?? 0} gol · ${player['assist'] ?? 0} assist',
                ),
                isThreeLine: true,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                      builder: (_) => PlayerPage(player: player, api: api),
                  ),
                ),
              ),
            ),
          ),
        ],
      );
    },
  );
}

class PlayerPage extends StatelessWidget {
  const PlayerPage({super.key, required this.player, this.api});
  final Map<String, dynamic> player;
  final TosApi? api;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Giocatore')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        if (api != null) Center(child: SiteImage(api: api!, path: '${player['foto'] ?? ''}', width: 80, height: 80, fallback: Icons.person_outline)) else const Icon(Icons.person_outline, size: 80),
        const SizedBox(height: 24),
        Text(
          '${player['nome']} ${player['cognome']}',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        Text('${player['ruolo_squadra'] ?? player['ruolo'] ?? ''}'),
        const SizedBox(height: 24),
        for (final entry in {
          'Presenze': 'presenze',
          'Gol': 'reti',
          'Assist': 'assist',
          'Gialli': 'gialli',
          'Rossi': 'rossi',
          'Media voti': 'media_voti',
        }.entries)
          ListTile(
            title: Text(entry.key),
            trailing: Text('${player[entry.value] ?? '—'}'),
          ),
      ],
    ),
  );
}

class MatchPage extends StatelessWidget {
  const MatchPage({super.key, required this.api, required this.id});
  final TosApi api;
  final String id;
  @override
  Widget build(BuildContext context) => DataPage(
    title: 'Dettaglio partita',
    load: () async {
      final results = await Future.wait<dynamic>([
        api.match(id),
        api.events(id),
      ]);
      return {'match': results[0], 'events': results[1]};
    },
    render: (data) {
      final match = data['match'] as Map;
      final events = TosApi.rows(data['events']);
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  Text(
                    '${match['squadra_casa']} – ${match['squadra_ospite']}',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    match['giocata'].toString() == '1'
                        ? '${match['gol_casa']} : ${match['gol_ospite']}'
                        : 'Da giocare',
                    style: Theme.of(context).textTheme.headlineLarge,
                  ),
                  if (match['decisa_rigori'].toString() == '1')
                    Text(
                      'Rigori ${match['rigori_casa']} : ${match['rigori_ospite']}',
                    ),
                  const SizedBox(height: 16),
                  Text(
                    '${match['data_partita'] ?? ''} · ${match['ora_partita'] ?? ''}',
                  ),
                  if ('${match['campo'] ?? ''}'.isNotEmpty)
                    Text('${match['campo']}'),
                  if ('${match['arbitro'] ?? ''}'.isNotEmpty)
                    Text('Arbitro: ${match['arbitro']}'),
                  if (match['giornata'] != null)
                    Text('Giornata ${match['giornata']}'),
                  if ('${match['fase'] ?? ''}'.isNotEmpty)
                    Text('${match['fase']}'),
                ],
              ),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Text(
              'Referto giocatori',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
          ),
          if (events.isEmpty) const Text('Referto non ancora disponibile.'),
          ...events.map(
            (event) => Card(
              child: ListTile(
                title: Text('${event['nome']} ${event['cognome']}'),
                subtitle: Text(
                  '${event['squadra'] ?? ''}\n${event['goal'] ?? 0} gol · ${event['assist'] ?? 0} assist · ${event['autogol'] ?? 0} autogol\nGialli ${event['cartellino_giallo'] ?? 0} · Rossi ${event['cartellino_rosso'] ?? 0}',
                ),
                trailing: Text('Voto ${event['voto'] ?? '—'}'),
                isThreeLine: true,
              ),
            ),
          ),
        ],
      );
    },
  );
}
