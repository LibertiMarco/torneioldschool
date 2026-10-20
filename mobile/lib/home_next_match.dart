import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'details.dart' show MatchPage;

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
        color: colors.primaryContainer,
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: DefaultTextStyle.merge(
            style: TextStyle(color: colors.onPrimaryContainer),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: children,
            ),
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
      // The authenticated endpoint orders scheduled matches first, then undated.
      final matches = TosApi.rows(data['upcoming_matches']);
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
      return _card([
        const Text(
          'LA TUA PROSSIMA PARTITA',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.2,
          ),
        ),
        const SizedBox(height: 16),
        Text(
          '${match['squadra_casa']} – ${match['squadra_ospite']}',
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
        ),
        if (tournament.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(tournament),
        ],
        const SizedBox(height: 16),
        Text(
          _schedule(match),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        if (field.isNotEmpty) ...[const SizedBox(height: 6), Text(field)],
        const SizedBox(height: 16),
        Align(
          alignment: Alignment.centerLeft,
          child: FilledButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => MatchPage(
                  api: widget.auth.api,
                  id: '${match['id']}',
                  previewMatch: match,
                ),
              ),
            ),
            icon: const Icon(Icons.arrow_forward),
            label: const Text('Dettagli partita'),
          ),
        ),
      ]);
    },
  );
}
