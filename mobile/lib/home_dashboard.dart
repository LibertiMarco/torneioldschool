import 'package:flutter/material.dart';
import 'package:html/dom.dart' as dom;
import 'package:html/parser.dart' show parseFragment;

import 'api.dart';
import 'about_page.dart';
import 'article_content.dart';
import 'player_history.dart';
import 'ranking_mode_selector.dart';
import 'ranking_surface.dart';
import 'details.dart' show DataPage;
import 'main.dart' show ErrorPanel, TournamentPage;
import 'theme.dart' show siteBlue, siteRed;
import 'home_design.dart';

class SiteImage extends StatelessWidget {
  const SiteImage({
    super.key,
    required this.api,
    required this.path,
    this.width = 56,
    this.height = 56,
    this.fallback = Icons.image_outlined,
    this.preserveShape = false,
  });
  final TosApi api;
  final String path;
  final double width, height;
  final IconData fallback;
  final bool preserveShape;
  @override
  Widget build(BuildContext context) {
    final parsed = Uri.tryParse(path.trim());
    final uri = parsed == null ? null : api.baseUrl.resolveUri(parsed);
    Widget placeholder() => SizedBox(
      width: width,
      height: height,
      child: preserveShape
          ? Icon(
              fallback,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            )
          : ColoredBox(
              color: Theme.of(context).colorScheme.surfaceContainerHigh,
              child: Icon(
                fallback,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
    );
    if (path.trim().isEmpty ||
        uri == null ||
        uri.scheme != 'https' ||
        uri.userInfo.isNotEmpty) {
      return placeholder();
    }
    final picture = Image.network(
      uri.toString(),
      width: width,
      height: height,
      fit: preserveShape ? BoxFit.contain : BoxFit.cover,
      errorBuilder: (_, _, _) => placeholder(),
    );
    return preserveShape
        ? picture
        : ClipRRect(borderRadius: BorderRadius.circular(8), child: picture);
  }
}

// Each homepage block handles its own loading and errors.
class HomeBlock extends StatefulWidget {
  const HomeBlock({super.key, required this.load, required this.render});
  final Future<dynamic> Function() load;
  final Widget Function(dynamic) render;
  @override
  State<HomeBlock> createState() => _HomeBlockState();
}

class _HomeBlockState extends State<HomeBlock> {
  late Future<dynamic> data;
  @override
  void initState() {
    super.initState();
    data = widget.load();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<dynamic>(
    future: data,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Padding(
          padding: EdgeInsets.all(24),
          child: Center(child: CircularProgressIndicator()),
        );
      }
      if (snapshot.hasError) {
        return ErrorPanel(
          error: snapshot.error!,
          retry: () => setState(() {
            data = widget.load();
          }),
        );
      }
      return widget.render(snapshot.data);
    },
  );
}

void openPage(BuildContext context, Widget page) =>
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));

class HomeDashboard extends StatefulWidget {
  const HomeDashboard({
    super.key,
    required this.api,
    required this.section,
    required this.onTournaments,
  });
  final TosApi api;
  final String section;
  final VoidCallback onTournaments;
  @override
  State<HomeDashboard> createState() => _HomeDashboardState();
}

class _HomeDashboardState extends State<HomeDashboard> {
  String order = 'gol';
  int reload = 0;

  void _ranking(Map<String, dynamic> row, bool esport) {
    if (!esport) {
      openPage(
        context,
        PlayerHistoryPage(api: widget.api, player: row, initialType: order),
      );
      return;
    }
    openPage(
      context,
      Scaffold(
        appBar: AppBar(
          title: Text('${row['nome'] ?? ''} ${row['cognome'] ?? ''}'.trim()),
        ),
        body: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const Text(
              'Statistiche EA FC totali',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
            ),
            for (final entry in {
              'punti': 'Punti totali',
              'tornei_giocati': 'Tornei giocati',
              'punti_partecipazione': 'Partecipazione',
              'punti_gironi': 'Gironi',
              'bonus_gironi': 'Bonus gironi',
              'punti_gold': 'Coppa Gold',
              'punti_silver': 'Coppa Silver',
              'best_result': 'Miglior risultato',
            }.entries)
              ListTile(
                title: Text(entry.value),
                trailing: Text('${row[entry.key] ?? '–'}'),
              ),
          ],
        ),
      ),
    );
  }

  Widget _link(String label, VoidCallback action) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: TextButton(
      onPressed: action,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(width: 12),
          const Icon(Icons.arrow_forward, size: 18),
        ],
      ),
    ),
  );

  Widget _rankingSelector() => LayoutBuilder(
    builder: (context, constraints) {
      Widget option(String mode, String label) => Expanded(
        child: TextButton(
          onPressed: () => setState(() => order = mode),
          style: TextButton.styleFrom(
            backgroundColor: order == mode
                ? Theme.of(context).colorScheme.primary
                : Colors.transparent,
            foregroundColor: order == mode
                ? Theme.of(context).colorScheme.onPrimary
                : Theme.of(context).colorScheme.onSurface,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          child: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
          ),
        ),
      );
      return Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHigh,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            option('gol', 'Marcatori'),
            option('presenze', 'Presenze'),
          ],
        ),
      );
    },
  );

  @override
  Widget build(BuildContext context) {
    final api = widget.api;
    final esport = widget.section == 'esport';
    return RefreshIndicator(
      onRefresh: () async {
        setState(() => reload++);
      },
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 12),
          const Text(
            'LA COMMUNITY IN CAMPO',
            style: TextStyle(
              color: siteRed,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            esport ? 'Ranking EA FC' : 'Classifica giocatori totale',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurface,
              fontSize: 30,
              fontWeight: FontWeight.w900,
              height: 1.08,
              letterSpacing: -1,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'I protagonisti di Old School',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 20),
          if (!esport) ...[_rankingSelector(), const SizedBox(height: 14)],
          HomeBlock(
            key: ValueKey('ranking-${widget.section}-$reload-$order'),
            load: () =>
                esport ? api.esportRanking() : api.playerRanking(order: order),
            render: (value) {
              final result = Map<String, dynamic>.from(value as Map);
              final rows = TosApi.rows(result['data']);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: Column(
                      children: [
                        if (rows.isEmpty)
                          const Padding(
                            padding: EdgeInsets.all(20),
                            child: Text('Nessun giocatore disponibile.'),
                          ),
                        for (final entry in rows.asMap().entries)
                          HomeRankingEntry(
                            index: entry.key,
                            api: api,
                            row: entry.value,
                            esport: esport,
                            order: order,
                            open: () => _ranking(entry.value, esport),
                          ),
                      ],
                    ),
                  ),
                  if (!esport ||
                      (result['meta'] as Map?)?['can_view_full'] == true)
                    _link(
                      'Classifica completa',
                      () => openPage(
                        context,
                        GlobalRankingPage(
                          api: api,
                          esport: esport,
                          initialOrder: order,
                        ),
                      ),
                    ),
                  if (esport &&
                      (result['meta'] as Map?)?['has_more'] == true &&
                      (result['meta'] as Map?)?['can_view_full'] != true)
                    const Padding(
                      padding: EdgeInsets.all(12),
                      child: Text('Accedi per consultare il ranking completo.'),
                    ),
                ],
              );
            },
          ),
          const HomeSectionTitle(
            title: 'Ultime notizie',
            eyebrow: 'DENTRO OLD SCHOOL',
          ),
          HomeBlock(
            key: ValueKey('news-${widget.section}-$reload'),
            load: () => api.news(widget.section),
            render: (value) {
              final rows = TosApi.rows(value);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (rows.isEmpty) const Text('Nessuna notizia disponibile.'),
                  for (var i = 0; i < rows.length && i < 4; i++)
                    NewsCard(api: api, row: rows[i], featured: i == 0),
                  _link(
                    'Tutte le notizie',
                    () => openPage(
                      context,
                      DataPage(
                        title: 'Notizie',
                        load: () => api.news(widget.section, all: true),
                        render: (data) => ListView(
                          padding: const EdgeInsets.all(20),
                          children: TosApi.rows(data)
                              .map((r) => NewsCard(api: api, row: r))
                              .toList(),
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
          const HomeSectionTitle(
            title: 'Albo d’oro',
            eyebrow: 'CHI HA LASCIATO IL SEGNO',
          ),
          HomeBlock(
            key: ValueKey('hall-${widget.section}-$reload'),
            load: () => api.hallOfFame(widget.section),
            render: (value) => Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                HallOfFamePanel(api: api, rows: TosApi.rows(value)),
                _link(
                  'Albo d’oro completo',
                  () => openPage(
                    context,
                    DataPage(
                      title: 'Albo d’oro',
                      load: () => api.hallOfFame(widget.section),
                      render: (data) => ListView(
                        padding: const EdgeInsets.all(20),
                        children: [
                          HallOfFamePanel(api: api, rows: TosApi.rows(data)),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 28),
          Material(
            color: siteBlue,
            borderRadius: BorderRadius.circular(20),
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'PIÙ DI UN TORNEO',
                    style: TextStyle(
                      color: Colors.white70,
                      letterSpacing: 2,
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Chi siamo',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 27,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.7,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Calcio, amicizia e passione. La community Old School, a Napoli e provincia.',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 13,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextButton(
                    onPressed: () => openPage(context, AboutPage(api: api)),
                    style: TextButton.styleFrom(
                      foregroundColor: Colors.white,
                      padding: EdgeInsets.zero,
                    ),
                    child: const Row(
                      children: [
                        Expanded(child: Text('Scopri chi siamo')),
                        Icon(Icons.north_east, size: 18),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 28),
          Material(
            color: siteBlue,
            borderRadius: BorderRadius.circular(20),
            clipBehavior: Clip.antiAlias,
            child: const Padding(
              padding: EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'RESTIAMO IN CONTATTO',
                    style: TextStyle(
                      color: Colors.white70,
                      letterSpacing: 2,
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  SizedBox(height: 12),
                  Text(
                    'Contattaci',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 27,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.7,
                    ),
                  ),
                  SizedBox(height: 8),
                  SelectableText(
                    'Email: info@torneioldschool.it\nSponsorizzazioni: sponsor@torneioldschool.it\nWhatsApp: +39 338 321 3272\nNapoli e provincia',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 13,
                      height: 1.9,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class PlayerRankingCard extends StatelessWidget {
  const PlayerRankingCard({
    super.key,
    required this.api,
    required this.row,
    this.esport = false,
    this.order = 'gol',
    this.index = 0,
  });
  final TosApi api;
  final Map<String, dynamic> row;
  final bool esport;
  final String order;
  final int index;
  @override
  Widget build(BuildContext context) => RankingSurface(
    index: index,
    child: ListTile(
      leading: SizedBox(
        width: esport ? 78 : 94,
        child: Row(
          children: [
            SizedBox(
              width: esport ? 28 : 44,
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  '${row['posizione'] ?? '–'}',
                  style: TextStyle(
                    fontWeight: esport ? FontWeight.bold : FontWeight.w900,
                    fontSize: esport ? null : 26,
                    color: esport ? null : siteRed,
                  ),
                ),
              ),
            ),
            SiteImage(
              api: api,
              path: '${row['foto'] ?? ''}',
              width: 44,
              height: 44,
              fallback: Icons.person_outline,
            ),
          ],
        ),
      ),
      title: Text(
        '${row['nome'] ?? ''} ${row['cognome'] ?? ''}'.trim(),
        style: const TextStyle(fontWeight: FontWeight.bold),
      ),
      subtitle: esport
          ? Text(
              '${row['tornei_giocati'] ?? 0} tornei · ${row['best_result'] ?? ''}',
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if ('${row['ruolo'] ?? ''}'.trim().isNotEmpty ||
                    '${row['squadra'] ?? ''}'.trim().isNotEmpty)
                  Text(
                    [
                      if ('${row['ruolo'] ?? ''}'.trim().isNotEmpty)
                        '${row['ruolo']}',
                      if ('${row['squadra'] ?? ''}'.trim().isNotEmpty)
                        '${row['squadra']}',
                    ].join(' · '),
                  ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 16,
                  runSpacing: 6,
                  children: [
                    for (final stat
                        in order == 'presenze'
                            ? ['presenze', 'gol']
                            : ['gol', 'presenze'])
                      Semantics(
                        label:
                            '${row[stat] ?? 0} ${stat == 'gol' ? 'gol' : 'presenze'}',
                        excludeSemantics: true,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Image.asset(
                              stat == 'gol'
                                  ? 'assets/emoji/football.png'
                                  : 'assets/emoji/clipboard.png',
                              width: 22,
                              height: 22,
                              excludeFromSemantics: true,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              '${row[stat] ?? 0}',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: stat == order
                                    ? FontWeight.w800
                                    : FontWeight.w500,
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
                if (row['media_voti'] != null) ...[
                  const SizedBox(height: 6),
                  Text('Media voto: ${row['media_voti']}'),
                ],
              ],
            ),
      trailing: esport
          ? Text(
              '${row['punti'] ?? 0} pt',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            )
          : null,
      onTap: () => openPage(
        context,
        !esport
            ? PlayerHistoryPage(api: api, player: row, initialType: order)
            : Scaffold(
                appBar: AppBar(
                  title: Text(
                    '${row['nome'] ?? ''} ${row['cognome'] ?? ''}'.trim(),
                  ),
                ),
                body: ListView(
                  padding: const EdgeInsets.all(24),
                  children: [
                    Text(
                      esport
                          ? 'Statistiche EA FC totali'
                          : 'Statistiche totali Old School',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 16),
                    ...(esport
                            ? {
                                'punti': 'Punti totali',
                                'tornei_giocati': 'Tornei giocati',
                                'punti_partecipazione': 'Partecipazione',
                                'punti_gironi': 'Gironi',
                                'bonus_gironi': 'Bonus gironi',
                                'punti_gold': 'Coppa Gold',
                                'punti_silver': 'Coppa Silver',
                                'best_result': 'Miglior risultato',
                              }
                            : {
                                'gol': 'Gol totali',
                                'presenze': 'Presenze totali',
                                'media_voti': 'Media voti',
                                'ruolo': 'Ruolo',
                              })
                        .entries
                        .map(
                          (e) => ListTile(
                            title: Text(e.value),
                            trailing: Text('${row[e.key] ?? '–'}'),
                          ),
                        ),
                  ],
                ),
              ),
      ),
    ),
  );
}

class GlobalRankingPage extends StatefulWidget {
  const GlobalRankingPage({
    super.key,
    required this.api,
    this.esport = false,
    this.initialOrder = 'gol',
  });
  final TosApi api;
  final bool esport;
  final String initialOrder;
  @override
  State<GlobalRankingPage> createState() => _GlobalRankingPageState();
}

class _GlobalRankingPageState extends State<GlobalRankingPage> {
  late String order;
  String search = '';
  final searchController = TextEditingController();
  int page = 1;
  late Future<Map<String, dynamic>> data;
  @override
  void initState() {
    super.initState();
    order = widget.initialOrder;
    load();
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  void submitSearch() => setState(() {
    search = searchController.text.trim();
    page = 1;
    load();
  });

  void load() {
    data = widget.esport
        ? widget.api.esportRanking(limit: 50)
        : widget.api.playerRanking(
            order: order,
            page: page,
            perPage: 10,
            search: search,
          );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.esport ? 'Ranking EA FC' : 'Classifica giocatori totale',
      ),
    ),
    body: Column(
      children: [
        if (!widget.esport) ...[
          Padding(
            padding: const EdgeInsets.all(16),
            child: RankingModeSelector(
              value: order,
              onChanged: (value) => setState(() {
                order = value;
                page = 1;
                load();
              }),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              controller: searchController,
              decoration: InputDecoration(
                labelText: 'Cerca giocatore',
                hintText: 'Nome o cognome',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  tooltip: 'Cerca',
                  onPressed: submitSearch,
                  icon: const Icon(Icons.arrow_forward),
                ),
              ),
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => submitSearch(),
            ),
          ),
        ],
        Expanded(
          child: FutureBuilder<Map<String, dynamic>>(
            future: data,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return ErrorPanel(
                  error: snapshot.error!,
                  retry: () => setState(load),
                );
              }
              final result = snapshot.data!;
              final rows = TosApi.rows(result['data']);
              final pagination = result['pagination'] as Map? ?? {};
              final total = int.tryParse('${pagination['total_pages']}') ?? 1;
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (rows.isEmpty) const Text('Nessun giocatore trovato.'),
                  ...rows.asMap().entries.map(
                    (entry) => PlayerRankingCard(
                      index: entry.key,
                      api: widget.api,
                      row: entry.value,
                      esport: widget.esport,
                      order: order,
                    ),
                  ),
                  if (widget.esport &&
                      (result['meta'] as Map?)?['has_more'] == true)
                    Text(
                      'Il servizio mostra i primi ${rows.length} giocatori del ranking.',
                    ),
                  if (!widget.esport)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                          tooltip: 'Pagina precedente',
                          onPressed: page > 1
                              ? () => setState(() {
                                  page--;
                                  load();
                                })
                              : null,
                          icon: const Icon(Icons.chevron_left),
                        ),
                        Flexible(
                          child: Text(
                            rows.isEmpty
                                ? 'Nessun giocatore trovato'
                                : 'Pagina $page di $total · ${pagination['total'] ?? rows.length} giocatori',
                            textAlign: TextAlign.center,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Pagina successiva',
                          onPressed: page < total
                              ? () => setState(() {
                                  page++;
                                  load();
                                })
                              : null,
                          icon: const Icon(Icons.chevron_right),
                        ),
                      ],
                    ),
                ],
              );
            },
          ),
        ),
      ],
    ),
  );
}

class HallOfFamePanel extends StatefulWidget {
  const HallOfFamePanel({super.key, required this.api, required this.rows});
  final TosApi api;
  final List<Map<String, dynamic>> rows;

  @override
  State<HallOfFamePanel> createState() => _HallOfFamePanelState();
}

class _HallOfFamePanelState extends State<HallOfFamePanel> {
  String? selected;
  int? selectedYear;

  String competitionKey(Map<String, dynamic> row) =>
      '${row['edition_key'] ?? '${row['sezione'] ?? 'calcio'}::${row['competizione'] ?? ''}::${row['anno'] ?? ''}'}';

  num number(Map<String, dynamic> row, String field) =>
      num.tryParse('${row[field] ?? ''}') ?? 0;

  Map<String, dynamic> latestTournament(List<Map<String, dynamic>> rows) {
    var latest = rows.first;
    for (final row in rows.skip(1)) {
      final time = number(
        row,
        'latest_sort_time',
      ).compareTo(number(latest, 'latest_sort_time'));
      if (time > 0 ||
          (time == 0 &&
              number(row, 'latest_record_id') >
                  number(latest, 'latest_record_id'))) {
        latest = row;
      }
    }
    return latest;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.rows.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(20),
        child: Text('Nessun risultato disponibile.'),
      );
    }
    final years =
        widget.rows
            .map((row) => int.tryParse('${row['anno']}') ?? 0)
            .where((year) => year > 0)
            .toSet()
            .toList()
          ..sort((a, b) => b.compareTo(a));
    final currentYear = years.contains(selectedYear) ? selectedYear : null;
    final visible = widget.rows
        .where(
          (row) => currentYear == null || '${row['anno']}' == '$currentYear',
        )
        .toList();
    final competitions = <String, String>{
      for (final row in visible)
        competitionKey(
          row,
        ): '${row['competizione'] ?? 'Torneo'}${widget.rows.where((other) => other['competizione'] == row['competizione']).length > 1 ? ' · ${row['anno'] ?? ''}' : ''}',
    };
    final options = competitions.entries.toList()
      ..sort((a, b) => a.value.toLowerCase().compareTo(b.value.toLowerCase()));
    final value = competitions.containsKey(selected)
        ? selected!
        : competitionKey(latestTournament(visible));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (years.length > 1)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: DropdownButtonFormField<int>(
              isExpanded: true,
              key: ValueKey('hall-year-$currentYear'),
              initialValue: currentYear ?? 0,
              decoration: InputDecoration(
                labelText: 'Anno',
                prefixIcon: Icon(Icons.calendar_month_outlined),
                filled: true,
                fillColor: Theme.of(context).colorScheme.surfaceContainerHigh,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.all(Radius.circular(16)),
                  borderSide: BorderSide.none,
                ),
              ),
              items: [
                const DropdownMenuItem(
                  value: 0,
                  child: Text(
                    'Tutti gli anni',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                for (final year in years)
                  DropdownMenuItem(value: year, child: Text('$year')),
              ],
              onChanged: (year) => setState(() {
                selectedYear = year == 0 ? null : year;
                selected = null;
              }),
            ),
          ),
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: DropdownButtonFormField<String>(
            key: ValueKey(value),
            initialValue: value,
            isExpanded: true,
            menuMaxHeight: 320,
            borderRadius: BorderRadius.circular(16),
            decoration: InputDecoration(
              labelText: 'Scegli il torneo',
              prefixIcon: const Icon(Icons.emoji_events_outlined),
              filled: true,
              fillColor: Theme.of(context).colorScheme.surfaceContainerHigh,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide.none,
              ),
            ),
            items: [
              for (final option in options)
                DropdownMenuItem(
                  value: option.key,
                  child: Text(option.value, overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: (choice) => setState(() => selected = choice),
          ),
        ),
        ...visible
            .where((row) => competitionKey(row) == value)
            .map((row) => HallCard(api: widget.api, row: row)),
      ],
    );
  }
}

class HallCard extends StatelessWidget {
  const HallCard({super.key, required this.api, required this.row});
  final TosApi api;
  final Map<String, dynamic> row;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
    ),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              SiteImage(
                api: api,
                path: '${row['torneo_logo'] ?? ''}',
                fallback: Icons.emoji_events_outlined,
                preserveShape: true,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  '${row['competizione'] ?? ''} · ${row['anno'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...TosApi.rows(row['premi'])
              .map((award) => _HallAward(api: api, award: award)),
          if (TosApi.tournamentSlug(row).isNotEmpty)
            TextButton(
              onPressed: () => openPage(
                context,
                TournamentPage(
                  api: api,
                  tournament: {...row, 'nome': row['competizione']},
                ),
              ),
              child: const Text('Vedi torneo'),
            ),
        ],
      ),
    ),
  );
}

class _HallAward extends StatelessWidget {
  const _HallAward({required this.api, required this.award});
  final TosApi api;
  final Map<String, dynamic> award;
  @override
  Widget build(BuildContext context) {
    final kind = '${award['kind'] ?? ''}';
    final individual =
        award['tipo'] == 'Individuale' ||
        ['SCORER', 'MVP', 'KEEPER', 'DEFENDER', 'ATTACKER'].contains(kind);
    final accent = kind == 'GOLD'
        ? const Color(0xff9d7116)
        : kind == 'SILVER'
        ? const Color(0xff697e91)
        : kind == 'BRONZO'
        ? const Color(0xffa65c35)
        : siteBlue;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withValues(alpha: .2)),
      ),
      child: Row(
        children: [
          SiteImage(
            api: api,
            path: '${award['logo_vincitrice'] ?? ''}',
            width: 44,
            height: 44,
            fallback: individual ? Icons.person_outline : Icons.shield_outlined,
            preserveShape: true,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      kind == 'SCORER'
                          ? Icons.sports_soccer
                          : Icons.emoji_events_outlined,
                      size: 16,
                      color: accent,
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        '${award['premio'] ?? ''}',
                        style: TextStyle(
                          color: accent,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  '${award['vincitrice'] ?? ''}',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                if (kind == 'SCORER' &&
                    (int.tryParse('${award['gol']}') ?? 0) > 0)
                  Text(
                    '${award['gol']} gol',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xff637487),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class NewsCard extends StatelessWidget {
  const NewsCard({
    super.key,
    required this.api,
    required this.row,
    this.featured = false,
  });
  final TosApi api;
  final Map<String, dynamic> row;
  final bool featured;
  @override
  Widget build(BuildContext context) => HomeNewsTile(
    api: api,
    row: row,
    featured: featured,
    open: () => openPage(
      context,
      DataPage(
        title: 'Notizia',
        load: () => api.article('${row['id']}'),
        render: (value) {
          final article = Map<String, dynamic>.from(value as Map);
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                '${article['titolo'] ?? ''}',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text('${article['data'] ?? ''}'),
              const SizedBox(height: 16),
              if ('${article['cover'] ?? ''}'.isNotEmpty)
                SiteImage(
                  api: api,
                  path: '${article['cover']}',
                  width: double.infinity,
                  height: 200,
                ),
              const SizedBox(height: 16),
              ArticleContent(content: '${article['contenuto'] ?? ''}'),
              ...TosApi.rows(article['media'])
                  .where(
                    (m) => m['tipo'] == 'image' && m['url'] != article['cover'],
                  )
                  .map(
                    (m) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: SiteImage(
                        api: api,
                        path: '${m['url']}',
                        width: double.infinity,
                        height: 220,
                      ),
                    ),
                  ),
            ],
          );
        },
      ),
    ),
  );
}

String articleText(String html) {
  final fragment = parseFragment(html);
  final buffer = StringBuffer();
  void visit(dom.Node node) {
    if (node is dom.Text) {
      buffer.write(node.data);
      return;
    }
    if (node is dom.Element &&
        ['script', 'style', 'iframe'].contains(node.localName)) {
      return;
    }
    if (node is dom.Element && node.localName == 'br') buffer.writeln();
    for (final child in node.nodes) {
      visit(child);
    }
    if (node is dom.Element &&
        [
          'p',
          'div',
          'li',
          'h1',
          'h2',
          'h3',
          'blockquote',
        ].contains(node.localName)) {
      buffer.writeln('\n');
    }
  }

  visit(fragment);
  return buffer.toString().replaceAll(RegExp(r'\n{3,}'), '\n\n').trim();
}
