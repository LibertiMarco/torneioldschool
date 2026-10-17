import 'dart:async';

import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'home_dashboard.dart' show SiteImage;

class PlayerLinkRequestPage extends StatefulWidget {
  const PlayerLinkRequestPage({
    super.key,
    required this.auth,
    required this.onRefresh,
    this.onAccount,
  });
  final TosAuth auth;
  final Future<void> Function() onRefresh;
  final VoidCallback? onAccount;
  @override
  State<PlayerLinkRequestPage> createState() => _PlayerLinkRequestPageState();
}

class _PlayerLinkRequestPageState extends State<PlayerLinkRequestPage> {
  final search = TextEditingController();
  Timer? debounce;
  Map<String, dynamic>? request, selected;
  List<Map<String, dynamic>> players = [];
  String? error;
  bool loading = true, searching = false, sending = false, confirmed = false;
  int revision = 0;
  static const path = 'api/mobile/v1/player_link_requests.php';
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  String name(Map player) =>
      '${player['nome'] ?? ''} ${player['cognome'] ?? ''}'.trim();
  bool get pending => request?['status'] == 'pending';
  String message(Object failure) => failure is ApiError
      ? failure.message
      : 'Servizio non disponibile. Riprova.';
  Future<void> load() async {
    try {
      final data = await widget.auth.authorized(path) as Map;
      if (!mounted) return;
      setState(() {
        request = data['request'] == null
            ? null
            : Map<String, dynamic>.from(data['request'] as Map);
        error = null;
        loading = false;
      });
      if (data['linked'] == true) await widget.onRefresh();
    } catch (failure) {
      if (mounted) {
        setState(() {
          error = message(failure);
          loading = false;
        });
      }
    }
  }

  void searchChanged(String query) {
    debounce?.cancel();
    final current = ++revision;
    setState(() {
      players = [];
      selected = null;
      confirmed = false;
      searching = query.trim().length >= 2;
      error = null;
    });
    if (!searching) return;
    debounce = Timer(const Duration(milliseconds: 350), () async {
      try {
        final data = await widget.auth.authorized(
          path,
          query: {'q': query.trim()},
        ) as Map;
        if (!mounted || current != revision) return;
        setState(() {
          players = TosApi.rows(data['players']);
          searching = false;
        });
      } catch (failure) {
        if (mounted && current == revision) {
          setState(() {
            error = message(failure);
            searching = false;
          });
        }
      }
    });
  }

  Future<void> submit() async {
    if (selected == null || !confirmed || sending || pending) return;
    setState(() {
      sending = true;
      error = null;
    });
    try {
      final data = await widget.auth.authorized(
        path,
        body: {'giocatore_id': int.parse('${selected!['id']}')},
      ) as Map;
      if (!mounted) return;
      setState(() {
        request = Map<String, dynamic>.from(data['request'] as Map);
        players = [];
        selected = null;
        confirmed = false;
        search.clear();
      });
    } catch (failure) {
      if (mounted) setState(() => error = message(failure));
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Icon(Icons.person_add_alt_1_outlined, size: 48, color: colors.primary),
        const SizedBox(height: 16),
        Text(
          'La tua scheda ti aspetta',
          style: Theme.of(context).textTheme.headlineSmall
              ?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 12),
        const Text(
          'Cerca e seleziona il giocatore che corrisponde a te. Un amministratore verificherà la richiesta prima di collegare il tuo account.',
        ),
        const SizedBox(height: 20),
        if (loading) const LinearProgressIndicator(),
        if (request != null)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    switch (request!['status']) {
                      'pending' => 'Richiesta in attesa di approvazione',
                      'accepted' => 'Richiesta accettata',
                      _ => 'Richiesta rifiutata',
                    },
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: colors.onSurface,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    name(request!).isEmpty
                        ? 'Giocatore non più disponibile'
                        : name(request!),
                  ),
                  if ('${request!['reason'] ?? ''}'.trim().isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text('${request!['reason']}'),
                  ],
                  if ('${request!['already_linked']}' == '1')
                    const Text(
                      'Giocatore già associato. Lo staff verificherà il conflitto senza modificare automaticamente il collegamento.',
                    ),
                  if (pending)
                    const Padding(
                      padding: EdgeInsets.only(top: 8),
                      child: Text(
                        'Puoi consultare qui l’esito della richiesta.',
                      ),
                    ),
                ],
              ),
            ),
          ),
        if (!pending && !loading) ...[
          TextField(
            controller: search,
            enabled: !sending,
            onChanged: searchChanged,
            decoration: const InputDecoration(
              labelText: 'Cerca il tuo giocatore',
              hintText: 'Nome e cognome · almeno 2 caratteri',
              prefixIcon: Icon(Icons.search),
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          if (searching) const LinearProgressIndicator(),
          if (!searching &&
              search.text.trim().length >= 2 &&
              players.isEmpty &&
              error == null)
            const Text('Nessun giocatore trovato. Prova con un altro nome.'),
          for (final player in players)
            Card(
              child: ListTile(
                selected: selected?['id'] == player['id'],
                leading: SiteImage(
                  api: widget.auth.api,
                  path: '${player['foto'] ?? ''}',
                  width: 42,
                  height: 42,
                  fallback: Icons.person_outline,
                ),
                title: Text(name(player)),
                subtitle: Text(
                  [
                    '${player['ruolo'] ?? ''}',
                    '${player['squadra'] ?? ''}',
                    if ('${player['already_linked']}' == '1')
                      'Già associato · verifica amministratore',
                  ].where((value) => value.isNotEmpty).join(' · '),
                ),
                trailing: Icon(
                  selected?['id'] == player['id']
                      ? Icons.check_circle
                      : Icons.radio_button_unchecked,
                ),
                onTap: sending
                    ? null
                    : () => setState(() {
                        selected = player;
                        confirmed = false;
                      }),
              ),
            ),
          if (selected != null) ...[
            CheckboxListTile(
              value: confirmed,
              controlAffinity: ListTileControlAffinity.leading,
              contentPadding: EdgeInsets.zero,
              title: Text('Confermo che ${name(selected!)} sono io'),
              onChanged: sending
                  ? null
                  : (value) => setState(() => confirmed = value == true),
            ),
            FilledButton.icon(
              onPressed: confirmed && !sending ? submit : null,
              icon: const Icon(Icons.send_outlined),
              label: Text(
                sending ? 'Invio in corso…' : 'Invia richiesta agli admin',
              ),
            ),
          ],
        ],
        if (error != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text(error!, style: TextStyle(color: colors.error)),
          ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: sending || loading
              ? null
              : () async {
                  await widget.onRefresh();
                  if (mounted) await load();
                },
          icon: const Icon(Icons.refresh),
          label: const Text('Verifica associazione'),
        ),
        if (widget.onAccount != null)
          TextButton.icon(
            onPressed: widget.onAccount,
            icon: const Icon(Icons.manage_accounts_outlined),
            label: const Text('Il mio account'),
          ),
      ],
    );
  }
}
