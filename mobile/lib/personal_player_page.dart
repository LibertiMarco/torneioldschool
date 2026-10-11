import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'details.dart' show TeamPage;
import 'home_dashboard.dart' show SiteImage, openPage;
import 'player_history.dart';
import 'theme.dart';

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
      player: player,
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
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
            children: [
              _hero(player),
              const SizedBox(height: 24),
              _heading(
                'Le mie statistiche',
                'Il tuo percorso · ${widget.section == 'calcio' ? 'Calcio' : 'Esport'}',
              ),
              const SizedBox(height: 14),
              LayoutBuilder(
                builder: (context, constraints) {
                  final width = MediaQuery.textScalerOf(context).scale(16) > 24
                      ? constraints.maxWidth
                      : (constraints.maxWidth - 12) / 2;
                  return Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      _metric(
                        'Gol',
                        player['reti'],
                        Icons.sports_soccer,
                        width,
                        () => _history(player, 'gol'),
                        prominent: true,
                      ),
                      _metric(
                        'Presenze',
                        player['presenze'],
                        Icons.assignment_outlined,
                        width,
                        () => _history(player, 'presenze'),
                      ),
                      if (_number(player['media_voti']) > 0)
                        _metric(
                          'Media voto',
                          player['media_voti'],
                          Icons.star_outline,
                          width,
                          null,
                        ),
                      _metric(
                        'Squadre',
                        teams.length,
                        Icons.shield_outlined,
                        width,
                        null,
                      ),
                    ],
                  );
                },
              ),
              const SizedBox(height: 24),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (player['assist'] != null)
                    _badge('Assist: ${player['assist']}'),
                  if (player['gialli'] != null)
                    _badge('Ammonizioni: ${player['gialli']}'),
                  if (player['rossi'] != null)
                    _badge('Espulsioni: ${player['rossi']}'),
                ],
              ),
              const SizedBox(height: 16),
              _historyLink(player),
              if (teams.isNotEmpty) ...[
                const SizedBox(height: 24),
                _distribution(teams),
              ],
              const SizedBox(height: 28),
              _heading(
                'Le mie squadre',
                'Squadre e tornei associati al tuo profilo',
              ),
              const SizedBox(height: 14),
              if (teams.isEmpty)
                const Text(
                  'Non risultano squadre associate in questa sezione.',
                ),
              for (final team in teams) _team(team),
            ],
          ),
        );
      },
    );
  }

  num _number(dynamic value) => num.tryParse('$value') ?? 0;
  String _value(dynamic value) =>
      value == null || '$value'.isEmpty ? '—' : '$value';

  Widget _heading(String title, String subtitle) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w800,
          color: siteBlue,
          letterSpacing: -.5,
        ),
      ),
      const SizedBox(height: 4),
      Text(
        subtitle,
        style: const TextStyle(color: Color(0xff586879), fontSize: 13),
      ),
    ],
  );

  Widget _hero(Map<String, dynamic> player) => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(26),
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [siteBlue, Color(0xff294865), siteBlue],
      ),
    ),
    child: Stack(
      children: [
        const Positioned(
          right: -38,
          top: -48,
          child: ExcludeSemantics(
            child: Icon(
              Icons.sports_soccer,
              size: 230,
              color: Color(0x0fffffff),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  SizedBox(
                    width: 20,
                    height: 3,
                    child: ColoredBox(color: siteRed),
                  ),
                  SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      'OLD SCHOOL / PLAYER',
                      style: TextStyle(
                        color: Colors.white70,
                        letterSpacing: 2,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 26),
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: Colors.white30),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(17),
                  child: SiteImage(
                    api: widget.auth.api,
                    path: '${player['foto'] ?? ''}',
                    width: 104,
                    height: 120,
                    fallback: Icons.person_outline,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                '${player['nome'] ?? ''} ${player['cognome'] ?? ''}'.trim(),
                style: const TextStyle(
                  fontSize: 32,
                  height: 1.05,
                  letterSpacing: -1,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if ('${player['ruolo'] ?? ''}'.trim().isNotEmpty)
                    _badge('${player['ruolo']}', dark: true),
                  _badge('Il mio profilo', dark: true),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _badge(String label, {bool dark = false}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: dark
          ? Colors.white.withValues(alpha: .12)
          : const Color(0xffe8edf5),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w600,
        color: dark ? Colors.white : siteBlue,
      ),
    ),
  );

  Widget _metric(
    String label,
    dynamic value,
    IconData icon,
    double width,
    VoidCallback? onTap, {
    bool prominent = false,
  }) => SizedBox(
    width: width,
    child: Material(
      color: prominent ? siteRed : Colors.white,
      borderRadius: BorderRadius.circular(20),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 22, color: prominent ? Colors.white70 : siteRed),
              const SizedBox(height: 16),
              Text(
                _value(value),
                style: TextStyle(
                  fontSize: 36,
                  height: 1.1,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -1,
                  color: prominent ? Colors.white : siteBlue,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      label,
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        color: prominent ? Colors.white : siteBlue,
                      ),
                    ),
                  ),
                  if (onTap != null)
                    Icon(
                      Icons.arrow_forward,
                      size: 16,
                      color: prominent ? Colors.white : siteBlue,
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    ),
  );

  Widget _historyLink(Map<String, dynamic> player) => Material(
    color: siteBlue,
    borderRadius: BorderRadius.circular(20),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: () => _history(player, 'presenze'),
      child: const Padding(
        padding: EdgeInsets.all(20),
        child: Row(
          children: [
            Icon(Icons.history, color: Colors.white),
            SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Storico prestazioni',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 17,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Partite giocate, gol e referti',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
            SizedBox(width: 8),
            Icon(Icons.arrow_forward, color: Colors.white, size: 20),
          ],
        ),
      ),
    ),
  );

  Widget _distribution(List<Map<String, dynamic>> teams) {
    final maximum = teams.fold<num>(
      0,
      (max, team) => _number(team['reti']) > max ? _number(team['reti']) : max,
    );
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Gol per squadra',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: siteBlue,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Il tuo contributo nei tornei',
            style: TextStyle(fontSize: 12, color: Color(0xff586879)),
          ),
          const SizedBox(height: 20),
          for (final team in teams)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Semantics(
                label:
                    '${team['nome']}, ${team['torneo_nome'] ?? team['torneo']}: ${_value(team['reti'])} gol',
                excludeSemantics: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            '${team['nome']}',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          _value(team['reti']),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: siteRed,
                          ),
                        ),
                      ],
                    ),
                    Text(
                      '${team['torneo_nome'] ?? team['torneo'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xff586879),
                      ),
                    ),
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: maximum > 0
                            ? (_number(team['reti']) / maximum)
                                  .clamp(0, 1)
                                  .toDouble()
                            : 0,
                        minHeight: 6,
                        backgroundColor: const Color(0xffedf0f4),
                        color: siteRed,
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

  Widget _team(Map<String, dynamic> team) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              SiteImage(
                api: widget.auth.api,
                path: '${team['logo'] ?? ''}',
                width: 44,
                height: 44,
                preserveShape: true,
                fallback: Icons.shield_outlined,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${team['nome'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${team['torneo_nome'] ?? team['torneo'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xff586879),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 16,
            runSpacing: 10,
            children: [
              Text(
                'Gol: ${_value(team['reti'])}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              Text(
                'Presenze: ${_value(team['presenze'])}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              if (_number(team['media_voti']) > 0)
                Text('Media voto: ${team['media_voti']}'),
              if (_number(team['gialli']) > 0)
                Text('Ammonizioni: ${team['gialli']}'),
              if (_number(team['rossi']) > 0)
                Text('Espulsioni: ${team['rossi']}'),
            ],
          ),
          if (team['diffidato'] == true || '${team['is_captain']}' == '1') ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if ('${team['is_captain']}' == '1') _badge('Capitano'),
                if (team['diffidato'] == true) _badge('Diffidato'),
              ],
            ),
          ],
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: () => openPage(
              context,
              TeamPage(
                api: widget.auth.api,
                slug: '${team['torneo']}',
                team: team,
              ),
            ),
            icon: const Icon(Icons.arrow_forward, size: 18),
            label: const Text('Vedi squadra'),
          ),
        ],
      ),
    ),
  );

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
