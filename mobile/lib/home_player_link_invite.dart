import 'package:flutter/material.dart';

import 'auth.dart';
import 'personal_player_page.dart';

/// A dismissible invitation, only after the server confirms no linked player.
class HomePlayerLinkInvite extends StatefulWidget {
  const HomePlayerLinkInvite({super.key, required this.auth, this.onProfile});
  final TosAuth auth;
  final VoidCallback? onProfile;

  @override
  State<HomePlayerLinkInvite> createState() => _HomePlayerLinkInviteState();
}

class _HomePlayerLinkInviteState extends State<HomePlayerLinkInvite> {
  late Future<dynamic> _status = _load();
  bool _dismissed = false;

  Future<dynamic> _load() =>
      widget.auth.authorized('api/mobile/v1/player_link_requests.php');

  Future<void> _openProfile() async {
    if (widget.onProfile != null) {
      widget.onProfile!();
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => Scaffold(
          appBar: AppBar(title: const Text('Profilo')),
          body: PersonalPlayerPage(auth: widget.auth, section: 'calcio'),
        ),
      ),
    );
    if (mounted) setState(() => _status = _load());
  }

  @override
  Widget build(BuildContext context) {
    if (_dismissed || widget.auth.user == null) return const SizedBox.shrink();
    return FutureBuilder<dynamic>(
      future: _status,
      builder: (context, snapshot) {
        if (!snapshot.hasData || snapshot.hasError) {
          return const SizedBox.shrink();
        }
        final data = snapshot.data as Map;
        if (data['linked'] == true) return const SizedBox.shrink();
        final pending = (data['request'] as Map?)?['status'] == 'pending';
        final colors = Theme.of(context).colorScheme;
        return Card(
          margin: const EdgeInsets.only(bottom: 20),
          color: colors.primaryContainer,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 8, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      pending
                          ? Icons.hourglass_top_rounded
                          : Icons.person_add_alt_1_rounded,
                      color: colors.onPrimaryContainer,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        pending
                            ? 'Richiesta in verifica'
                            : 'Giochi nei tornei TOS?',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: colors.onPrimaryContainer,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Nascondi per ora',
                      onPressed: () => setState(() => _dismissed = true),
                      icon: Icon(Icons.close, color: colors.onPrimaryContainer),
                    ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: Text(
                    pending
                        ? 'Lo staff sta verificando il collegamento. Puoi controllare lo stato nel Profilo.'
                        : 'Collega la tua scheda giocatore per ritrovare partite, gol e presenze nel Profilo.',
                    style: TextStyle(color: colors.onPrimaryContainer),
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: _openProfile,
                  icon: Icon(
                    pending ? Icons.badge_outlined : Icons.link_rounded,
                  ),
                  label: Text(
                    pending
                        ? 'Controlla richiesta'
                        : 'Collega il tuo giocatore',
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
