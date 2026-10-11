import 'package:flutter/material.dart';
import 'package:html/dom.dart' as dom;
import 'package:html/parser.dart' show parseFragment;

import 'api.dart';
import 'about_page.dart';
import 'article_content.dart';
import 'player_history.dart';
import 'ranking_mode_selector.dart';
import 'details.dart' show DataPage;
import 'main.dart' show ErrorPanel, TournamentPage;
import 'theme.dart' show siteBlue;

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
          ? Icon(fallback, color: siteBlue)
          : ColoredBox(
              color: const Color(0xffeeeeee),
              child: Icon(fallback, color: siteBlue),
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
  @override
  Widget build(BuildContext context) {
    final api = widget.api;
    final esport = widget.section == 'esport';
    Widget heading(String text) => Padding(
      padding: const EdgeInsets.fromLTRB(0, 24, 0, 12),
      child: Text(
        text,
        style: Theme.of(context).textTheme.titleLarge
            ?.copyWith(fontWeight: FontWeight.bold, color: siteBlue),
      ),
    );
    return RefreshIndicator(
      onRefresh: () async => setState(() => reload++),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Card(
            color: siteBlue,
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    esport ? 'Old School Esport' : 'Tornei calcetto Napoli',
                    style: const TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    esport
                        ? 'Tornei, ranking EA FC e tutte le sfide della community.'
                        : 'Tornei di calcio a 5, 6 e 8 a Napoli. Risultati, classifiche e statistiche Old School.',
                    style: const TextStyle(color: Colors.white),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: widget.onTournaments,
                    icon: const Icon(Icons.emoji_events_outlined),
                    label: const Text('Scopri i tornei'),
                  ),
                ],
              ),
            ),
          ),
          heading(esport ? 'Ranking EA FC' : 'Classifica giocatori totale'),
          if (!esport)
            RankingModeSelector(
              value: order,
              onChanged: (value) => setState(() => order = value),
            ),
          HomeBlock(
            key: ValueKey('ranking-$reload-$order'),
            load: () =>
                esport ? api.esportRanking() : api.playerRanking(order: order),
            render: (value) {
              final result = Map<String, dynamic>.from(value as Map);
              final rows = TosApi.rows(result['data']);
              return Column(
                children: [
                  if (rows.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(20),
                      child: Text('Nessun giocatore disponibile.'),
                    ),
                  ...rows.map(
                    (row) => PlayerRankingCard(
                      api: api,
                      row: row,
                      esport: esport,
                      order: order,
                    ),
                  ),
                  if (!esport ||
                      (result['meta'] as Map?)?['can_view_full'] == true)
                    TextButton(
                      onPressed: () => openPage(
                        context,
                        GlobalRankingPage(
                          api: api,
                          esport: esport,
                          initialOrder: order,
                        ),
                      ),
                      child: const Text('Classifica completa'),
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
          heading('Ultime notizie'),
          HomeBlock(
            key: ValueKey('news-$reload'),
            load: () => api.news(widget.section),
            render: (value) {
              final rows = TosApi.rows(value);
              return Column(
                children: [
                  if (rows.isEmpty) const Text('Nessuna notizia disponibile.'),
                  ...rows.take(4).map((row) => NewsCard(api: api, row: row)),
                  TextButton(
                    onPressed: () => openPage(
                      context,
                      DataPage(
                        title: 'Notizie',
                        load: () => api.news(widget.section, all: true),
                        render: (data) => ListView(
                          padding: const EdgeInsets.all(16),
                          children: TosApi.rows(data)
                              .map((r) => NewsCard(api: api, row: r))
                              .toList(),
                        ),
                      ),
                    ),
                    child: const Text('Tutte le notizie'),
                  ),
                ],
              );
            },
          ),
          heading("Albo d’oro"),
          HomeBlock(
            key: ValueKey('hall-$reload'),
            load: () => api.hallOfFame(widget.section),
            render: (value) {
              final rows = TosApi.rows(value);
              return Column(
                children: [
                  HallOfFamePanel(api: api, rows: rows),
                  TextButton(
                    onPressed: () => openPage(
                      context,
                      DataPage(
                        title: "Albo d’oro",
                        load: () => api.hallOfFame(widget.section),
                        render: (data) => ListView(
                          padding: const EdgeInsets.all(16),
                          children: [
                            HallOfFamePanel(api: api, rows: TosApi.rows(data)),
                          ],
                        ),
                      ),
                    ),
                    child: const Text("Albo d’oro completo"),
                  ),
                ],
              );
            },
          ),
          heading('Chi siamo'),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Lo facciamo per passione, per condividere divertimento e amicizia con chiunque voglia partecipare.',
                    style: TextStyle(fontSize: 16, height: 1.6),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: () => openPage(context, AboutPage(api: api)),
                    icon: const Icon(Icons.groups_outlined),
                    label: const Text('Scopri chi siamo'),
                  ),
                ],
              ),
            ),
          ),
          heading('Contattaci'),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Siamo sempre disponibili per domande, iscrizioni o collaborazioni.',
                  ),
                  SizedBox(height: 16),
                  SelectableText(
                    'Email: info@torneioldschool.it\nSponsorizzazioni: sponsor@torneioldschool.it\nWhatsApp: +39 338 321 3272\nNapoli e provincia',
                    style: TextStyle(height: 1.8),
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
  });
  final TosApi api;
  final Map<String, dynamic> row;
  final bool esport;
  final String order;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: SizedBox(
        width: 78,
        child: Row(
          children: [
            SizedBox(
              width: 28,
              child: Text(
                '${row['posizione'] ?? '–'}',
                style: const TextStyle(fontWeight: FontWeight.bold),
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
                                color: siteBlue,
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
                  ...rows.map(
                    (r) => PlayerRankingCard(
                      api: widget.api,
                      row: r,
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

  String competitionKey(Map<String, dynamic> row) =>
      '${row['sezione'] ?? 'calcio'}::${row['competizione'] ?? ''}';

  num number(Map<String, dynamic> row, String field) =>
      num.tryParse('${row[field] ?? ''}') ?? 0;

  Map<String, dynamic> latestTournament() => widget.rows.reduce((latest, row) {
    final time = number(
      row,
      'latest_sort_time',
    ).compareTo(number(latest, 'latest_sort_time'));
    return time > 0 ||
            (time == 0 &&
                number(row, 'latest_record_id') >
                    number(latest, 'latest_record_id'))
        ? row
        : latest;
  });

  @override
  Widget build(BuildContext context) {
    if (widget.rows.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(20),
        child: Text('Nessun risultato disponibile.'),
      );
    }
    final competitions = <String, String>{
      for (final row in widget.rows)
        competitionKey(row): '${row['competizione'] ?? 'Torneo'}',
    };
    final options = competitions.entries.toList()
      ..sort((a, b) => a.value.toLowerCase().compareTo(b.value.toLowerCase()));
    final value = competitions.containsKey(selected)
        ? selected!
        : competitionKey(latestTournament());
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
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
              fillColor: const Color(0xffe8edf5),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
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
        ...widget.rows
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
  Widget build(BuildContext context) => Card(
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
          ...TosApi.rows(row['premi']).map(
            (award) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: SiteImage(
                api: api,
                path: '${award['logo_vincitrice'] ?? ''}',
                width: 36,
                height: 36,
                fallback: Icons.shield_outlined,
                preserveShape: true,
              ),
              title: Text('${award['vincitrice'] ?? ''}'),
              subtitle: Text('${award['premio'] ?? ''}'),
            ),
          ),
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

class NewsCard extends StatelessWidget {
  const NewsCard({super.key, required this.api, required this.row});
  final TosApi api;
  final Map<String, dynamic> row;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      contentPadding: const EdgeInsets.all(12),
      leading: SiteImage(
        api: api,
        path: '${row['cover'] ?? row['immagine'] ?? ''}',
        width: 64,
        height: 64,
      ),
      title: Text(
        '${row['titolo'] ?? ''}',
        style: const TextStyle(fontWeight: FontWeight.bold),
      ),
      subtitle: Text('${row['data'] ?? ''}'),
      trailing: const Icon(Icons.chevron_right),
      onTap: () => openPage(
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
                      (m) =>
                          m['tipo'] == 'image' && m['url'] != article['cover'],
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
