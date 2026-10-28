import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';

class DeleteAccountPage extends StatefulWidget {
  const DeleteAccountPage({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<DeleteAccountPage> createState() => _DeleteAccountPageState();
}

class _DeleteAccountPageState extends State<DeleteAccountPage> {
  final _password = TextEditingController();
  bool _confirmed = false, _busy = false;
  String? _error;

  @override
  void dispose() {
    _password.clear();
    _password.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    if (_busy || !_confirmed) return;
    if (_password.text.isEmpty) {
      setState(() => _error = 'Inserisci la password attuale.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.auth.deleteAccount(_password.text);
      _password.clear();
      if (mounted) {
        setState(() => _busy = false);
        Navigator.of(context).pop();
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = error is ApiError
              ? error.message
              : 'Cancellazione non riuscita. Riprova.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_busy,
    child: Scaffold(
      appBar: AppBar(title: const Text('Elimina account')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text(
            'La cancellazione del tuo account e dei relativi consensi è irreversibile. Non potrai più accedere con queste credenziali.',
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _password,
            enabled: !_busy,
            obscureText: true,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'Password attuale'),
          ),
          CheckboxListTile(
            title: const Text('Confermo di voler eliminare il mio account'),
            value: _confirmed,
            onChanged: _busy
                ? null
                : (value) => setState(() => _confirmed = value == true),
          ),
          if (_error != null) Semantics(liveRegion: true, child: Text(_error!)),
          FilledButton(
            onPressed: _busy || !_confirmed ? null : _delete,
            child: Text(_busy ? 'Attendi...' : 'Elimina definitivamente'),
          ),
        ],
      ),
    ),
  );
}
