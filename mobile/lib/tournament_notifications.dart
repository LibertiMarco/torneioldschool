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
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Notifiche risultati',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Segui questo torneo per ricevere gli aggiornamenti quando vengono confermati i risultati delle partite.',
            ),
            if (_userId == null)
              const Text('Accedi dal tuo account per seguire il torneo.')
            else ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : () => _request(
                        followed: _followed == null ? null : !_followed!,
                      ),
                icon: Icon(
                  _followed == true
                      ? Icons.notifications_active
                      : Icons.notifications_none,
                ),
                label: Text(
                  _busy
                      ? 'Aggiornamento…'
                      : _followed == true
                      ? 'Non seguire più il torneo'
                      : _followed == null
                      ? 'Riprova'
                      : 'Segui il torneo',
                ),
              ),
              if (_followed == true &&
                  push?.supported == true &&
                  push?.enabled != true)
                const NativePushSettings(),
              if (_followed == true && push?.supported != true)
                const Text(
                  'Per ricevere le notifiche sul telefono, attivale dall’app Android o iPhone.',
                ),
            ],
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      ),
    );
  }
}
