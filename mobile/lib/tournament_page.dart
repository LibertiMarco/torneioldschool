import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart';
import 'home_dashboard.dart' show SiteImage, HomeBlock, openPage;
import 'tournament_rules.dart';
import 'main.dart' show ErrorPanel;
import 'theme.dart';
import 'tournament_logic.dart';

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

InputDecoration tournamentSelectorDecoration(String hint, IconData icon) =>
    InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: Colors.white,
      prefixIcon: Icon(icon, size: 20, color: siteBlue),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xffe5eaf1)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xffe5eaf1)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: siteBlue, width: 1.5),
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
      style: const TextStyle(
        fontSize: 22,
        fontWeight: FontWeight.bold,
        color: siteBlue,
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
    icon: const Icon(Icons.expand_more_rounded, color: siteBlue),
    borderRadius: BorderRadius.circular(18),
    dropdownColor: Colors.white,
    style: Theme.of(context).textTheme.bodyMedium!
        .copyWith(fontWeight: FontWeight.w600),
    decoration: tournamentSelectorDecoration(
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
          if (!esport) 'Marcatori',
          'Calendario',
          'Rose',
          'Regole',
        ];
        if (!tabs.contains(tab)) tab = 'Classifica';
        if (!result.phases.contains(tablePhase)) tablePhase = 'REGULAR';
        if (!result.phases.contains(calendarPhase)) {
          calendarPhase = 'REGULAR';
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
                  side: const BorderSide(color: Color(0xffdbe3f0)),
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
                              style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: siteBlue,
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
              LayoutBuilder(
                builder: (context, constraints) {
                  final width = (constraints.maxWidth - 10) / 2;
                  const icons = {
                    'Classifica': Icons.leaderboard_outlined,
                    'Marcatori': Icons.sports_soccer_rounded,
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
                                      ? siteBlue
                                      : const Color(0xffe9edf3),
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
                                                ? Colors.white
                                                : siteBlue,
                                          ),
                                          const SizedBox(width: 10),
                                          Flexible(
                                            child: Text(
                                              label,
                                              style: TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w600,
                                                color: tab == label
                                                    ? Colors.white
                                                    : siteBlue,
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
                            TeamPage(api: widget.api, slug: slug, team: team),
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
    final rounds = rows.map((r) => matchRound(r, phase)).toSet().toList()
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
              .where((m) => matchRound(m, phase) == r)
              .map((m) => MatchCard(api: widget.api, match: m, phase: phase)),
        ],
      ),
    ];
  }

  List<Widget> calendar(TournamentData result) {
    final rows = result.matches
        .where((m) => phaseKey(m['fase']) == calendarPhase)
        .toList();
    final rounds =
        rows.map((m) => matchRound(m, calendarPhase)).toSet().toList()
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
        .where((m) => chosen.isEmpty || matchRound(m, calendarPhase) == chosen)
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
        icon: const Icon(Icons.expand_more_rounded, color: siteBlue),
        borderRadius: BorderRadius.circular(18),
        dropdownColor: Colors.white,
        style: Theme.of(context).textTheme.bodyMedium!
            .copyWith(fontWeight: FontWeight.w600),
        decoration: tournamentSelectorDecoration(
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
      ...visible.map(
        (m) => MatchCard(api: widget.api, match: m, phase: calendarPhase),
      ),
    ];
  }
}

String roundLabel(String round, String phase) {
  if (phase == 'REGULAR' || phase == 'SPAREGGIO') {
    return round == '0' ? 'Giornata da definire' : 'Giornata $round';
  }
  const labels = {
    '1': 'Finale',
    '2': 'Semifinale',
    '3': 'Quarti di finale',
    '4': 'Ottavi di finale',
    '5': 'Sedicesimi di finale',
    '6': 'Trentaduesimi di finale',
    'KO': 'Fase eliminazione',
    'FINALE': 'Finale',
    'SEMIFINALE': 'Semifinale',
    'QUARTI': 'Quarti di finale',
    'OTTAVI': 'Ottavi di finale',
    'SEDICESIMI': 'Sedicesimi di finale',
    'TRENTADUESIMI': 'Trentaduesimi di finale',
  };
  return labels[round] ?? round;
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
        color: color ?? (header ? siteBlue : Colors.white),
        border: const Border(bottom: BorderSide(color: Color(0xffdbe3f0))),
      ),
      child: Text(
        value,
        style: TextStyle(
          color: textColor ?? (header ? Colors.white : siteBlue),
          fontWeight: header || bold ? FontWeight.bold : FontWeight.normal,
        ),
      ),
    );
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Color(0xffdbe3f0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (data.grouped)
            Container(
              padding: const EdgeInsets.all(12),
              color: const Color(0xffe8edf5),
              child: Text(
                label,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: siteBlue,
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
                                decoration: const BoxDecoration(
                                  border: Border(
                                    bottom: BorderSide(
                                      color: Color(0xffdbe3f0),
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
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                          color: siteBlue,
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
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: cupColors[phase] ?? const Color(0xffdbe3f0),
          width: 2,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: match['id'] == null
            ? null
            : () => openPage(
                context,
                MatchPage(api: api, id: '${match['id']}', previewMatch: match),
              ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Text(
                '${match['data_partita'] ?? 'Data da definire'} · ${match['ora_partita'] ?? ''}',
                style: const TextStyle(fontSize: 12, color: Color(0xff526176)),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      children: [
                        SiteImage(
                          api: api,
                          path: '${match['logo_casa'] ?? ''}',
                          width: 40,
                          height: 40,
                          fallback: Icons.shield_outlined,
                          preserveShape: true,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          home,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: siteBlue,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: Text(
                      played
                          ? '${match['gol_casa']} : ${match['gol_ospite']}'
                          : 'VS',
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: siteBlue,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Column(
                      children: [
                        SiteImage(
                          api: api,
                          path: '${match['logo_ospite'] ?? ''}',
                          width: 40,
                          height: 40,
                          fallback: Icons.shield_outlined,
                          preserveShape: true,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          away,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: siteBlue,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              if (stat(match['decisa_rigori']) == 1)
                Text(
                  'Rigori ${match['rigori_casa'] ?? '–'} : ${match['rigori_ospite'] ?? '–'}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              if (stat(match['fase_leg']) > 0)
                Text(stat(match['fase_leg']) == 1 ? 'Andata' : 'Ritorno'),
              if (!played)
                const Text('Da giocare', style: TextStyle(color: siteRed)),
              if ('${match['campo'] ?? ''}'.isNotEmpty)
                Text('${match['campo']}', style: const TextStyle(fontSize: 12)),
              const SizedBox(height: 6),
              const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Dettaglio e referto',
                    style: TextStyle(
                      color: siteRed,
                      fontWeight: FontWeight.bold,
                      fontSize: 12,
                    ),
                  ),
                  Icon(Icons.chevron_right, color: siteRed, size: 18),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
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
  Widget build(BuildContext context) {
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
        ...widget.players
            .skip(page * perPage)
            .take(perPage)
            .toList()
            .asMap()
            .entries
            .map((entry) {
              final player = entry.value;
              return Card(
                child: ListTile(
                  leading: SizedBox(
                    width: 74,
                    child: Row(
                      children: [
                        Text(
                          '${ranks[page * perPage + entry.key]}',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(width: 8),
                        SiteImage(
                          api: widget.api,
                          path: '${player['foto'] ?? ''}',
                          width: 40,
                          height: 40,
                          fallback: Icons.person_outline,
                        ),
                      ],
                    ),
                  ),
                  title: Text(
                    '${player['nome']} ${player['cognome']}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Text('${player['squadra'] ?? ''}'),
                  trailing: Text(
                    '${player['gol']} gol',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: siteRed,
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
