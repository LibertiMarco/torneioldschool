import 'package:flutter/material.dart';

import 'access_gate.dart';
import 'api.dart';
import 'auth.dart';
import 'home_dashboard.dart' show SiteImage;
import 'team_page.dart';
import 'tournament_page.dart';
import 'tournament_notifications.dart';
import 'tournament_list.dart' show normalizedSearch;

/// Several selected teams from one tournament share a single Home shortcut.
List<Map<String, dynamic>> groupFavoriteItems(
  List<Map<String, dynamic>> items,
) {
  final groups = <String, List<Map<String, dynamic>>>{};
  for (final item in items) {
    groups.putIfAbsent('${item['torneo_slug']}', () => []).add(item);
  }
  return groups.values.map((rows) {
    final wholeTournament = rows.where((row) => row['tipo'] == 'torneo');
    if (wholeTournament.isNotEmpty) return wholeTournament.first;
    final teams = rows.map((row) => '${row['squadra_nome']}').toSet().toList();
    return teams.length <= 1
        ? rows.first
        : {...rows.first, 'tipo': 'torneo', 'squadre_seguite': teams};
  }).toList();
}

class HomeFavorites extends StatelessWidget {
  const HomeFavorites({
    super.key,
    required this.auth,
    required this.section,
    this.revision = 0,
    this.fullPage = false,
  });
  final TosAuth auth;
  final String section;
  final int revision;
  final bool fullPage;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: auth,
    builder: (context, _) {
      if (auth.user == null) {
        return fullPage
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('Accedi per ritrovare i tuoi preferiti.'),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: auth.busy ? null : auth.login,
                      child: const Text('Accedi'),
                    ),
                  ],
                ),
              )
            : const SizedBox.shrink();
      }
      return AppAuthScope(
        auth: auth,
        child: _FavoritesContent(
          key: ValueKey((auth, auth.user?['id'], section, revision, fullPage)),
          auth: auth,
          section: section,
          fullPage: fullPage,
        ),
      );
    },
  );
}

class _FavoritesContent extends StatefulWidget {
  const _FavoritesContent({
    super.key,
    required this.auth,
    required this.section,
    required this.fullPage,
  });
  final TosAuth auth;
  final String section;
  final bool fullPage;
  @override
  State<_FavoritesContent> createState() => _FavoritesContentState();
}

class _FavoritesContentState extends State<_FavoritesContent> {
  late Future<Map<String, dynamic>> _data = _load();
  String _search = '';
  int _visibleTournaments = 20;

  Future<Map<String, dynamic>> _load() async {
    final results = await Future.wait([
      widget.auth.authorized(
        'api/mobile/v1/follow.php',
        query: {'list': '1', 'section': widget.section},
      ),
      if (widget.fullPage) widget.auth.api.tournaments(widget.section),
    ]);
    return {
      'items': TosApi.rows((results.first as Map)['items']),
      'tournaments': widget.fullPage ? results[1] : <Map<String, dynamic>>[],
    };
  }

  void _reload() {
    if (mounted) {
      setState(() {
        _data = _load();
      });
    }
  }

  Future<void> _manage() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => Scaffold(
          appBar: AppBar(title: const Text('I tuoi preferiti')),
          body: HomeFavorites(
            auth: widget.auth,
            section: widget.section,
            fullPage: true,
          ),
        ),
      ),
    );
    _reload();
  }

  Future<void> _open(Map<String, dynamic> item) async {
    final api = widget.auth.api;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AccessGate(
          fullPage: true,
          builder: (_) => item['tipo'] == 'squadra'
              ? TeamPage(
                  api: api,
                  slug: '${item['torneo_slug']}',
                  team: {
                    'id': item['squadra_id'],
                    'nome': item['squadra_nome'],
                    'logo': item['squadra_logo'],
                    'torneo': item['torneo_slug'],
                  },
                )
              : TournamentPage(
                  api: api,
                  tournament: {
                    'id': item['torneo_id'],
                    'nome': item['torneo_nome'],
                    'img': item['torneo_logo'],
                    'filetorneo': item['filetorneo'],
                  },
                ),
        ),
      ),
    );
    _reload();
  }

  Widget _favorite(Map<String, dynamic> item) {
    final team = item['tipo'] == 'squadra';
    final selectedTeams = List<String>.from(item['squadre_seguite'] ?? []);
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 4),
      leading: SiteImage(
        identityName: '${item[team ? 'squadra_nome' : 'torneo_nome'] ?? ''}',
        api: widget.auth.api,
        path: '${item[team ? 'squadra_logo' : 'torneo_logo'] ?? ''}',
        width: 40,
        height: 40,
        preserveShape: true,
        fallback: team ? Icons.shield_outlined : Icons.emoji_events_outlined,
      ),
      title: Text(
        '${item[team ? 'squadra_nome' : 'torneo_nome']}',
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        selectedTeams.isNotEmpty
            ? selectedTeams.join(' · ')
            : team
            ? '${item['torneo_nome']}'
            : 'Torneo seguito',
        style: TextStyle(
          fontSize: 12,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
        maxLines: widget.fullPage ? null : 2,
        overflow: widget.fullPage ? null : TextOverflow.ellipsis,
      ),
      trailing: const Icon(Icons.chevron_right),
      onTap: () => _open(item),
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
    future: _data,
    builder: (context, snapshot) {
      final loading = snapshot.connectionState != ConnectionState.done;
      if (snapshot.hasError) {
        final error = Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Impossibile caricare i preferiti.'),
            TextButton(onPressed: _reload, child: const Text('Riprova')),
          ],
        );
        return widget.fullPage
            ? Center(child: error)
            : Padding(padding: const EdgeInsets.only(bottom: 24), child: error);
      }
      if (!snapshot.hasData) {
        return const Padding(
          padding: EdgeInsets.symmetric(vertical: 20),
          child: LinearProgressIndicator(
            semanticsLabel: 'Caricamento preferiti',
          ),
        );
      }
      final tournaments = TosApi.rows(snapshot.data!['tournaments'])
          .where(
            (t) => const {
              'in corso',
              'programmato',
            }.contains('${t['stato'] ?? ''}'.trim().toLowerCase()),
          )
          .toList();
      final activeSlugs = tournaments.map(TosApi.tournamentSlug).toSet();
      final items = groupFavoriteItems(
        TosApi.rows(snapshot.data!['items'])
            .where(
              (item) =>
                  !widget.fullPage || activeSlugs.contains(item['torneo_slug']),
            )
            .toList(),
      );
      final header = Row(
        children: [
          const Expanded(
            child: Text(
              'I tuoi preferiti',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            ),
          ),
          if (!widget.fullPage)
            TextButton(onPressed: _manage, child: const Text('Gestisci')),
        ],
      );
      final content = <Widget>[
        if (!widget.fullPage) header,
        if (loading) const LinearProgressIndicator(),
        if (items.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Text('Scegli i tornei e le squadre da ritrovare qui.'),
          ),
        for (final item in (widget.fullPage ? items : items.take(3)))
          _favorite(item),
        if (!widget.fullPage && items.length > 3)
          TextButton(
            onPressed: _manage,
            child: Text('Mostra tutti (${items.length})'),
          ),
      ];
      if (!widget.fullPage) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 24),
          child: Card(
            margin: EdgeInsets.zero,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: content,
              ),
            ),
          ),
        );
      }
      final filtered = tournaments
          .where(
            (t) =>
                normalizedSearch('${t['nome']} ${t['categoria'] ?? ''}')
                    .contains(normalizedSearch(_search)),
          )
          .toList();
      return ListView(
        padding: const EdgeInsets.all(20),
        children: [
          ...content,
          const SizedBox(height: 20),
          const Text(
            'Gestisci i preferiti',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          const Text(
            'Segui un torneo o scegli le tue squadre. Riceverai anche gli aggiornamenti sui risultati se hai attivato le notifiche.',
          ),
          const SizedBox(height: 16),
          TextField(
            decoration: const InputDecoration(
              labelText: 'Cerca un torneo',
              prefixIcon: Icon(Icons.search),
            ),
            onChanged: (value) => setState(() {
              _search = value;
              _visibleTournaments = 20;
            }),
          ),
          const SizedBox(height: 16),
          if (filtered.isEmpty) const Text('Nessun torneo trovato.'),
          for (final tournament in filtered.take(_visibleTournaments))
            Card(
              child: ExpansionTile(
                key: ValueKey(TosApi.tournamentSlug(tournament)),
                leading: SiteImage(
                  identityName: '${tournament['nome'] ?? ''}',
                  api: widget.auth.api,
                  path: '${tournament['img'] ?? ''}',
                  width: 36,
                  height: 36,
                  preserveShape: true,
                  fallback: Icons.emoji_events_outlined,
                ),
                title: Text('${tournament['nome']}'),
                childrenPadding: const EdgeInsets.all(12),
                children: [
                  TournamentNotifications(
                    slug: TosApi.tournamentSlug(tournament),
                    favoritesMode: true,
                    onChanged: _reload,
                  ),
                ],
              ),
            ),
          if (filtered.length > _visibleTournaments)
            OutlinedButton(
              onPressed: () => setState(() => _visibleTournaments += 20),
              child: const Text('Mostra altri tornei'),
            ),
        ],
      );
    },
  );
}
