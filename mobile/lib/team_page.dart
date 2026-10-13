import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage;
import 'home_dashboard.dart' show SiteImage, HomeBlock, openPage;
import 'theme.dart';
import 'tournament_logic.dart' show stat, phaseKey, matchTurn, roundLabel;
import 'tournament_list.dart' show normalizedSearch;

List<Map<String, dynamic>> teamPlayedMatches(
  List<Map<String, dynamic>> matches,
  String name,
) {
  final key = normalizedSearch(name);
  final rows = matches
      .where(
        (m) =>
            stat(m['giocata']) == 1 &&
            [
              m['squadra_casa'],
              m['squadra_ospite'],
            ].any((v) => normalizedSearch('${v ?? ''}') == key),
      )
      .toList();
  rows.sort((a, b) {
    int date(Map<String, dynamic> m) =>
        DateTime.tryParse(
          '${m['data_partita'] ?? ''} ${m['ora_partita'] ?? '00:00'}'.trim(),
        )?.millisecondsSinceEpoch ??
        0;
    final diff = date(b).compareTo(date(a));
    return diff != 0 ? diff : stat(b['id']).compareTo(stat(a['id']));
  });
  return rows;
}

String teamMatchOutcome(Map<String, dynamic> match, String team) {
  if (match['gol_casa'] == null || match['gol_ospite'] == null) {
    return 'Disputata';
  }
  var home = stat(match['gol_casa']), away = stat(match['gol_ospite']);
  if (home == away &&
      stat(match['decisa_rigori']) == 1 &&
      match['rigori_casa'] != null &&
      match['rigori_ospite'] != null) {
    home = stat(match['rigori_casa']);
    away = stat(match['rigori_ospite']);
  }
  if (home == away) return 'Pareggio';
  final isHome =
      normalizedSearch('${match['squadra_casa']}') == normalizedSearch(team);
  return (isHome ? home > away : away > home) ? 'Vittoria' : 'Sconfitta';
}

class TeamPage extends StatefulWidget {
  const TeamPage({
    super.key,
    required this.api,
    required this.slug,
    required this.team,
    this.initialTab = 'Partite',
  });
  final TosApi api;
  final String slug, initialTab;
  final Map<String, dynamic> team;
  @override
  State<TeamPage> createState() => _TeamPageState();
}

class _TeamPageState extends State<TeamPage> {
  late String tab;
  List<Map<String, dynamic>>? players, matches;
  int reload = 0;
  @override
  void initState() {
    super.initState();
    tab = widget.initialTab == 'Rosa' ? 'Rosa' : 'Partite';
  }

  Future<List<Map<String, dynamic>>> loadPlayers() async => players ??=
      await widget.api.roster(widget.slug, '${widget.team['nome']}');
  Future<List<Map<String, dynamic>>> loadMatches() async =>
      matches ??= teamPlayedMatches(
        await widget.api.matches(widget.slug),
        '${widget.team['nome']}',
      );
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final team = widget.team;
    Widget metric(String label, dynamic value) => Expanded(
      child: Column(
        children: [
          Text(
            '$value',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 4),
          Text(label, style: TextStyle(fontSize: 12, color: Color(0xffbdcbdc))),
        ],
      ),
    );
    return Scaffold(
      appBar: AppBar(title: Text('${team['nome']}')),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(() {
            players = null;
            matches = null;
            reload++;
          });
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(
                color: colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(22),
                    child: Row(
                      children: [
                        SiteImage(
                          api: widget.api,
                          path: '${team['logo'] ?? ''}',
                          width: 68,
                          height: 68,
                          preserveShape: true,
                          fallback: Icons.shield_outlined,
                        ),
                        const SizedBox(width: 18),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'SQUADRA',
                                style: TextStyle(
                                  fontSize: 10,
                                  letterSpacing: 1.6,
                                  fontWeight: FontWeight.w700,
                                  color: colors.secondary,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '${team['nome']}',
                                style: TextStyle(
                                  fontSize: 26,
                                  fontWeight: FontWeight.w700,
                                  color: colors.onSurface,
                                ),
                              ),
                              if ('${team['girone'] ?? ''}'
                                  .trim()
                                  .isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  'Girone ${team['girone']}',
                                  style: TextStyle(
                                    color: colors.onSurfaceVariant,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 20,
                    ),
                    color: dark ? colors.surfaceContainer : siteBlue,
                    child: Row(
                      children: [
                        metric('Punti', team['punti'] ?? 0),
                        metric('Giocate', team['giocate'] ?? 0),
                        metric('Gol fatti', team['gol_fatti'] ?? 0),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(14),
                    child: Wrap(
                      spacing: 14,
                      runSpacing: 8,
                      alignment: WrapAlignment.center,
                      children: [
                        for (final entry in {
                          'Vinte': 'vinte',
                          'Pareggi': 'pareggiate',
                          'Perse': 'perse',
                          'Subiti': 'gol_subiti',
                        }.entries)
                          Text(
                            '${team[entry.value] ?? 0} ${entry.key.toLowerCase()}',
                            style: TextStyle(
                              fontSize: 12,
                              color: colors.onSurfaceVariant,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.all(5),
              decoration: BoxDecoration(
                color: colors.surfaceContainerHigh,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  for (final name in ['Partite', 'Rosa'])
                    Expanded(
                      child: Semantics(
                        button: true,
                        selected: tab == name,
                        child: Material(
                          color: tab == name
                              ? colors.primaryContainer
                              : Colors.transparent,
                          borderRadius: BorderRadius.circular(14),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () => setState(() => tab = name),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    name == 'Partite'
                                        ? Icons.sports_soccer_rounded
                                        : Icons.groups_outlined,
                                    size: 19,
                                    color: colors.onSurface,
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    name,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: colors.onSurface,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            HomeBlock(
              key: ValueKey('$tab-$reload'),
              load: tab == 'Rosa' ? loadPlayers : loadMatches,
              render: (value) {
                final rows = TosApi.rows(value);
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            tab == 'Rosa'
                                ? 'Rosa squadra'
                                : 'Partite disputate',
                            style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w700,
                              color: colors.onSurface,
                            ),
                          ),
                        ),
                        Text(
                          '${rows.length} ${tab == 'Rosa' ? 'giocatori' : 'partite'}',
                          style: TextStyle(
                            color: colors.onSurfaceVariant,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    if (rows.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(28),
                        decoration: BoxDecoration(
                          color: colors.surfaceContainerLow,
                          borderRadius: BorderRadius.circular(22),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              tab == 'Rosa'
                                  ? Icons.groups_outlined
                                  : Icons.sports_soccer_rounded,
                              size: 32,
                              color: colors.onSurfaceVariant,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              tab == 'Rosa'
                                  ? 'Rosa non ancora disponibile.'
                                  : 'Nessuna partita disputata.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: colors.onSurfaceVariant),
                            ),
                          ],
                        ),
                      ),
                    if (tab == 'Rosa')
                      RosterGrid(api: widget.api, players: rows)
                    else
                      for (final match in rows)
                        TeamMatchTile(
                          api: widget.api,
                          match: match,
                          team: '${team['nome']}',
                        ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class RosterGrid extends StatelessWidget {
  const RosterGrid({super.key, required this.api, required this.players});
  final TosApi api;
  final List<Map<String, dynamic>> players;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final two =
          constraints.maxWidth >= 325 &&
          MediaQuery.textScalerOf(context).scale(14) <= 18;
      final width = two
          ? (constraints.maxWidth - 12) / 2
          : constraints.maxWidth;
      return Wrap(
        spacing: 12,
        runSpacing: 14,
        children: [
          for (final player in players)
            SizedBox(
              width: width,
              child: RosterPlayerCard(api: api, player: player),
            ),
        ],
      );
    },
  );
}

class RosterPlayerCard extends StatelessWidget {
  const RosterPlayerCard({super.key, required this.api, required this.player});
  final TosApi api;
  final Map<String, dynamic> player;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final photo = '${player['foto'] ?? ''}'.trim();
    final hasPhoto =
        photo.isNotEmpty &&
        !RegExp(
          r'(^|/)unknown\.(jpg|jpeg|png)(?:[?#].*)?$',
          caseSensitive: false,
        ).hasMatch(photo);
    final initials = [player['nome'], player['cognome']]
        .map((v) => '${v ?? ''}'.trim())
        .where((v) => v.isNotEmpty)
        .map((v) => v.characters.first)
        .join()
        .toUpperCase();
    final captain = stat(player['is_captain'] ?? player['captain']) == 1;
    final teamRole = '${player['ruolo_squadra'] ?? ''}'.trim();
    final role = teamRole.isNotEmpty
        ? teamRole
        : '${player['ruolo'] ?? ''}'.trim();
    final goalkeeper = RegExp(
      r'portiere|\bgk\b|^(p|por)$',
      caseSensitive: false,
    ).hasMatch(role);
    Widget badge(
      String label,
      String description,
      Color color,
      Color textColor,
    ) => Tooltip(
      message: description,
      child: Semantics(
        label: description,
        excludeSemantics: true,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(7),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: textColor,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0815293e),
            blurRadius: 16,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => openPage(context, PlayerPage(api: api, player: player)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LayoutBuilder(
                builder: (context, constraints) => Container(
                  height: 110,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: dark
                          ? [
                              colors.primaryContainer,
                              colors.surfaceContainerHigh,
                            ]
                          : [siteBlue, const Color(0xff345778)],
                    ),
                  ),
                  child: Stack(
                    children: [
                      Positioned(
                        right: -12,
                        bottom: -18,
                        child: Icon(
                          Icons.sports_soccer_rounded,
                          size: 100,
                          color: Colors.white.withValues(alpha: 0.05),
                        ),
                      ),
                      Center(
                        child: hasPhoto
                            ? SiteImage(
                                api: api,
                                path: photo,
                                width: constraints.maxWidth,
                                height: 110,
                                fallback: Icons.person_outline,
                              )
                            : Text(
                                initials,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 38,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 2,
                                ),
                              ),
                      ),
                      if (goalkeeper || captain)
                        Positioned(
                          top: 10,
                          right: 10,
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              if (goalkeeper)
                                badge(
                                  'GK',
                                  'Portiere',
                                  const Color(0xffffd43b),
                                  siteBlue,
                                ),
                              if (goalkeeper && captain)
                                const SizedBox(width: 6),
                              if (captain)
                                badge('C', 'Capitano', siteRed, Colors.white),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${player['nome']} ${player['cognome']}',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: colors.onSurface,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      role.isEmpty ? 'Giocatore' : role,
                      style: TextStyle(
                        fontSize: 12,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Divider(height: 1, color: colors.outlineVariant),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        for (final entry in {
                          'Presenze': 'presenze',
                          'Gol': 'reti',
                          'Assist': 'assist',
                        }.entries)
                          Expanded(
                            child: Column(
                              children: [
                                Text(
                                  '${player[entry.value] ?? 0}',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w700,
                                    color: colors.onSurface,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  entry.key,
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: colors.onSurfaceVariant,
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TeamMatchTile extends StatelessWidget {
  const TeamMatchTile({
    super.key,
    required this.api,
    required this.match,
    required this.team,
  });
  final TosApi api;
  final Map<String, dynamic> match;
  final String team;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final outcome = teamMatchOutcome(match, team);
    final color = outcome == 'Vittoria'
        ? (dark ? const Color(0xff9dd6ba) : const Color(0xff18765d))
        : outcome == 'Sconfitta'
        ? colors.secondary
        : colors.onSurfaceVariant;
    final date = DateTime.tryParse('${match['data_partita'] ?? ''}');
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
    final dateText = date == null
        ? 'Data da definire'
        : '${date.day} ${months[date.month - 1]} ${date.year}';
    final phase = phaseKey(match['fase']);
    final phaseName =
        const {
          'REGULAR': 'Regular season',
          'GOLD': 'Coppa Gold',
          'SILVER': 'Coppa Silver',
          'BRONZO': 'Coppa Bronzo',
          'SPAREGGIO': 'Spareggio',
        }[phase] ??
        phase;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: match['id'] == null
              ? null
              : () => openPage(
                  context,
                  MatchPage(
                    api: api,
                    id: '${match['id']}',
                    previewMatch: match,
                  ),
                ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        dateText,
                        style: TextStyle(
                          fontSize: 12,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        outcome,
                        style: TextStyle(
                          color: color,
                          fontWeight: FontWeight.w600,
                          fontSize: 11,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        children: [
                          SiteImage(
                            api: api,
                            path: '${match['logo_casa'] ?? ''}',
                            width: 32,
                            height: 32,
                            preserveShape: true,
                            fallback: Icons.shield_outlined,
                          ),
                          const SizedBox(height: 7),
                          Text(
                            '${match['squadra_casa']}',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: colors.onSurface,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text(
                        '${match['gol_casa'] ?? '—'} : ${match['gol_ospite'] ?? '—'}',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w700,
                          color: colors.onSurface,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Column(
                        children: [
                          SiteImage(
                            api: api,
                            path: '${match['logo_ospite'] ?? ''}',
                            width: 32,
                            height: 32,
                            preserveShape: true,
                            fallback: Icons.shield_outlined,
                          ),
                          const SizedBox(height: 7),
                          Text(
                            '${match['squadra_ospite']}',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: colors.onSurface,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (stat(match['decisa_rigori']) == 1) ...[
                  const SizedBox(height: 10),
                  Text(
                    'Rigori ${match['rigori_casa'] ?? '—'} : ${match['rigori_ospite'] ?? '—'}',
                    style: TextStyle(fontSize: 12, color: siteRed),
                  ),
                ],
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        [
                          phaseName,
                          roundLabel(matchTurn(match, phase), phase),
                        ].join(' · '),
                        style: TextStyle(
                          fontSize: 11,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ),
                    Icon(
                      Icons.arrow_forward_rounded,
                      size: 17,
                      color: colors.onSurface,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
