import 'package:flutter/material.dart';

import 'api.dart';
export 'team_page.dart';
import 'theme.dart';
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
        if (api != null)
          Center(
            child: SiteImage(
              api: api!,
              path: '${player['foto'] ?? ''}',
              width: 80,
              height: 80,
              fallback: Icons.person_outline,
            ),
          )
        else
          const Icon(Icons.person_outline, size: 80),
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
  const MatchPage({
    super.key,
    required this.api,
    required this.id,
    this.previewMatch = const {},
  });
  final TosApi api;
  final String id;
  final Map<String, dynamic> previewMatch;

  String dateLabel(dynamic value) {
    final date = DateTime.tryParse('$value');
    if (date == null) {
      return '$value'.trim().isEmpty ? 'Data da definire' : '$value';
    }
    const months = [
      'gen',
      'feb',
      'mar',
      'apr',
      'mag',
      'giu',
      'lug',
      'ago',
      'set',
      'ott',
      'nov',
      'dic',
    ];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }

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
      final match = {
        ...previewMatch,
        ...Map<String, dynamic>.from(data['match'] as Map),
      };
      final events = TosApi.rows(data['events']);
      final played = '${match['giocata']}' == '1';
      final home = '${match['squadra_casa'] ?? 'Da definire'}';
      final away = '${match['squadra_ospite'] ?? 'Da definire'}';
      Widget team(String name, String logo) => Expanded(
        child: Column(
          children: [
            SiteImage(
              api: api,
              path: logo,
              width: 56,
              height: 56,
              preserveShape: true,
              fallback: Icons.shield_outlined,
            ),
            const SizedBox(height: 12),
            Text(
              name,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: siteBlue,
              ),
            ),
          ],
        ),
      );
      Widget information(IconData icon, String label, dynamic value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: const Color(0xff778496), size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xff778496),
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '$value',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: siteBlue,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
      Widget statBadge(String label, dynamic value, {Color color = siteBlue}) =>
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.07),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              '$value $label',
              style: TextStyle(
                color: color,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          );
      final groups = <String, List<Map<String, dynamic>>>{};
      for (final event in events) {
        final teamName = '${event['squadra'] ?? ''}'.trim();
        groups
            .putIfAbsent(
              teamName.isEmpty ? 'Altri giocatori' : teamName,
              () => [],
            )
            .add(event);
      }
      final ordered = [
        home,
        if (away != home) away,
        ...groups.keys.where((name) => name != home && name != away),
      ];
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xffe5eaf1)),
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: played
                        ? const Color(0xffe9edf3)
                        : const Color(0xffffeded),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    played ? 'RISULTATO FINALE' : 'DA GIOCARE',
                    style: TextStyle(
                      fontSize: 10,
                      letterSpacing: 1.2,
                      fontWeight: FontWeight.w700,
                      color: played ? siteBlue : siteRed,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    team(home, '${match['logo_casa'] ?? ''}'),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 12,
                      ),
                      child: Text(
                        played
                            ? '${match['gol_casa']} : ${match['gol_ospite']}'
                            : 'VS',
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.w800,
                          color: siteBlue,
                        ),
                      ),
                    ),
                    team(away, '${match['logo_ospite'] ?? ''}'),
                  ],
                ),
                if ('${match['decisa_rigori']}' == '1') ...[
                  const SizedBox(height: 16),
                  Text(
                    'Rigori ${match['rigori_casa']} : ${match['rigori_ospite']}',
                    style: const TextStyle(
                      color: siteRed,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                const SizedBox(height: 22),
                const Divider(height: 1, color: Color(0xffe5eaf1)),
                const SizedBox(height: 16),
                Text(
                  [
                    dateLabel(match['data_partita'] ?? ''),
                    '${match['ora_partita'] ?? ''}'.replaceFirst(
                      RegExp(r':00$'),
                      '',
                    ),
                  ].where((value) => value.isNotEmpty).join(' · '),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: Color(0xff778496),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: const Color(0xffe5eaf1)),
            ),
            child: Column(
              children: [
                if ('${match['campo'] ?? ''}'.trim().isNotEmpty)
                  information(
                    Icons.location_on_outlined,
                    'Campo',
                    match['campo'],
                  ),
                if ('${match['arbitro'] ?? ''}'.trim().isNotEmpty)
                  information(
                    Icons.sports_outlined,
                    'Arbitro',
                    match['arbitro'],
                  ),
                if (match['giornata'] != null)
                  information(
                    Icons.calendar_today_outlined,
                    'Giornata',
                    match['giornata'],
                  ),
                if ('${match['fase'] ?? ''}'.trim().isNotEmpty)
                  information(Icons.layers_outlined, 'Fase', match['fase']),
                if ('${match['fase_round'] ?? ''}'.trim().isNotEmpty)
                  information(
                    Icons.emoji_events_outlined,
                    'Turno',
                    match['fase_round'],
                  ),
                if ('${match['fase_leg']}' == '1' ||
                    '${match['fase_leg']}' == '2')
                  information(
                    Icons.swap_horiz_rounded,
                    'Incontro',
                    '${match['fase_leg']}' == '1' ? 'Andata' : 'Ritorno',
                  ),
              ],
            ),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 28, bottom: 8),
            child: Text(
              'Referto giocatori',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: siteBlue,
              ),
            ),
          ),
          if (events.isEmpty)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
              ),
              child: const Column(
                children: [
                  Icon(
                    Icons.assignment_outlined,
                    size: 32,
                    color: Color(0xff778496),
                  ),
                  SizedBox(height: 12),
                  Text(
                    'Referto non ancora disponibile.',
                    style: TextStyle(color: Color(0xff778496)),
                  ),
                ],
              ),
            ),
          for (final name in ordered.where(groups.containsKey)) ...[
            Padding(
              padding: const EdgeInsets.only(top: 16, bottom: 12),
              child: Text(
                name,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: Color(0xff778496),
                ),
              ),
            ),
            for (final event in groups[name]!)
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xffe5eaf1)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        SiteImage(
                          api: api,
                          path: '${event['foto'] ?? ''}',
                          width: 40,
                          height: 40,
                          fallback: Icons.person_outline,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            '${event['nome']} ${event['cognome']}',
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: siteBlue,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: siteBlue,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            children: [
                              const Text(
                                'VOTO',
                                style: TextStyle(
                                  color: Color(0xffcdd8e5),
                                  fontSize: 9,
                                  letterSpacing: 0.6,
                                ),
                              ),
                              Text(
                                '${event['voto'] ?? '—'}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 16,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        statBadge('gol', event['goal'] ?? 0),
                        statBadge('assist', event['assist'] ?? 0),
                        statBadge('autogol', event['autogol'] ?? 0),
                        statBadge(
                          'gialli',
                          event['cartellino_giallo'] ?? 0,
                          color: const Color(0xff996b00),
                        ),
                        statBadge(
                          'rossi',
                          event['cartellino_rosso'] ?? 0,
                          color: siteRed,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
          ],
        ],
      );
    },
  );
}
