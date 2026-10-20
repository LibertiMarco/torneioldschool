import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'auth.dart';
import 'home_dashboard.dart' show SiteImage, openPage;
import 'personal_match_order.dart';
import 'tournament_page.dart';
import 'venue_navigation.dart';

/// Uses only the player resolved by the authenticated server, never a client ID.
class HomeNextMatch extends StatelessWidget {
  const HomeNextMatch({
    super.key,
    required this.auth,
    required this.section,
    required this.revision,
  });

  final TosAuth auth;
  final String section;
  final int revision;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: auth,
    builder: (context, _) => auth.user == null || section != 'calcio'
        ? const SizedBox.shrink()
        : _NextMatch(
            key: ValueKey((auth, auth.user?['id'], section, revision)),
            auth: auth,
            section: section,
          ),
  );
}

class _NextMatch extends StatefulWidget {
  const _NextMatch({super.key, required this.auth, required this.section});
  final TosAuth auth;
  final String section;

  @override
  State<_NextMatch> createState() => _NextMatchState();
}

class _NextMatchState extends State<_NextMatch> {
  late Future<dynamic> _data = _load();
  bool get _dark => Theme.of(context).brightness == Brightness.dark;
  Color get _foreground => _dark
      ? Theme.of(context).colorScheme.onSurface
      : Theme.of(context).colorScheme.onPrimaryContainer;

  Future<dynamic> _load() async => widget.auth.authorized(
    'api/mobile/v1/player_stats.php',
    query: {'section': widget.section},
  );

  String _schedule(Map<String, dynamic> match) {
    final date = DateTime.tryParse('${match['data_partita'] ?? ''}');
    final time = '${match['ora_partita'] ?? ''}'.trim();
    return [
      if (date == null)
        'Data da definire'
      else
        '${date.day.toString().padLeft(2, '0')}/'
            '${date.month.toString().padLeft(2, '0')}/${date.year}',
      if (time.isNotEmpty) time.length >= 5 ? time.substring(0, 5) : time,
    ].join(' · ');
  }

  Widget _card(List<Widget> children) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Card(
        margin: EdgeInsets.zero,
        color: _dark ? colors.surfaceContainer : colors.primaryContainer,
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: DefaultTextStyle.merge(
            style: TextStyle(color: _foreground),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: children,
            ),
          ),
        ),
      ),
    );
  }

  Widget _team(String name, String logo, {required bool compact}) {
    final colors = Theme.of(context).colorScheme;
    final crest = Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: _dark ? colors.surfaceContainerHigh : colors.surface,
        borderRadius: BorderRadius.circular(20),
      ),
      child: SiteImage(
        api: widget.auth.api,
        path: logo,
        width: 52,
        height: 52,
        fallback: Icons.shield_outlined,
        preserveShape: true,
      ),
    );
    final label = Text(
      name,
      textAlign: compact ? TextAlign.left : TextAlign.center,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w800,
        height: 1.25,
      ),
    );
    return compact
        ? Row(
            children: [
              crest,
              const SizedBox(width: 12),
              Expanded(child: label),
            ],
          )
        : Column(children: [crest, const SizedBox(height: 12), label]);
  }

  Widget _info(IconData icon, String text) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Icon(icon, size: 18),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Text(text, style: const TextStyle(fontWeight: FontWeight.w600)),
      ),
    ],
  );

  Widget _venue(Map<String, dynamic> match, String field) {
    final uri = venueNavigationUri(match);
    if (uri == null) return _info(Icons.location_on_outlined, field);
    return Tooltip(
      message: 'Apri indicazioni',
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () async {
          try {
            if (await launchUrl(
              uri,
              mode: LaunchMode.externalApplication,
              webOnlyWindowName: '_blank',
            )) {
              return;
            }
          } catch (_) {
            // Keep the match card usable when navigation cannot be opened.
          }
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Impossibile aprire la navigazione. Riprova.'),
              ),
            );
          }
        },
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Row(
            children: [
              Expanded(child: _info(Icons.location_on_outlined, field)),
              const SizedBox(width: 8),
              Icon(Icons.near_me_outlined, size: 18, color: _foreground),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<dynamic>(
    future: _data,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return _card([
          const Text('Il tuo calendario'),
          const SizedBox(height: 12),
          const LinearProgressIndicator(
            semanticsLabel: 'Caricamento della prossima partita',
          ),
        ]);
      }
      if (snapshot.hasError) {
        return _card([
          const Text('Il tuo calendario non è disponibile al momento.'),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => setState(() {
                _data = _load();
              }),
              icon: const Icon(Icons.refresh),
              label: const Text('Riprova'),
            ),
          ),
        ]);
      }
      final data = snapshot.data as Map;
      if (data['player'] == null) return const SizedBox.shrink();
      final matches = orderPersonalMatches(
        TosApi.rows(data['upcoming_matches']),
      );
      if (matches.isEmpty) {
        return _card([
          const Text(
            'IL TUO CALENDARIO',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          const Text('Non ci sono partite da disputare per le tue squadre.'),
        ]);
      }
      final match = matches.first;
      final tournament = '${match['torneo_nome'] ?? match['torneo'] ?? ''}'
          .trim();
      final field = '${match['campo'] ?? ''}'.trim();
      final tournamentFile = '${match['torneo_file'] ?? match['torneo'] ?? ''}'
          .trim();
      return _card([
        const Text(
          'LA TUA PROSSIMA PARTITA',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.2,
          ),
        ),
        if (tournament.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(tournament),
        ],
        const SizedBox(height: 24),
        LayoutBuilder(
          builder: (context, constraints) {
            final compact = MediaQuery.textScalerOf(context).scale(16) > 24;
            final home = _team(
              '${match['squadra_casa']}',
              '${match['logo_casa'] ?? ''}',
              compact: compact,
            );
            final away = _team(
              '${match['squadra_ospite']}',
              '${match['logo_ospite'] ?? ''}',
              compact: compact,
            );
            final vs = Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                'VS',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                  color: _foreground,
                ),
              ),
            );
            return compact
                ? Column(children: [home, vs, away])
                : Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: home),
                      Padding(
                        padding: const EdgeInsets.only(top: 16),
                        child: vs,
                      ),
                      Expanded(child: away),
                    ],
                  );
          },
        ),
        const SizedBox(height: 20),
        const Divider(height: 1),
        const SizedBox(height: 10),
        _info(Icons.event_outlined, _schedule(match)),
        if (field.isNotEmpty) ...[
          const SizedBox(height: 2),
          _venue(match, field),
        ],
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: tournamentFile.isEmpty
                ? null
                : () => openPage(
                    context,
                    TournamentPage(
                      api: widget.auth.api,
                      tournament: {
                        'id': match['torneo_id'],
                        'nome': tournament,
                        'filetorneo': tournamentFile,
                        'sezione': widget.section,
                      },
                      initialTab: 'Calendario',
                    ),
                  ),
            icon: const Icon(Icons.arrow_forward),
            label: const Text('Vai al torneo'),
          ),
        ),
      ]);
    },
  );
}
