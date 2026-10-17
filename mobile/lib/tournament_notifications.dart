import 'package:flutter/material.dart';

import 'access_gate.dart';
import 'auth.dart';
import 'push.dart';
import 'home_dashboard.dart' show SiteImage;

class TournamentNotifications extends StatefulWidget {
  const TournamentNotifications({
    super.key,
    required this.slug,
    this.teamLogos = const {},
  });
  final String slug;
  final Map<String, String> teamLogos;

  @override
  State<TournamentNotifications> createState() =>
      _TournamentNotificationsState();
}

class _TournamentNotificationsState extends State<TournamentNotifications> {
  TosAuth? _auth;
  String? _userId;
  bool? _followed;
  bool _all = false;
  List<String> _teams = [], _available = [];
  bool _busy = false;
  String? _error;
  int _generation = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final auth = AppAuthScope.of(context);
    if (_auth != auth) {
      _auth?.removeListener(_changed);
      _auth = auth;
      auth?.addListener(_changed);
      _changed();
    }
  }

  void _changed() {
    final id = _auth?.user?['id']?.toString();
    if (id == _userId) return;
    _userId = id;
    _generation++;
    _followed = null;
    _all = false;
    _teams = [];
    _available = [];
    _busy = false;
    _error = null;
    if (mounted) setState(() {});
    if (id != null) _request();
  }

  Future<void> _request({bool? followed, List<String>? teams}) async {
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _auth!.authorized(
        'api/mobile/v1/follow.php',
        query: followed == null ? {'torneo': widget.slug} : null,
        body: followed == null
            ? null
            : {'torneo': widget.slug, 'followed': followed, 'teams': teams},
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        _followed = result['followed'] == true;
        _all = result['all'] == true;
        _teams = List<String>.from(result['teams'] ?? []);
        _available = List<String>.from(result['available_teams'] ?? []);
      });
    } catch (_) {
      if (!mounted || generation != _generation) return;
      setState(() {
        _error = 'Impossibile aggiornare la preferenza. Riprova.';
      });
    } finally {
      if (mounted && generation == _generation) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _chooseTeams() async {
    final userId = _userId;
    var all = _followed != true || _all;
    final selected = (_followed == true ? _teams : _available).toSet();
    var search = '';
    final choice = await showDialog<({bool all, List<String> teams})>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, update) {
          final colors = Theme.of(context).colorScheme;
          final visible = _available
              .where(
                (team) => team.toLowerCase().contains(search.toLowerCase()),
              )
              .toList();
          final count = all ? _available.length : selected.length;
          return Dialog(
            insetPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 24,
            ),
            clipBehavior: Clip.antiAlias,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(28),
            ),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: 480,
                maxHeight: MediaQuery.sizeOf(context).height * .8,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    padding: const EdgeInsets.fromLTRB(20, 18, 8, 16),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          colors.primaryContainer,
                          colors.surfaceContainerLow,
                        ],
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: colors.surface.withValues(alpha: .7),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(
                            Icons.notifications_active_outlined,
                            color: colors.primary,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Segui le tue squadre',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'Scegli di chi ricevere i risultati',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 12,
                                  color: colors.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          tooltip: 'Chiudi',
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close_rounded),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                    child: TextField(
                      onChanged: (value) => update(() => search = value),
                      decoration: InputDecoration(
                        hintText: 'Cerca squadra',
                        prefixIcon: const Icon(Icons.search_rounded),
                        filled: true,
                        fillColor: colors.surfaceContainerLow,
                        isDense: true,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: CheckboxListTile(
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      tileColor: colors.primaryContainer.withValues(alpha: .3),
                      secondary: Icon(
                        Icons.groups_rounded,
                        color: colors.primary,
                      ),
                      title: const Text(
                        'Tutte le squadre',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                      value: all,
                      onChanged: (value) => update(() {
                        all = value == true;
                        selected.clear();
                        if (all) selected.addAll(_available);
                      }),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        '$count di ${_available.length} selezionate',
                        style: TextStyle(
                          fontSize: 12,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ),
                  Flexible(
                    child: visible.isEmpty
                        ? const Padding(
                            padding: EdgeInsets.all(24),
                            child: Text('Nessuna squadra trovata'),
                          )
                        : ListView.builder(
                            shrinkWrap: true,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: visible.length,
                            itemBuilder: (context, index) {
                              final team = visible[index];
                              final checked = all || selected.contains(team);
                              return AnimatedContainer(
                                duration: const Duration(milliseconds: 180),
                                margin: const EdgeInsets.only(bottom: 6),
                                decoration: BoxDecoration(
                                  color: checked
                                      ? colors.primaryContainer.withValues(
                                          alpha: .18,
                                        )
                                      : colors.surfaceContainerLow,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                    color: checked
                                        ? colors.primary.withValues(alpha: .3)
                                        : Colors.transparent,
                                  ),
                                ),
                                child: Material(
                                  color: Colors.transparent,
                                  borderRadius: BorderRadius.circular(14),
                                  clipBehavior: Clip.antiAlias,
                                  child: CheckboxListTile(
                                    contentPadding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 3,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    secondary: Container(
                                      width: 40,
                                      height: 40,
                                      padding: const EdgeInsets.all(4),
                                      decoration: BoxDecoration(
                                        color: colors.surface,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: SiteImage(
                                        api: _auth!.api,
                                        path: widget.teamLogos[team] ?? '',
                                        width: 32,
                                        height: 32,
                                        fallback: Icons.shield_outlined,
                                        preserveShape: true,
                                      ),
                                    ),
                                    title: Text(
                                      team,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    value: checked,
                                    onChanged: (value) => update(() {
                                      if (all) {
                                        selected.addAll(_available);
                                        all = false;
                                      }
                                      if (value == true) {
                                        selected.add(team);
                                      } else {
                                        selected.remove(team);
                                      }
                                      if (_available.isNotEmpty &&
                                          selected.length ==
                                              _available.length) {
                                        all = true;
                                      }
                                    }),
                                  ),
                                ),
                              );
                            },
                          ),
                  ),
                  const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.pop(context, (
                            all: false,
                            teams: <String>[],
                          )),
                          child: const Text(
                            'Disattiva',
                            style: TextStyle(fontSize: 12),
                          ),
                        ),
                        FilledButton.icon(
                          onPressed: () => Navigator.pop(context, (
                            all: all,
                            teams: selected.toList(),
                          )),
                          icon: const Icon(Icons.check_rounded, size: 18),
                          label: const Text(
                            'Salva',
                            style: TextStyle(fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
    if (!mounted ||
        choice == null ||
        _userId != userId ||
        _auth?.user?['id']?.toString() != userId) {
      return;
    }
    await _request(
      followed: choice.all || choice.teams.isNotEmpty,
      teams: choice.all ? null : choice.teams,
    );
  }

  @override
  void dispose() {
    _generation++;
    _auth?.removeListener(_changed);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final push = context
        .dependOnInheritedWidgetOfExactType<PushScope>()
        ?.notifier;
    final colors = Theme.of(context).colorScheme;
    final followed = _followed == true;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: followed
                ? colors.primaryContainer.withValues(alpha: .35)
                : colors.surfaceContainerLow,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: followed
                  ? colors.primary.withValues(alpha: .3)
                  : colors.outlineVariant,
            ),
          ),
          child: Row(
            children: [
              Icon(
                followed
                    ? Icons.notifications_active_outlined
                    : Icons.notifications_none_rounded,
                size: 20,
                color: colors.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Notifiche risultati',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (_userId == null)
                      Text(
                        'Accedi per seguire il torneo',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                  ],
                ),
              ),
              if (_userId != null) ...[
                const SizedBox(width: 8),
                Tooltip(
                  message: followed
                      ? 'Scegli le squadre da seguire'
                      : 'Ricevi gli aggiornamenti sui risultati',
                  child: FilledButton.tonal(
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      minimumSize: const Size(0, 40),
                      textStyle: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    onPressed: _busy
                        ? null
                        : _followed == null
                        ? () => _request()
                        : _chooseTeams,
                    child: _busy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(
                            followed
                                ? _all
                                      ? 'Tutte'
                                      : _teams.length == 1
                                      ? '1 squadra'
                                      : '${_teams.length} squadre'
                                : _followed == null
                                ? 'Riprova'
                                : 'Segui',
                          ),
                  ),
                ),
                if (followed &&
                    push?.supported == true &&
                    push?.enabled != true)
                  IconButton(
                    tooltip: 'Attiva notifiche sul telefono',
                    onPressed: () => showModalBottomSheet<void>(
                      context: context,
                      isScrollControlled: true,
                      showDragHandle: true,
                      builder: (_) => SafeArea(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(20),
                          child: PushScope(
                            controller: push!,
                            child: const NativePushSettings(),
                          ),
                        ),
                      ),
                    ),
                    icon: const Icon(Icons.settings_outlined, size: 20),
                  ),
              ],
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6, left: 12),
            child: Text(
              _error!,
              style: TextStyle(fontSize: 12, color: colors.error),
            ),
          ),
      ],
    );
  }
}
