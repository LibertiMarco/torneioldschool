import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'details.dart' show TeamPage;
import 'home_dashboard.dart' show openPage;
import 'player_history.dart';
import 'theme.dart';
import 'personal_player_design.dart';

/// The personal tab always resolves its player through the authenticated API.
class PersonalPlayerPage extends StatefulWidget {
  const PersonalPlayerPage({
    super.key,
    required this.auth,
    required this.section,
    this.onAccount,
  });
  final TosAuth auth;
  final String section;
  final VoidCallback? onAccount;

  @override
  State<PersonalPlayerPage> createState() => _PersonalPlayerPageState();
}

class _PersonalPlayerPageState extends State<PersonalPlayerPage> {
  Future<Map<String, dynamic>>? _data;
  Object? _userId;

  @override
  void initState() {
    super.initState();
    widget.auth.addListener(_authChanged);
    _reset();
  }

  void _reset() {
    _userId = widget.auth.user?['id'];
    _data = widget.auth.user == null ? null : _load();
  }

  void _authChanged() {
    if (!mounted) return;
    setState(() {
      if (_userId != widget.auth.user?['id']) _reset();
    });
  }

  @override
  void didUpdateWidget(covariant PersonalPlayerPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.auth != widget.auth) {
      oldWidget.auth.removeListener(_authChanged);
      widget.auth.addListener(_authChanged);
    }
    if (oldWidget.auth != widget.auth || oldWidget.section != widget.section) {
      _reset();
    }
  }

  @override
  void dispose() {
    widget.auth.removeListener(_authChanged);
    super.dispose();
  }

  Future<Map<String, dynamic>> _load({String? type, int page = 1}) async =>
      Map<String, dynamic>.from(
        await widget.auth.authorized(
          'api/mobile/v1/player_stats.php',
          query: {
            'section': widget.section,
            if (type != null) ...{'type': type, 'page': '$page'},
          },
        ) as Map,
      );

  Future<void> _refresh() async {
    final data = _load();
    setState(() {
      _data = data;
    });
    // FutureBuilder presents errors, including refresh failures.
    try {
      await data;
    } catch (_) {}
  }

  void _history(Map<String, dynamic> player, String type) => openPage(
    context,
    PlayerHistoryPage(
      api: widget.auth.api,
      player: {...player, 'sezione': widget.section},
      initialType: type,
      loadMatches: (type, page) async {
        final result = await _load(type: type, page: page);
        if (result['player'] == null) {
          throw const ApiError('Il profilo giocatore non è più collegato.');
        }
        return result;
      },
    ),
  );

  @override
  Widget build(BuildContext context) {
    if (widget.auth.user == null) {
      return _message(
        icon: Icons.badge_outlined,
        title: 'La tua storia, in campo.',
        text: 'Accedi per ritrovare la tua scheda giocatore, le squadre e tutte le tue prestazioni.',
        action: FilledButton.icon(
          onPressed: widget.auth.busy ? null : widget.auth.login,
          icon: const Icon(Icons.login),
          label: Text(
            widget.auth.busy ? 'Accesso in corso…' : 'Accedi al tuo profilo',
          ),
        ),
        error: widget.auth.error,
      );
    }
    return FutureBuilder<Map<String, dynamic>>(
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(
            child: CircularProgressIndicator(
              semanticsLabel: 'Caricamento statistiche personali',
            ),
          );
        }
        if (snapshot.hasError) {
          return _message(
            icon: Icons.cloud_off_outlined,
            title: 'Un attimo di pausa',
            text: snapshot.error is ApiError
                ? snapshot.error.toString()
                : 'Impossibile caricare le statistiche. Riprova tra poco.',
            action: FilledButton.icon(
              onPressed: _refresh,
              icon: const Icon(Icons.refresh),
              label: const Text('Riprova'),
            ),
          );
        }
        final data = snapshot.data!;
        if (data['player'] == null) {
          return _message(
            icon: Icons.person_add_alt_1_outlined,
            title: 'La tua scheda ti aspetta',
            text: 'Non hai ancora un profilo giocatore collegato. Contatta un amministratore per associare il tuo account al giocatore e ritrovare le tue statistiche.',
            action: widget.onAccount == null
                ? null
                : OutlinedButton.icon(
                    onPressed: widget.onAccount,
                    icon: const Icon(Icons.manage_accounts_outlined),
                    label: const Text('Il mio account'),
                  ),
            refresh: true,
          );
        }
        final player = Map<String, dynamic>.from(data['player'] as Map);
        final teams = TosApi.rows(data['teams']);
        return PlayerDashboard(
          api: widget.auth.api,
          player: player,
          teams: teams,
          awards: TosApi.rows(data['awards']),
          section: widget.section,
          refresh: _refresh,
          history: (type) => _history(player, type),
          openTeam: (team) => openPage(
            context,
            TeamPage(
              api: widget.auth.api,
              slug: '${team['torneo']}',
              team: team,
            ),
          ),
        );
      },
    );
  }

  Widget _message({
    required IconData icon,
    required String title,
    required String text,
    Widget? action,
    String? error,
    bool refresh = false,
  }) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
    children: [
      Container(
        height: 160,
        decoration: BoxDecoration(
          color: siteBlue,
          borderRadius: BorderRadius.circular(26),
          gradient: const LinearGradient(colors: [siteBlue, Color(0xff294865)]),
        ),
        child: Icon(icon, color: Colors.white, size: 72),
      ),
      const SizedBox(height: 28),
      Text(
        title,
        style: const TextStyle(
          fontSize: 30,
          height: 1.1,
          fontWeight: FontWeight.w900,
          color: siteBlue,
        ),
      ),
      const SizedBox(height: 14),
      Text(
        text,
        style: const TextStyle(
          fontSize: 16,
          height: 1.5,
          color: Color(0xff586879),
        ),
      ),
      const SizedBox(height: 24),
      ?action,
      if (refresh)
        TextButton.icon(
          onPressed: _refresh,
          icon: const Icon(Icons.refresh),
          label: const Text('Verifica associazione'),
        ),
      if (error != null)
        Padding(
          padding: const EdgeInsets.only(top: 16),
          child: Text(error, style: const TextStyle(color: siteRed)),
        ),
    ],
  );
}
