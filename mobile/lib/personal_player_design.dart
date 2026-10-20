import 'package:flutter/material.dart';

import 'api.dart';
import 'home_dashboard.dart' show SiteImage, openPage;
import 'details.dart' show MatchPage;
import 'theme.dart';
import 'player_awards.dart';

class PlayerDashboard extends StatefulWidget {
  const PlayerDashboard({
    super.key,
    required this.api,
    required this.player,
    required this.teams,
    required this.section,
    required this.refresh,
    required this.history,
    required this.openTeam,
    this.awards = const [],
    this.upcomingMatches = const [],
  });
  final TosApi api;
  final Map<String, dynamic> player;
  final List<Map<String, dynamic>> teams;
  final List<Map<String, dynamic>> awards;
  final List<Map<String, dynamic>> upcomingMatches;
  final String section;
  final Future<void> Function() refresh;
  final void Function(String) history;
  final void Function(Map<String, dynamic>) openTeam;

  @override
  State<PlayerDashboard> createState() => _PlayerDashboardState();
}

class _PlayerDashboardState extends State<PlayerDashboard> {
  bool appearances = false;
  ColorScheme get colors => Theme.of(context).colorScheme;
  bool get dark => Theme.of(context).brightness == Brightness.dark;
  Color get panel => dark ? colors.surfaceContainer : siteBlue;
  num number(dynamic value) => num.tryParse('$value') ?? 0;
  String value(dynamic value) =>
      value == null || '$value'.isEmpty ? '—' : '$value';
  Duration get motion => MediaQuery.disableAnimationsOf(context)
      ? Duration.zero
      : const Duration(milliseconds: 360);

  @override
  Widget build(BuildContext context) => widget.section == 'esport'
      ? _esport()
      : RefreshIndicator(
          onRefresh: widget.refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
            children: [
              TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: 1),
                duration: motion,
                curve: Curves.easeOutCubic,
                builder: (context, progress, child) => Opacity(
                  opacity: progress,
                  child: Transform.translate(
                    offset: Offset(0, 12 * (1 - progress)),
                    child: child,
                  ),
                ),
                child: _athlete(),
              ),
              const SizedBox(height: 24),
              _upcomingMatches(),
              const SizedBox(height: 24),
              _title('Le mie statistiche', 'IL TUO PERCORSO'),
              const SizedBox(height: 12),
              _summary(),
              if (widget.player['rankings'] is Map) ...[
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Classifiche generali · tutti i tempi',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 8),
                        for (final entry in {
                          'gol': 'Marcatori',
                          'presenze': 'Presenze',
                        }.entries)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(
                              children: [
                                Expanded(child: Text(entry.value)),
                                Text(
                                  widget.player['rankings'][entry.key] == null
                                      ? 'Non in classifica'
                                      : '${widget.player['rankings'][entry.key]}°',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              _discipline(),
              const SizedBox(height: 22),
              _history(),
              const SizedBox(height: 28),
              PlayerAwardsSection(api: widget.api, awards: widget.awards),
              if (widget.teams.isNotEmpty) ...[
                const SizedBox(height: 26),
                _chart(),
              ],
              const SizedBox(height: 28),
              _title('Le mie squadre', 'APPARTENENZA'),
              const SizedBox(height: 16),
              if (widget.teams.isEmpty)
                Text('Non risultano squadre associate in questa sezione.'),
              for (var index = 0; index < widget.teams.length; index++)
                _team(widget.teams[index], index),
            ],
          ),
        );

  Widget _upcomingMatches() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      _title('Le tue prossime partite', 'IL TUO CALENDARIO'),
      const SizedBox(height: 12),
      if (widget.upcomingMatches.isEmpty)
        const Text('Non ci sono partite da disputare per le tue squadre.'),
      for (final match in widget.upcomingMatches)
        Card(
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => openPage(
              context,
              MatchPage(
                api: widget.api,
                id: '${match['id']}',
                previewMatch: match,
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _upcomingTeam(
                    '${match['squadra_casa']}',
                    '${match['logo_casa'] ?? ''}',
                  ),
                  const SizedBox(height: 10),
                  _upcomingTeam(
                    '${match['squadra_ospite']}',
                    '${match['logo_ospite'] ?? ''}',
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          [
                            '${match['torneo_nome'] ?? match['torneo'] ?? ''}',
                            _matchSchedule(match),
                          ].where((part) => part.isNotEmpty).join('\n'),
                          style: TextStyle(
                            color: colors.onSurfaceVariant,
                            height: 1.4,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      const Icon(Icons.chevron_right),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
    ],
  );

  Widget _upcomingTeam(String name, String logo) => Row(
    children: [
      Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          color: colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(12),
        ),
        child: SiteImage(
          api: widget.api,
          path: logo,
          width: 34,
          height: 34,
          fallback: Icons.shield_outlined,
          preserveShape: true,
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Text(name, style: const TextStyle(fontWeight: FontWeight.w700)),
      ),
    ],
  );

  String _matchSchedule(Map<String, dynamic> match) {
    final date = DateTime.tryParse('${match['data_partita']}');
    final time = '${match['ora_partita'] ?? ''}';
    return [
      if (date != null)
        '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}'
      else
        'Data da definire',
      if (time.isNotEmpty) time.length >= 5 ? time.substring(0, 5) : time,
    ].join(' · ');
  }

  Widget _esport() {
    final tournaments = <String, Map<String, dynamic>>{};
    for (final team in widget.teams) {
      tournaments.putIfAbsent('${team['torneo']}', () => team);
    }
    return RefreshIndicator(
      onRefresh: widget.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Text(
            '${widget.player['nome'] ?? ''} ${widget.player['cognome'] ?? ''}'
                .trim(),
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 24),
          _title('I miei tornei', 'ESPORT'),
          const SizedBox(height: 12),
          if (tournaments.isEmpty)
            const Text('Non risultano tornei associati al tuo account.'),
          for (final team in tournaments.values)
            Card(
              child: ListTile(
                leading: Icon(Icons.sports_esports, color: colors.primary),
                title: Text('${team['torneo_nome'] ?? team['torneo']}'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => widget.openTeam(team),
              ),
            ),
          const SizedBox(height: 28),
          PlayerAwardsSection(api: widget.api, awards: widget.awards),
        ],
      ),
    );
  }

  Widget _title(String title, String eyebrow) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        eyebrow,
        style: TextStyle(
          color: colors.secondary,
          fontSize: 10,
          letterSpacing: 2,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 5),
      Text(
        title,
        style: TextStyle(
          color: colors.onSurface,
          fontSize: 25,
          fontWeight: FontWeight.w900,
          letterSpacing: -.7,
        ),
      ),
    ],
  );

  Widget _athlete() => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      color: panel,
      borderRadius: BorderRadius.circular(24),
      boxShadow: [
        BoxShadow(
          color: siteBlue.withValues(alpha: .18),
          blurRadius: 22,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Stack(
      children: [
        const Positioned.fill(child: CustomPaint(painter: _PitchArtwork())),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 6,
                    height: 6,
                    decoration: BoxDecoration(
                      color: colors.secondary,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'OLD SCHOOL',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        letterSpacing: 2.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Text(
                    widget.section == 'calcio' ? 'CALCIO' : 'ESPORT',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 9,
                      letterSpacing: 1.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 26),
              LayoutBuilder(
                builder: (context, constraints) {
                  final largeText =
                      MediaQuery.textScalerOf(context).scale(16) > 24;
                  final portrait = _portrait();
                  final identity = Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'LA MIA SCHEDA',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 9,
                          letterSpacing: 1.6,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        '${widget.player['nome'] ?? ''} ${widget.player['cognome'] ?? ''}'
                            .trim(),
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 30,
                          fontWeight: FontWeight.w900,
                          height: 1.05,
                          letterSpacing: -.8,
                        ),
                      ),
                      const SizedBox(height: 14),
                      if ('${widget.player['ruolo'] ?? ''}'.trim().isNotEmpty)
                        Text(
                          '${widget.player['ruolo']}'.toUpperCase(),
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1,
                          ),
                        ),
                    ],
                  );
                  if (largeText) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        portrait,
                        const SizedBox(height: 18),
                        identity,
                      ],
                    );
                  }
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(child: identity),
                      const SizedBox(width: 14),
                      portrait,
                    ],
                  );
                },
              ),
              const SizedBox(height: 24),
              const Divider(height: 1, color: Colors.white24),
              LayoutBuilder(
                builder: (context, constraints) {
                  final narrow =
                      MediaQuery.textScalerOf(context).scale(16) > 24;
                  final children = [
                    _heroStat('Gol', widget.player['reti'], 'gol'),
                    _heroStat(
                      'Presenze',
                      widget.player['presenze'],
                      'presenze',
                    ),
                  ];
                  return narrow
                      ? Column(children: children)
                      : Row(
                          children: [
                            Expanded(child: children[0]),
                            Container(
                              height: 46,
                              width: 1,
                              color: Colors.white24,
                            ),
                            Expanded(child: children[1]),
                          ],
                        );
                },
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _heroStat(String label, dynamic count, String type) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: () => widget.history(type),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value(count),
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 46,
                      fontWeight: FontWeight.w900,
                      height: 1,
                      letterSpacing: -1.5,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    label,
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.only(bottom: 3),
              child: Icon(Icons.north_east, size: 17, color: Colors.white70),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _portrait() {
    final name =
        '${widget.player['nome'] ?? ''} ${widget.player['cognome'] ?? ''}'
            .trim();
    final initials = name
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part.characters.first.toUpperCase())
        .join();
    final path = '${widget.player['foto'] ?? ''}'.trim();
    final uri = widget.api.baseUrl.resolve(path);
    final placeholder = Center(
      child: Text(
        initials,
        style: TextStyle(
          color: Colors.white,
          fontSize: 38,
          fontWeight: FontWeight.w900,
          letterSpacing: -2,
        ),
      ),
    );
    return Container(
      width: 104,
      height: 142,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.white24),
        borderRadius: BorderRadius.circular(14),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xff42627c), Color(0xff223c54)],
                ),
              ),
            ),
            if (path.isNotEmpty &&
                !path.toLowerCase().contains('unknown.jpg') &&
                uri.scheme == 'https' &&
                uri.userInfo.isEmpty)
              Image.network(
                uri.toString(),
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => placeholder,
              )
            else
              placeholder,
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: SizedBox(
                height: 3,
                child: ColoredBox(color: colors.secondary),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summary() {
    final items = <(String, dynamic)>[
      if (number(widget.player['media_voti']) > 0)
        ('Media voto', widget.player['media_voti']),
      ('Squadre', widget.teams.length),
    ];
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 18),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final wideText = MediaQuery.textScalerOf(context).scale(16) > 24;
          final width = wideText
              ? constraints.maxWidth
              : constraints.maxWidth / items.length;
          return Wrap(
            runSpacing: 18,
            children: [
              for (final item in items)
                SizedBox(
                  width: width,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        value(item.$2),
                        style: TextStyle(
                          color: colors.onSurface,
                          fontWeight: FontWeight.w900,
                          fontSize: 28,
                          height: 1,
                          letterSpacing: -.8,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        item.$1,
                        style: TextStyle(
                          color: colors.onSurfaceVariant,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  Widget _discipline() => Wrap(
    spacing: 16,
    runSpacing: 10,
    children: [
      if (widget.player['gialli'] != null)
        _event(
          'Ammonizioni: ${widget.player['gialli']}',
          const Color(0xffffc83d),
        ),
      if (widget.player['rossi'] != null)
        _event('Espulsioni: ${widget.player['rossi']}', siteRed),
    ],
  );

  Widget _event(String label, Color color) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 8,
        height: 11,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
      const SizedBox(width: 7),
      Text(
        label,
        style: TextStyle(color: colors.onSurfaceVariant, fontSize: 11),
      ),
    ],
  );

  Widget _history() => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: () => widget.history('presenze'),
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: colors.secondary.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(Icons.history, color: colors.secondary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Storico prestazioni',
                    style: TextStyle(
                      color: colors.onSurface,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  SizedBox(height: 3),
                  Text(
                    'Ogni partita racconta la tua storia',
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(Icons.arrow_forward, color: colors.onSurface, size: 20),
          ],
        ),
      ),
    ),
  );

  Widget _chart() {
    final field = appearances ? 'presenze' : 'reti';
    final playedTeams =
        widget.teams.where((team) => number(team['presenze']) > 0).toList()
          ..sort((a, b) => number(b[field]).compareTo(number(a[field])));
    final max = playedTeams.fold<num>(
      0,
      (a, team) => number(team[field]) > a ? number(team[field]) : a,
    );
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: panel,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'IL TUO IMPATTO',
            style: TextStyle(
              color: Colors.white70,
              fontSize: 9,
              letterSpacing: 2,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            appearances ? 'Presenze per squadra' : 'Gol per squadra',
            style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w800,
              letterSpacing: -.5,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _chartFilter('Gol', false),
              _chartFilter('Presenze', true),
            ],
          ),
          const SizedBox(height: 24),
          for (final team in playedTeams)
            Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: Semantics(
                label:
                    '${team['nome']}, ${team['torneo_nome'] ?? team['torneo']}: ${value(team[field])} ${appearances ? 'presenze' : 'gol'}',
                excludeSemantics: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${team['nome']}',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                '${team['torneo_nome'] ?? team['torneo'] ?? ''}',
                                style: TextStyle(
                                  color: Colors.white70,
                                  fontSize: 10,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          value(team[field]),
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 26,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    TweenAnimationBuilder<double>(
                      tween: Tween(
                        begin: 0,
                        end: max > 0
                            ? (number(team[field]) / max).clamp(0, 1).toDouble()
                            : 0,
                      ),
                      duration: motion,
                      curve: Curves.easeOutCubic,
                      builder: (context, progress, _) => SizedBox(
                        height: 14,
                        child: Stack(
                          children: [
                            Positioned.fill(
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: .08),
                                  borderRadius: BorderRadius.circular(3),
                                ),
                              ),
                            ),
                            FractionallySizedBox(
                              widthFactor: progress,
                              heightFactor: 1,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(3),
                                  gradient: LinearGradient(
                                    colors: dark
                                        ? [
                                            colors.secondaryContainer,
                                            colors.secondary,
                                          ]
                                        : [const Color(0xffa80000), siteRed],
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          Text(
            'Confronto tra le tue squadre · dati dei tornei',
            style: TextStyle(color: Colors.white70, fontSize: 10),
          ),
        ],
      ),
    );
  }

  Widget _chartFilter(String label, bool selected) => ChoiceChip(
    label: Text(label),
    selected: appearances == selected,
    onSelected: (_) => setState(() => appearances = selected),
    showCheckmark: false,
    selectedColor: dark ? colors.primaryContainer : Colors.white,
    backgroundColor: dark
        ? colors.surfaceContainerHigh
        : const Color(0xff2e4154),
    color: WidgetStateProperty.resolveWith(
      (states) => states.contains(WidgetState.selected)
          ? (dark ? colors.primaryContainer : Colors.white)
          : (dark ? colors.surfaceContainerHigh : const Color(0xff2e4154)),
    ),
    side: BorderSide.none,
    labelStyle: TextStyle(
      color: dark
          ? (appearances == selected
                ? colors.onPrimaryContainer
                : colors.onSurface)
          : (appearances == selected ? siteBlue : Colors.white),
      fontWeight: FontWeight.w700,
      fontSize: 12,
    ),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
  );

  Widget _team(Map<String, dynamic> team, int index) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    decoration: BoxDecoration(
      border: Border(left: BorderSide(color: colors.outlineVariant, width: 2)),
    ),
    child: Padding(
      padding: const EdgeInsets.only(left: 14),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  padding: const EdgeInsets.all(5),
                  decoration: BoxDecoration(
                    color: colors.surfaceContainerHigh,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: SiteImage(
                    api: widget.api,
                    path: '${team['logo'] ?? ''}',
                    width: 34,
                    height: 34,
                    preserveShape: true,
                    fallback: Icons.shield_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${team['nome'] ?? ''}',
                        style: TextStyle(
                          fontSize: 16,
                          color: colors.onSurface,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${team['torneo_nome'] ?? team['torneo'] ?? ''}',
                        style: TextStyle(
                          fontSize: 11,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 16,
              runSpacing: 8,
              children: [
                Text(
                  'Gol: ${value(team['reti'])}',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                ),
                Text(
                  'Presenze: ${value(team['presenze'])}',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                ),
                if (number(team['media_voti']) > 0)
                  Text(
                    'Media voto: ${team['media_voti']}',
                    style: TextStyle(fontSize: 12),
                  ),
                if (number(team['gialli']) > 0)
                  _event(
                    'Ammonizioni: ${team['gialli']}',
                    const Color(0xffffc83d),
                  ),
                if (number(team['rossi']) > 0)
                  _event('Espulsioni: ${team['rossi']}', siteRed),
              ],
            ),
            if (team['diffidato'] == true ||
                '${team['is_captain']}' == '1') ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if ('${team['is_captain']}' == '1') _tag('Capitano'),
                  if (team['diffidato'] == true) _tag('Diffidato'),
                ],
              ),
            ],
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () => widget.openTeam(team),
              icon: Icon(Icons.north_east, size: 16),
              label: Text('Vedi squadra'),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _tag(String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: colors.primaryContainer,
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: colors.onSurface,
        fontSize: 10,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _PitchArtwork extends CustomPainter {
  const _PitchArtwork();
  @override
  void paint(Canvas canvas, Size size) {
    final stripe = Paint()..color = Colors.white.withValues(alpha: .035);
    canvas.drawPath(
      Path()
        ..moveTo(size.width * .62, 0)
        ..lineTo(size.width, 0)
        ..lineTo(size.width * .38, size.height)
        ..lineTo(0, size.height)
        ..close(),
      stripe,
    );
    final lines = Paint()
      ..color = Colors.white.withValues(alpha: .08)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    final center = Offset(size.width * .83, size.height * .3);
    canvas.drawCircle(center, 74, lines);
    canvas.drawCircle(center, 3, lines);
    canvas.drawLine(
      Offset(center.dx, 0),
      Offset(center.dx, size.height),
      lines,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(size.width * .54, 18, size.width * .55, size.height - 36),
        const Radius.circular(4),
      ),
      lines,
    );
  }

  @override
  bool shouldRepaint(covariant _PitchArtwork oldDelegate) => false;
}
