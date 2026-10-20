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
  bool _remindersAvailable = false;
  bool _scheduleChangesAvailable = false;
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
        _remindersAvailable = response['reminders_available'] == true;
        _scheduleChangesAvailable = response['schedule_changes_available'] == true;
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
        if (_remindersAvailable)
          DropdownButtonFormField<String>(
            key: ValueKey('${_preferences!["reminder"]}-$_busy'),
            initialValue: _preferences!['reminder'] as String? ?? 'off',
            decoration: const InputDecoration(labelText: 'Promemoria partita'),
            items: const [
              DropdownMenuItem(value: 'off', child: Text('Disattivato')),
              DropdownMenuItem(value: '1h', child: Text('1 ora prima')),
              DropdownMenuItem(value: '2h', child: Text('2 ore prima')),
              DropdownMenuItem(value: '1d', child: Text('Il giorno prima')),
            ],
            onChanged: _busy ? null : (value) {
              if (value != null) _request({'reminder': value});
            },
          ),
        for (final entry in {
          'results': 'Risultati delle partite',
          'comments': 'Risposte ai tuoi commenti',
          if (_scheduleChangesAvailable)
            'schedule_changes': 'Cambi di data, orario o campo',
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
