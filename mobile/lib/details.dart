import 'package:flutter/material.dart';

import 'api.dart';
import 'sport_controls.dart';
import 'match_report_logic.dart';
import 'venue_navigation.dart';

import 'package:url_launcher/url_launcher.dart';

import 'access_gate.dart';
import 'external_link_button.dart';
import 'player_event_marks.dart';
import 'player_role_badges.dart';
import 'player_awards.dart';
export 'team_page.dart';
import 'tournament_logic.dart' show phaseKey, matchTurn, roundLabel, matchLeg;
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
          if (AccessRequiredPanel.handles(context, snapshot.error)) {
            return AccessRequiredPanel(
              retry: () => setState(() => data = widget.load()),
            );
          }
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
                    onPressed: () => setState(() {
                      data = widget.load();
                    }),
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
        PlayerEventMarks(
          stats: {
            'goal': player['reti'] ?? player['gol'] ?? 0,
            'cartellino_giallo': player['gialli'] ?? 0,
            'cartellino_rosso': player['rossi'] ?? 0,
          },
        ),
        for (final entry in {
          'Presenze': 'presenze',
          'Media voti': 'media_voti',
        }.entries)
          if ((num.tryParse('${player[entry.value]}') ?? 0) > 0)
            ListTile(
              title: Text(entry.key),
              trailing: Text('${player[entry.value]}'),
            ),
        if (api != null) ...[
          const SizedBox(height: 28),
          PlayerAwardsSection(
            api: api!,
            playerId: '${player['id'] ?? player['giocatore_id'] ?? ''}',
            section: '${player['sezione'] ?? 'calcio'}',
          ),
        ],
      ],
    ),
  );
}

class MatchPage extends StatefulWidget {
  const MatchPage({
    super.key,
    required this.api,
    required this.id,
    this.previewMatch = const {},
  });
  final TosApi api;
  final String id;
  final Map<String, dynamic> previewMatch;

  @override
  State<MatchPage> createState() => _MatchPageState();
}

class _MatchPageState extends State<MatchPage> {
  bool showAllPlayers = false;
  TosApi get api => widget.api;
  String get id => widget.id;
  Map<String, dynamic> get previewMatch => widget.previewMatch;

  @override
  void didUpdateWidget(MatchPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.id != id || oldWidget.api != api) showAllPlayers = false;
  }

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
    key: ValueKey((api, id)),
    title: 'Dettaglio partita',
    load: () async {
      final match = await api.match(id);
      final events = matchWasPlayed(match)
          ? await api.events(id)
          : <Map<String, dynamic>>[];
      return {'match': match, 'events': events};
    },
    render: (data) {
      final colors = Theme.of(context).colorScheme;
      final match = {
        ...previewMatch,
        ...Map<String, dynamic>.from(data['match'] as Map),
      };
      final played = matchWasPlayed(match);
      final events = played
          ? TosApi.rows(data['events'])
          : <Map<String, dynamic>>[];
      final visibleEvents = showAllPlayers
          ? events
          : events.where(hasPlayerEvents).toList();
      final home = '${match['squadra_casa'] ?? 'Da definire'}';
      final away = '${match['squadra_ospite'] ?? 'Da definire'}';
      Widget team(String name, String logo) => Expanded(
        child: Column(
          children: [
            SiteImage(
              api: api,
              path: logo,
              width: 44,
              height: 44,
              preserveShape: true,
              fallback: Icons.shield_outlined,
            ),
            const SizedBox(height: 8),
            Text(
              name,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: colors.onSurface,
              ),
            ),
          ],
        ),
      );
      Widget information(IconData icon, String label, dynamic value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: colors.onSurfaceVariant, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 12,
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '$value',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: colors.onSurface,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
      final groups = <String, List<Map<String, dynamic>>>{};
      for (final event in visibleEvents) {
        final teamName = '${event['squadra'] ?? ''}'.trim();
        groups
            .putIfAbsent(
              teamName.isEmpty ? 'Altri giocatori' : teamName,
              () => [],
            )
            .add(event);
      }
      if (showAllPlayers) {
        for (final entry in groups.entries) {
          groups[entry.key] = orderedMatchPlayers(entry.value);
        }
      }
      Widget playerCard(Map<String, dynamic> event) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: colors.outlineVariant),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                SiteImage(
                  api: api,
                  path: '${event['foto'] ?? ''}',
                  width: 34,
                  height: 34,
                  fallback: Icons.person_outline,
                ),
                PlayerRoleBadges(player: event),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colors.primaryContainer,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    children: [
                      Text(
                        'VOTO',
                        style: TextStyle(
                          color: colors.onPrimaryContainer,
                          fontSize: 9,
                          letterSpacing: 0.6,
                        ),
                      ),
                      Text(
                        '${event['voto'] ?? '—'}',
                        style: TextStyle(
                          color: colors.onPrimaryContainer,
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '${event['nome']} ${event['cognome']}',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: colors.onSurface,
              ),
            ),
            PlayerEventMarks(stats: event),
            if ((int.tryParse('${event['assist']}') ?? 0) > 0)
              Text(
                'Assist: ${event['assist']}',
                style: TextStyle(color: colors.onSurfaceVariant, fontSize: 12),
              ),
          ],
        ),
      );
      Widget reportColumn(String name, String side) => Column(
        key: ValueKey('match-report-$side'),
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  side,
                  style: TextStyle(
                    fontSize: 10,
                    letterSpacing: 1.2,
                    fontWeight: FontWeight.w700,
                    color: colors.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    SiteImage(
                      api: api,
                      path:
                          '${match[side == 'CASA' ? 'logo_casa' : 'logo_ospite'] ?? ''}',
                      width: 22,
                      height: 22,
                      preserveShape: true,
                      fallback: Icons.shield_outlined,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        name,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: colors.onSurface,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if ((groups[name] ?? []).isEmpty)
            Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                showAllPlayers ? 'Nessun referto.' : 'Nessun gol o cartellino.',
                style: TextStyle(fontSize: 12, color: colors.onSurfaceVariant),
              ),
            ),
          for (final event in groups[name] ?? <Map<String, dynamic>>[])
            playerCard(event),
        ],
      );
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: colors.outlineVariant),
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
                        ? colors.primaryContainer
                        : colors.secondaryContainer,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    played ? 'RISULTATO FINALE' : 'DA GIOCARE',
                    style: TextStyle(
                      fontSize: 10,
                      letterSpacing: 1.2,
                      fontWeight: FontWeight.w700,
                      color: played
                          ? colors.onPrimaryContainer
                          : colors.onSecondaryContainer,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
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
                        style: TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.w800,
                          color: colors.onSurface,
                        ),
                      ),
                    ),
                    team(away, '${match['logo_ospite'] ?? ''}'),
                  ],
                ),
                if (played && '${match['decisa_rigori']}' == '1') ...[
                  const SizedBox(height: 16),
                  Text(
                    'Rigori ${match['rigori_casa']} : ${match['rigori_ospite']}',
                    style: TextStyle(
                      color: colors.secondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Divider(height: 1, color: colors.outlineVariant),
                const SizedBox(height: 16),
                Text(
                  [
                    dateLabel(match['data_partita'] ?? ''),
                    '${match['ora_partita'] ?? ''}'.replaceFirst(
                      RegExp(r':00$'),
                      '',
                    ),
                  ].where((value) => value.isNotEmpty).join(' · '),
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            decoration: BoxDecoration(
              color: colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: colors.outlineVariant),
            ),
            child: LayoutBuilder(
              builder: (context, constraints) {
                final twoColumns =
                    constraints.maxWidth >= 300 &&
                    MediaQuery.textScalerOf(context).scale(14) < 20;
                final items = <Widget>[
                  if ('${match['campo'] ?? ''}'.trim().isNotEmpty)
                    InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: venueNavigationUri(match) == null
                          ? null
                          : () async {
                              try {
                                if (await launchUrl(
                                  venueNavigationUri(match)!,
                                  mode: LaunchMode.externalApplication,
                                )) {
                                  return;
                                }
                              } catch (_) {
                                /* Show an actionable failure below. */
                              }
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Impossibile aprire la navigazione.',
                                    ),
                                  ),
                                );
                              }
                            },
                      child: information(
                        Icons.location_on_outlined,
                        venueNavigationUri(match) == null
                            ? 'Campo'
                            : 'Campo · Indicazioni',
                        match['campo'],
                      ),
                    ),
                  if ('${match['arbitro'] ?? ''}'.trim().isNotEmpty)
                    information(
                      Icons.sports_outlined,
                      'Arbitro',
                      match['arbitro'],
                    ),
                  if (match['giornata'] != null &&
                      phaseKey(match['fase']) == 'REGULAR')
                    information(
                      Icons.calendar_today_outlined,
                      'Giornata',
                      match['giornata'],
                    ),
                  if ('${match['fase'] ?? ''}'.trim().isNotEmpty)
                    information(Icons.layers_outlined, 'Fase', match['fase']),
                  if (phaseKey(match['fase']) != 'REGULAR')
                    information(
                      Icons.emoji_events_outlined,
                      'Turno',
                      roundLabel(
                        matchTurn(match, phaseKey(match['fase'])),
                        phaseKey(match['fase']),
                      ),
                    ),
                  if (phaseKey(match['fase']) == 'REGULAR' &&
                      matchLeg(match['fase_leg']).isNotEmpty)
                    information(
                      Icons.swap_horiz_rounded,
                      'Incontro',
                      matchLeg(match['fase_leg']) == 'ANDATA'
                          ? 'Andata'
                          : 'Ritorno',
                    ),
                ];
                return Wrap(
                  spacing: 12,
                  children: [
                    for (final item in items)
                      SizedBox(
                        width: twoColumns
                            ? (constraints.maxWidth - 12) / 2
                            : constraints.maxWidth,
                        child: item,
                      ),
                  ],
                );
              },
            ),
          ),
          MatchLinks(match: match),
          if (played)
            Padding(
              padding: EdgeInsets.only(top: 20, bottom: 8),
              child: Text(
                'Referto giocatori',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: colors.onSurface,
                ),
              ),
            ),
          if (events.isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: SportSegments<bool>(
                value: showAllPlayers,
                options: const {false: 'Con eventi', true: 'Tutti'},
                onChanged: (value) => setState(() => showAllPlayers = value),
              ),
            ),
            if (visibleEvents.isEmpty)
              Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Text(
                  'Nessun gol o cartellino. Tocca “Tutti” per vedere tutti i giocatori.',
                ),
              ),
          ],
          if (events.isEmpty)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Column(
                children: [
                  Icon(
                    Icons.assignment_outlined,
                    size: 32,
                    color: colors.onSurfaceVariant,
                  ),
                  SizedBox(height: 12),
                  Text(
                    played ? 'Referto non ancora disponibile.' : 'Partita da disputare. Il referto sarà disponibile dopo la gara.',
                    style: TextStyle(color: colors.onSurfaceVariant),
                  ),
                ],
              ),
            ),
          if (visibleEvents.isNotEmpty)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: reportColumn(home, 'CASA')),
                const SizedBox(width: 12),
                Expanded(child: reportColumn(away, 'OSPITE')),
              ],
            ),
          for (final name in groups.keys.where(
            (name) => name != home && name != away,
          )) ...[
            Padding(
              padding: const EdgeInsets.only(top: 16, bottom: 12),
              child: Text(
                name,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: colors.onSurfaceVariant,
                ),
              ),
            ),
            for (final event in groups[name]!) playerCard(event),
          ],
        ],
      );
    },
  );
}

class MatchLinks extends StatelessWidget {
  const MatchLinks({super.key, required this.match});
  final Map<String, dynamic> match;

  @override
  Widget build(BuildContext context) {
    final youtube = externalLinkUri(match['link_youtube']);
    final instagram = externalLinkUri(match['link_instagram']);
    if (youtube == null && instagram == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          if (youtube != null)
            ExternalLinkButton(
              uri: youtube,
              label: 'Guarda su YouTube',
              icon: Icons.play_circle_outline,
              color: const Color(0xffd80000),
            ),
          if (instagram != null)
            ExternalLinkButton(
              uri: instagram,
              label: 'Guarda su Instagram',
              icon: Icons.camera_alt_outlined,
              color: const Color(0xffb52b74),
            ),
        ],
      ),
    );
  }
}
