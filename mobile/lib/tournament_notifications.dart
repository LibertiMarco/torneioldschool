import 'package:flutter/material.dart';

import 'access_gate.dart';
import 'auth.dart';
import 'push.dart';

class TournamentNotifications extends StatefulWidget {
  const TournamentNotifications({super.key, required this.slug});
  final String slug;

  @override
  State<TournamentNotifications> createState() =>
      _TournamentNotificationsState();
}

class _TournamentNotificationsState extends State<TournamentNotifications> {
  TosAuth? _auth;
  String? _userId;
  bool? _followed;
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
    _busy = false;
    _error = null;
    if (mounted) setState(() {});
    if (id != null) _request();
  }

  Future<void> _request({bool? followed}) async {
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
            : {'torneo': widget.slug, 'followed': followed},
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        _followed = result['followed'] == true;
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
                    Text(
                      _userId == null
                          ? 'Accedi per seguire il torneo'
                          : 'A fine partita',
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
                      ? 'Non seguire più il torneo'
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
                        : () => _request(
                            followed: _followed == null ? null : !followed,
                          ),
                    child: _busy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(
                            followed
                                ? 'Seguito'
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
