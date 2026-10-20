import 'package:flutter/material.dart';

import 'auth.dart';

class NotificationPreferences extends StatefulWidget {
  const NotificationPreferences({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<NotificationPreferences> createState() =>
      _NotificationPreferencesState();
}

class _NotificationPreferencesState extends State<NotificationPreferences> {
  Map<String, dynamic>? _preferences;
  bool _busy = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    _request();
  }

  Future<void> _request([Map<String, dynamic>? patch]) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.auth.authorized(
        'api/mobile/v1/notification_preferences.php',
        body: patch,
      );
      if (mounted) {
        setState(
          () => _preferences = Map<String, dynamic>.from(
            response['preferences'] as Map,
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Impossibile aggiornare le preferenze. Riprova.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text(
        'Scegli gli avvisi per il tuo account. I risultati riguardano le tue partite e le squadre o i tornei che segui.',
      ),
      if (_preferences != null) ...[
        for (final entry in {
          'results': 'Risultati delle partite',
          'comments': 'Risposte ai tuoi commenti',
        }.entries)
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(entry.value),
            value: _preferences![entry.key] == true,
            onChanged: _busy ? null : (value) => _request({entry.key: value}),
          ),
      ],
      if (_busy) const LinearProgressIndicator(),
      if (_error != null) ...[
        Text(
          _error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
        TextButton(
          onPressed: _busy ? null : () => _request(),
          child: const Text('Ricarica preferenze'),
        ),
      ],
    ],
  );
}
