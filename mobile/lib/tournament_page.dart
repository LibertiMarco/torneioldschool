import 'package:flutter/material.dart';

import 'api.dart';
import 'ads.dart';
import 'details.dart';
import 'home_dashboard.dart' show SiteImage, HomeBlock, openPage;
import 'tournament_rules.dart';
import 'main.dart' show ErrorPanel;
import 'theme.dart';
import 'ranking_surface.dart';
import 'tournament_logic.dart';
import 'tournament_notifications.dart';

const cupColors = {
  'GOLD': Color(0xffffd700),
  'SILVER': Color(0xffc0c0c0),
  'BRONZO': Color(0xffcd7f32),
  'SPAREGGIO': siteBlue,
  'ELIMINATO': Color(0xfff8d7da),
};
const phaseLabels = {
  'REGULAR': 'Regular season',
  'SPAREGGIO': 'Spareggio',
  'GOLD': 'Coppa Gold',
  'SILVER': 'Coppa Silver',
  'BRONZO': 'Coppa Bronzo',
};

InputDecoration tournamentSelectorDecoration(
  BuildContext context,
  String hint,
  IconData icon,
) => InputDecoration(
  hintText: hint,
  filled: true,
  fillColor: Theme.of(context).colorScheme.surfaceContainerLow,
  prefixIcon: Icon(
    icon,
    size: 20,
    color: Theme.of(context).colorScheme.primary,
  ),
  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),
  border: OutlineInputBorder(
    borderRadius: BorderRadius.circular(16),
    borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
  ),
  enabledBorder: OutlineInputBorder(
    borderRadius: BorderRadius.circular(16),
    borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
  ),
  focusedBorder: OutlineInputBorder(
    borderRadius: BorderRadius.circular(16),
    borderSide: BorderSide(
      color: Theme.of(context).colorScheme.primary,
      width: 1.5,
    ),
  ),
);

class TournamentPage extends StatefulWidget {
  const TournamentPage({
    super.key,
    required this.api,
    required this.tournament,
  });
  final TosApi api;
  final Map<String, dynamic> tournament;
  @override
  State<TournamentPage> createState() => _TournamentPageState();
}

class _TournamentPageState extends State<TournamentPage> {
  late Future<TournamentData> data;
  String tab = 'Classifica', tablePhase = 'REGULAR', calendarPhase = 'REGULAR';
  String? round;
  int reload = 0;
  String get slug => TosApi.tournamentSlug(widget.tournament);
  @override
  void initState() {
    super.initState();
    data = TournamentData.load(widget.api, slug);
  }

  Future<void> refresh() async {
    final next = TournamentData.load(widget.api, slug);
    setState(() {
      data = next;
      reload++;
    });
    try {
      await next;
    } catch (_) {
      /* The page presents the failure and retry. */
    }
  }

  Widget title(String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Text(
      value,
      style: TextStyle(
        fontSize: 22,
        fontWeight: FontWeight.bold,
        color: Theme.of(context).colorScheme.primary,
      ),
    ),
  );
  Widget phaseSelect(
    TournamentData result,
    String selected,
    ValueChanged<String> onChanged,
  ) => DropdownButtonFormField<String>(
    initialValue: selected,
    isExpanded: true,
    icon: Icon(
      Icons.expand_more_rounded,
      color: Theme.of(context).colorScheme.primary,
    ),
    borderRadius: BorderRadius.circular(18),
    dropdownColor: Theme.of(context).colorScheme.surfaceContainerHigh,
    style: Theme.of(context).textTheme.bodyMedium!
        .copyWith(fontWeight: FontWeight.w600),
    decoration: tournamentSelectorDecoration(
      context,
      'Seleziona fase',
      Icons.layers_outlined,
    ),
    items: result.phases
        .map(
          (p) => DropdownMenuItem(value: p, child: Text(phaseLabels[p] ?? p)),
        )
        .toList(),
    onChanged: (v) {
      if (v != null) onChanged(v);
    },
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('${widget.tournament['nome']}')),
    body: FutureBuilder<TournamentData>(
      future: data,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return ErrorPanel(error: snapshot.error!, retry: refresh);
        }
        final result = snapshot.data!;
        final info = {...widget.tournament, ...result.info};
        final esport = '${info['sezione']}' == 'esport';
        final tabs = [
          'Classifica',
          if (!esport) ...['Marcatori', 'Diffidati'],
          'Calendario',
          'Rose',
          'Regole',
        ];
        if (!tabs.contains(tab)) tab = 'Classifica';
        if (!result.phases.contains(tablePhase)) {
          tablePhase = result.phases.first;
        }
        if (!result.phases.contains(calendarPhase)) {
          calendarPhase = result.phases.first;
          round = null;
        }
        return RefreshIndicator(
          onRefresh: refresh,
          child: ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              Card(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(18),
                  side: BorderSide(
                    color: Theme.of(context).colorScheme.outlineVariant,
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      SiteImage(
                        api: widget.api,
                        path: '${info['img'] ?? ''}',
                        width: 72,
                        height: 72,
                        fallback: Icons.emoji_events_outlined,
                        preserveShape: true,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${info['nome']}',
                              style: TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text('${info['categoria'] ?? ''}'),
                            if ('${info['stato'] ?? ''}'.isNotEmpty)
                              Text(
                                '${info['stato']}',
                                style: const TextStyle(color: siteRed),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              TournamentNotifications(
                key: ValueKey(slug),
                slug: slug,
                teamLogos: {
                  for (final team in result.teams)
                    '${team['nome']}': '${team['logo'] ?? ''}',
                },
              ),
              const SizedBox(height: 16),
              LayoutBuilder(
                builder: (context, constraints) {
                  final width = (constraints.maxWidth - 10) / 2;
                  const icons = {
                    'Classifica': Icons.leaderboard_outlined,
                    'Marcatori': Icons.sports_soccer_rounded,
                    'Diffidati': Icons.warning_amber_rounded,
                    'Calendario': Icons.calendar_today_rounded,
                    'Rose': Icons.groups_outlined,
                    'Regole': Icons.description_outlined,
                  };
                  return Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: tabs
                        .map(
                          (label) => SizedBox(
                            width: width,
                            child: Semantics(
                              button: true,
                              selected: tab == label,
                              child: AnimatedContainer(
                                duration: const Duration(milliseconds: 180),
                                decoration: BoxDecoration(
                                  color: tab == label
                                      ? Theme.of(context).colorScheme.primary
                                      : Theme.of(context)
                                            .colorScheme
                                            .surfaceContainerHigh,
                                  borderRadius: BorderRadius.circular(16),
                                  boxShadow: tab == label
                                      ? const [
                                          BoxShadow(
                                            color: Color(0x2415293e),
                                            blurRadius: 12,
                                            offset: Offset(0, 4),
                                          ),
                                        ]
                                      : null,
                                ),
                                child: Material(
                                  color: Colors.transparent,
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(16),
                                    onTap: () => setState(() => tab = label),
                                    child: Padding(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 12,
                                        vertical: 16,
                                      ),
                                      child: Row(
                                        children: [
                                          Icon(
                                            icons[label],
                                            size: 20,
                                            color: tab == label
                                                ? Theme.of(context)
                                                      .colorScheme
                                                      .onPrimary
                                                : Theme.of(context)
                                                      .colorScheme
                                                      .onSurface,
                                          ),
                                          const SizedBox(width: 10),
                                          Flexible(
                                            child: Text(
                                              label,
                                              style: TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w600,
                                                color: tab == label
                                                    ? Theme.of(context)
                                                          .colorScheme
                                                          .onPrimary
                                                    : Theme.of(context)
                                                          .colorScheme
                                                          .onSurface,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        )
                        .toList(),
                  );
                },
              ),
              if (tab == 'Classifica') ...[
                if (!widget.api.preview) const AdaptiveAdBanner(),
                title('Classifica'),
                phaseSelect(
                  result,
                  tablePhase,
                  (v) => setState(() => tablePhase = v),
                ),
                const SizedBox(height: 16),
                if (tablePhase == 'REGULAR') ...[
                  if (result.teams.isEmpty)
                    const Text('Nessuna squadra disponibile.'),
                  ...result.tables.entries.map(
                    (group) => Padding(
                      padding: const EdgeInsets.only(bottom: 20),
                      child: StandingsTable(
                        api: widget.api,
                        slug: slug,
                        label: group.key,
                        teams: group.value,
                        data: result,
                      ),
                    ),
                  ),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: result.tableCupPlaces.entries
                        .where((entry) => entry.value > 0)
                        .map(
                          (entry) => Chip(
                            backgroundColor: cupColors[entry.key],
                            label: Text(
                              '${entry.value}${result.grouped ? ' per girone' : ' posti'} · ${phaseLabels[entry.key]}',
                              style: const TextStyle(color: siteBlue),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Pti punti · G giocate · V vinte · N pareggi · P perse\nGF gol fatti · GS gol subiti · DR differenza reti',
                    style: TextStyle(fontSize: 12, color: Color(0xff526176)),
                  ),
                ] else
                  ...bracket(result, tablePhase),
              ],
              if (tab == 'Calendario') ...[
                title('Calendario'),
                phaseSelect(
                  result,
                  calendarPhase,
                  (v) => setState(() {
                    calendarPhase = v;
                    round = null;
                  }),
                ),
                const SizedBox(height: 12),
                ...calendar(result),
              ],
              if (tab == 'Rose') ...[
                title('Rose Squadre'),
                ...([
                      ...result.teams,
                    ]..sort((a, b) => '${a['nome']}'.compareTo('${b['nome']}')))
                    .map(
                      (team) => Card(
                        child: ListTile(
                          leading: SiteImage(
                            api: widget.api,
                            path: '${team['logo'] ?? ''}',
                            width: 44,
                            height: 44,
                            fallback: Icons.shield_outlined,
                            preserveShape: true,
                          ),
                          title: Text(
                            '${team['nome']}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          subtitle: Text(
                            groupKey(team['girone']).isEmpty
                                ? 'Rosa e statistiche'
                                : 'Girone ${groupKey(team['girone'])}',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => openPage(
                            context,
                            TeamPage(
                              api: widget.api,
                              slug: slug,
                              team: team,
                              initialTab: 'Rosa',
                            ),
                          ),
                        ),
                      ),
                    ),
                if (result.teams.isEmpty)
                  const Text('Squadre non ancora disponibili.'),
              ],
              if (tab == 'Marcatori') ...[
                title('Classifica Marcatori'),
                HomeBlock(
                  key: ValueKey('scorers-$reload'),
                  load: () => widget.api.scorers(slug),
                  render: (value) => TournamentScorers(
                    api: widget.api,
                    players: TosApi.rows(value),
                  ),
                ),
              ],
              if (tab == 'Diffidati') ...[
                title('Elenco Diffidati'),
                const Text('Giocatori in diffida e turni delle ammonizioni.'),
                const SizedBox(height: 12),
                HomeBlock(
                  key: ValueKey('diffidati-$reload'),
                  load: () => widget.api.cautionedPlayers(slug),
                  render: (value) =>
                      TournamentCautionedPlayers(players: TosApi.rows(value)),
                ),
              ],
              if (tab == 'Regole') ...[
                title('Regole del Torneo'),
                TournamentRules(html: result.rules),
              ],
            ],
          ),
        );
      },
    ),
  );
  List<Widget> bracket(TournamentData result, String phase) {
    final rows = result.matches
        .where((m) => phaseKey(m['fase']) == phase)
        .toList();
    final rounds = rows.map((r) => matchTurn(r, phase)).toSet().toList()
      ..sort(roundOrder);
    return [
      if (rows.isEmpty)
        const Padding(
          padding: EdgeInsets.all(24),
          child: Text('Tabellone non ancora disponibile.'),
        ),
      ...rounds.expand(
        (r) => [
          title(roundLabel(r, phase)),
          ...rows
              .where((m) => matchTurn(m, phase) == r)
              .map((m) => MatchCard(api: widget.api, match: m, phase: phase)),
        ],
      ),
    ];
  }

  List<Widget> calendar(TournamentData result) {
    final rows = result.matches
        .where((m) => phaseKey(m['fase']) == calendarPhase)
        .toList();
    final rounds = rows.map((m) => matchTurn(m, calendarPhase)).toSet().toList()
      ..sort(roundOrder);
    if (rounds.isEmpty) {
      return [
        const Padding(
          padding: EdgeInsets.all(24),
          child: Text('Nessuna partita disponibile per questa fase.'),
        ),
      ];
    }
    final chosen = round != null && (round == '' || rounds.contains(round))
        ? round!
        : defaultCalendarRound(result.matches, calendarPhase);
    final visible = rows
        .where((m) => chosen.isEmpty || matchTurn(m, calendarPhase) == chosen)
        .toList();
    visible.sort((a, b) {
      final date = '${a['data_partita'] ?? ''} ${a['ora_partita'] ?? ''}'
          .compareTo('${b['data_partita'] ?? ''} ${b['ora_partita'] ?? ''}');
      return date;
    });
    return [
      DropdownButtonFormField<String>(
        key: ValueKey('$calendarPhase-$chosen'),
        initialValue: chosen,
        isExpanded: true,
        icon: Icon(
          Icons.expand_more_rounded,
          color: Theme.of(context).colorScheme.primary,
        ),
        borderRadius: BorderRadius.circular(18),
        dropdownColor: Theme.of(context).colorScheme.surfaceContainerHigh,
        style: Theme.of(context).textTheme.bodyMedium!
            .copyWith(fontWeight: FontWeight.w600),
        decoration: tournamentSelectorDecoration(
          context,
          calendarPhase == 'REGULAR' ? 'Giornata' : 'Turno',
          Icons.event_outlined,
        ),
        items: [
          const DropdownMenuItem(value: '', child: Text('Tutte')),
          ...rounds.map(
            (r) => DropdownMenuItem(
              value: r,
              child: Text(roundLabel(r, calendarPhase)),
            ),
          ),
        ],
        onChanged: (v) => setState(() => round = v),
      ),
      const SizedBox(height: 16),
      ...rounds
          .where((r) => chosen.isEmpty || r == chosen)
          .expand(
            (r) => [
              if (calendarPhase != 'REGULAR' || chosen.isEmpty)
                title(roundLabel(r, calendarPhase)),
              ...visible
                  .where((m) => matchTurn(m, calendarPhase) == r)
                  .map(
                    (m) => MatchCard(
                      api: widget.api,
                      match: m,
                      phase: calendarPhase,
                    ),
                  ),
            ],
          ),
    ];
  }
}

class StandingsTable extends StatelessWidget {
  const StandingsTable({
    super.key,
    required this.api,
    required this.slug,
    required this.label,
    required this.teams,
    required this.data,
  });
  final TosApi api;
  final String slug, label;
  final List<Map<String, dynamic>> teams;
  final TournamentData data;
  static const columns = {
    'Pti': 'punti',
    'G': 'giocate',
    'V': 'vinte',
    'N': 'pareggiate',
    'P': 'perse',
    'GF': 'gol_fatti',
    'GS': 'gol_subiti',
    'DR': 'differenza_reti',
  };
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final count = data.grouped && teams.length < data.perGroup
        ? data.perGroup
        : teams.length;
    Widget cell(
      String value, {
      double width = 48,
      Color? color,
      Color? textColor,
      bool header = false,
      bool bold = false,
    }) => Container(
      width: width,
      height: header ? 44 : 62,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color ?? (header ? colors.primary : colors.surfaceContainerLow),
        border: Border(bottom: BorderSide(color: colors.outlineVariant)),
      ),
      child: Text(
        value,
        style: TextStyle(
          color: textColor ?? (header ? colors.onPrimary : colors.onSurface),
          fontWeight: header || bold ? FontWeight.bold : FontWeight.normal,
        ),
      ),
    );
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (data.grouped)
            Container(
              padding: const EdgeInsets.all(12),
              color: colors.surfaceContainerHigh,
              child: Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: colors.onSurface,
                  fontSize: 18,
                ),
              ),
            ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 172,
                child: Column(
                  children: [
                    Row(
                      children: [
                        cell('#', width: 32, header: true),
                        cell('Squadra', width: 140, header: true),
                      ],
                    ),
                    ...List.generate(count, (i) {
                      final team = i < teams.length ? teams[i] : null;
                      final cup = team == null
                          ? null
                          : data.cupForPosition(i + 1);
                      return Row(
                        children: [
                          cell(
                            '${i + 1}',
                            width: 32,
                            color: cupColors[cup],
                            textColor: cup == 'SPAREGGIO'
                                ? Colors.white
                                : cup == 'ELIMINATO'
                                ? const Color(0xff8b1e2d)
                                : cupColors.containsKey(cup)
                                ? siteBlue
                                : null,
                            bold: true,
                          ),
                          SizedBox(
                            width: 140,
                            height: 62,
                            child: InkWell(
                              onTap: team == null
                                  ? null
                                  : () => openPage(
                                      context,
                                      TeamPage(
                                        api: api,
                                        slug: slug,
                                        team: team,
                                      ),
                                    ),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 6,
                                ),
                                decoration: BoxDecoration(
                                  border: Border(
                                    bottom: BorderSide(
                                      color: colors.outlineVariant,
                                    ),
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    if (team != null)
                                      SiteImage(
                                        api: api,
                                        path: '${team['logo'] ?? ''}',
                                        width: 24,
                                        height: 24,
                                        fallback: Icons.shield_outlined,
                                        preserveShape: true,
                                      ),
                                    const SizedBox(width: 6),
                                    Expanded(
                                      child: Text(
                                        team == null
                                            ? 'Da definire'
                                            : '${team['nome']}',
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                          color: colors.onSurface,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    }),
                  ],
                ),
              ),
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Column(
                    children: [
                      Row(
                        children: columns.keys
                            .map((label) => cell(label, header: true))
                            .toList(),
                      ),
                      ...List.generate(
                        count,
                        (i) => Row(
                          children: columns.entries
                              .map(
                                (entry) => cell(
                                  i < teams.length
                                      ? '${teams[i][entry.value] ?? 0}'
                                      : '–',
                                  bold: entry.key == 'Pti',
                                ),
                              )
                              .toList(),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class MatchCard extends StatelessWidget {
  const MatchCard({
    super.key,
    required this.api,
    required this.match,
    required this.phase,
  });
  final TosApi api;
  final Map<String, dynamic> match;
  final String phase;
  @override
  Widget build(BuildContext context) {
    final played = stat(match['giocata']) == 1;
    final home = '${match['squadra_casa'] ?? 'Da definire'}';
    final away = '${match['squadra_ospite'] ?? 'Da definire'}';
    final colors = Theme.of(context).colorScheme;
    final phaseColor = cupColors[phase] ?? colors.primary;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      color: colors.surfaceContainerLow,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: match['id'] == null
            ? null
            : () => openPage(
                context,
                MatchPage(api: api, id: '${match['id']}', previewMatch: match),
              ),
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
              color: colors.surfaceContainerHigh,
              child: Row(
                children: [
                  Icon(Icons.event_rounded, size: 17, color: phaseColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${match['data_partita'] ?? 'Data da definire'} · ${match['ora_partita'] ?? ''}',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: colors.onSurface,
                      ),
                    ),
                  ),
                  if (matchLeg(match['fase_leg']).isNotEmpty)
                    _MatchBadge(
                      label: matchLeg(match['fase_leg']) == 'ANDATA'
                          ? 'ANDATA'
                          : 'RITORNO',
                      color: phaseColor,
                    ),
                  if (matchLeg(match['fase_leg']).isEmpty)
                    _MatchBadge(
                      label: played ? 'TERMINATA' : 'IN PROGRAMMA',
                      color: played ? const Color(0xff21835a) : colors.primary,
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 18, 14, 14),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: _MatchTeam(
                          api: api,
                          logo: '${match['logo_casa'] ?? ''}',
                          name: home,
                        ),
                      ),
                      Container(
                        constraints: const BoxConstraints(minWidth: 78),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 9,
                        ),
                        decoration: BoxDecoration(
                          color: played
                              ? colors.primary
                              : colors.surfaceContainerHigh,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Text(
                          played
                              ? '${match['gol_casa']}  :  ${match['gol_ospite']}'
                              : 'VS',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: played ? 21 : 17,
                            fontWeight: FontWeight.w900,
                            color: played ? colors.onPrimary : colors.onSurface,
                          ),
                        ),
                      ),
                      Expanded(
                        child: _MatchTeam(
                          api: api,
                          logo: '${match['logo_ospite'] ?? ''}',
                          name: away,
                        ),
                      ),
                    ],
                  ),
                  if (stat(match['decisa_rigori']) == 1) ...[
                    const SizedBox(height: 10),
                    Text(
                      'Rigori ${match['rigori_casa'] ?? '–'} : ${match['rigori_ospite'] ?? '–'}',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                  if ('${match['campo'] ?? ''}'.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.place_outlined,
                          size: 16,
                          color: colors.onSurfaceVariant,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${match['campo']}',
                          style: TextStyle(
                            fontSize: 12,
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 12),
                  Divider(height: 1, color: colors.outlineVariant),
                  const SizedBox(height: 9),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        'Dettaglio e referto',
                        style: TextStyle(
                          color: colors.primary,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(width: 3),
                      Icon(
                        Icons.arrow_forward_rounded,
                        color: colors.primary,
                        size: 16,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MatchBadge extends StatelessWidget {
  const _MatchBadge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .13),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 9,
        letterSpacing: .35,
        fontWeight: FontWeight.w900,
        color: color,
      ),
    ),
  );
}

class _MatchTeam extends StatelessWidget {
  const _MatchTeam({required this.api, required this.logo, required this.name});
  final TosApi api;
  final String logo;
  final String name;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 5),
    child: Column(
      children: [
        SiteImage(
          api: api,
          path: logo,
          width: 48,
          height: 48,
          fallback: Icons.shield_outlined,
          preserveShape: true,
        ),
        const SizedBox(height: 9),
        Text(
          name,
          textAlign: TextAlign.center,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            height: 1.15,
            color: Theme.of(context).colorScheme.onSurface,
          ),
        ),
      ],
    ),
  );
}

class TournamentScorers extends StatefulWidget {
  const TournamentScorers({
    super.key,
    required this.api,
    required this.players,
  });
  final TosApi api;
  final List<Map<String, dynamic>> players;
  @override
  State<TournamentScorers> createState() => _TournamentScorersState();
}

class _TournamentScorersState extends State<TournamentScorers> {
  int page = 0;
  @override
  void didUpdateWidget(covariant TournamentScorers oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.players != widget.players) page = 0;
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final ranks = <int>[];
    int? lastGoals;
    var rank = 0;
    for (var i = 0; i < widget.players.length; i++) {
      final goals = stat(widget.players[i]['gol']);
      if (lastGoals != goals) rank = i + 1;
      ranks.add(rank);
      lastGoals = goals;
    }
    const perPage = 15;
    final total = (widget.players.length / perPage).ceil();
    return Column(
      children: [
        if (widget.players.isEmpty) const Text('Nessun dato marcatori.'),
        if (widget.players.isNotEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Theme.of(context).brightness == Brightness.dark
                  ? colors.surfaceContainer
                  : siteBlue,
              borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.emoji_events_outlined,
                  size: 22,
                  color: Color(0xff7ee0cc),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'CLASSIFICA MARCATORI',
                    style: TextStyle(
                      color: Theme.of(context).brightness == Brightness.dark
                          ? colors.onSurface
                          : Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ...widget.players
            .skip(page * perPage)
            .take(perPage)
            .toList()
            .asMap()
            .entries
            .map((entry) {
              final player = entry.value;
              final position = ranks[page * perPage + entry.key];
              final podium = position <= 3;
              final medal = switch (position) {
                1 => const Color(0xffb68a2e),
                2 => const Color(0xff8795a6),
                3 => const Color(0xffb77a53),
                _ => colors.onSurfaceVariant,
              };
              return RankingSurface(
                index: page * perPage + entry.key,
                child: ListTile(
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  horizontalTitleGap: 10,
                  leading: SizedBox(
                    width: 82,
                    child: Row(
                      children: [
                        Container(
                          width: 28,
                          height: 32,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: podium
                                ? medal.withValues(alpha: .15)
                                : colors.surface,
                            borderRadius: BorderRadius.circular(9),
                            border: Border.all(
                              color: podium
                                  ? medal.withValues(alpha: .25)
                                  : colors.outlineVariant,
                            ),
                          ),
                          child: FittedBox(
                            child: Text(
                              '$position',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: medal,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        ClipOval(
                          child: SiteImage(
                            api: widget.api,
                            path: '${player['foto'] ?? ''}',
                            width: 40,
                            height: 40,
                            fallback: Icons.person_outline,
                          ),
                        ),
                      ],
                    ),
                  ),
                  title: Text(
                    '${player['nome']} ${player['cognome']}',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: colors.onSurface,
                    ),
                  ),
                  subtitle: Padding(
                    padding: const EdgeInsets.only(top: 5),
                    child: Text(
                      '${player['squadra'] ?? ''}',
                      style: TextStyle(
                        fontSize: 11,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ),
                  trailing: Semantics(
                    label: '${stat(player['gol'])} gol',
                    child: ExcludeSemantics(
                      child: Container(
                        width: 46,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: colors.primaryContainer.withValues(alpha: .5),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                '${stat(player['gol'])}',
                                style: TextStyle(
                                  fontSize: 25,
                                  height: 1,
                                  fontWeight: FontWeight.w800,
                                  color: colors.primary,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'GOL',
                                style: TextStyle(
                                  fontSize: 9,
                                  height: 1,
                                  letterSpacing: 1.2,
                                  color: colors.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  onTap: () => openPage(
                    context,
                    PlayerPage(
                      api: widget.api,
                      player: {...player, 'reti': player['gol']},
                    ),
                  ),
                ),
              );
            }),
        if (total > 1)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                tooltip: 'Marcatori precedenti',
                onPressed: page > 0 ? () => setState(() => page--) : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Text('Pagina ${page + 1} di $total'),
              IconButton(
                tooltip: 'Marcatori successivi',
                onPressed: page + 1 < total
                    ? () => setState(() => page++)
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
      ],
    );
  }
}

class TournamentCautionedPlayers extends StatelessWidget {
  const TournamentCautionedPlayers({super.key, required this.players});
  final List<Map<String, dynamic>> players;

  @override
  Widget build(BuildContext context) {
    if (players.isEmpty) return const Text('Nessun giocatore diffidato.');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          '${players.length} ${players.length == 1 ? 'giocatore' : 'giocatori'}',
        ),
        const SizedBox(height: 8),
        for (final player in players)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(
                        Icons.warning_amber_rounded,
                        color: Color(0xffa86b00),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          '${player['cognome'] ?? ''} ${player['nome'] ?? ''}'
                              .trim(),
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.onSurface,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('${player['squadra'] ?? ''}'),
                  const SizedBox(height: 12),
                  const Text('Turni ammonizioni'),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final label
                          in (player['ammonizioni'] as List? ??
                              (player['giornate'] as List? ?? [])
                                  .map(
                                    (day) => day == null
                                        ? 'Giornata non indicata'
                                        : 'Giornata $day',
                                  )
                                  .toList()))
                        Chip(
                          avatar: Container(
                            width: 10,
                            height: 15,
                            decoration: BoxDecoration(
                              color: const Color(0xffffd43b),
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                          label: Text('$label'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
