import 'package:flutter/material.dart';

import 'auth.dart';
import 'api.dart';

class AdminUsersPage extends StatefulWidget {
  const AdminUsersPage({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<AdminUsersPage> createState() => _AdminUsersPageState();
}

class _AdminUsersPageState extends State<AdminUsersPage> {
  int page = 1;
  late Future<dynamic> users;
  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    users = widget.auth.authorized(
      'api/mobile/v1/admin/users.php',
      query: {'page': '$page'},
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<dynamic>(
    future: users,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Center(child: CircularProgressIndicator());
      }
      if (snapshot.hasError) {
        return Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                snapshot.error is ApiError
                    ? snapshot.error.toString()
                    : 'Servizio temporaneamente non disponibile.',
              ),
              FilledButton(
                onPressed: () => setState(_load),
                child: const Text('Riprova'),
              ),
            ],
          ),
        );
      }
      final rows = TosApi.rows(snapshot.data['users']);
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Utenti',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          if (rows.isEmpty) const Text('Nessun utente disponibile.'),
          ...rows.map(
            (user) => Card(
              child: ListTile(
                leading: const Icon(Icons.person_outline),
                title: Text('${user['nome']} ${user['cognome']}'),
                subtitle: Text('${user['email']}'),
                trailing: Text('${user['ruolo']}'),
                onTap: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          AdminFeaturesPage(auth: widget.auth, user: user),
                    ),
                  );
                  if (mounted) setState(_load);
                },
              ),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              TextButton(
                onPressed: page == 1
                    ? null
                    : () => setState(() {
                        page--;
                        _load();
                      }),
                child: const Text('Precedente'),
              ),
              Text('Pagina $page'),
              TextButton(
                onPressed: snapshot.data['has_more'] == true
                    ? () => setState(() {
                        page++;
                        _load();
                      })
                    : null,
                child: const Text('Successiva'),
              ),
            ],
          ),
        ],
      );
    },
  );
}

class AdminFeaturesPage extends StatefulWidget {
  const AdminFeaturesPage({super.key, required this.auth, required this.user});
  final TosAuth auth;
  final Map<String, dynamic> user;
  @override
  State<AdminFeaturesPage> createState() => _AdminFeaturesPageState();
}

class _AdminFeaturesPageState extends State<AdminFeaturesPage> {
  Map<String, bool> flags = {};
  List<Map<String, dynamic>> definitions = [];
  String? revision;
  String? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    _load();
  }

  void _accept(dynamic data) {
    definitions = TosApi.rows(data['definitions']);
    flags = Map<String, bool>.from(data['user']['feature_flags'] as Map);
    revision = data['revision'] as String;
  }

  Future<void> _load() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final data = await widget.auth.authorized(
        'api/mobile/v1/admin/user_features.php',
        query: {'id': '${widget.user['id']}'},
      );
      if (mounted) setState(() => _accept(data));
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is ApiError
              ? failure.message
              : 'Caricamento non riuscito.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _save() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Salvare le abilitazioni?'),
        content: Text(
          widget.auth.api.preview
              ? 'La modifica riguarda soltanto i dati dimostrativi.'
              : 'La modifica sarà valida anche sul sito per ${widget.user['email']}.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annulla'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Salva'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final data = await widget.auth.authorized(
        'api/mobile/v1/admin/user_features.php',
        body: {
          'id': int.parse('${widget.user['id']}'),
          'feature_flags': flags,
          'revision': revision,
        },
      );
      if (!mounted) return;
      setState(() => _accept(data));
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Abilitazioni salvate.')));
    } catch (failure) {
      if (mounted) {
        setState(() {
          error = failure is ApiError
              ? failure.message
              : 'Salvataggio non riuscito.';
          if (failure is ApiError && failure.status == 409) revision = null;
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Funzioni account')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text(
          '${widget.user['nome']} ${widget.user['cognome']}',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        Text('${widget.user['email']}'),
        const SizedBox(height: 12),
        const Text(
          'Admin e sysadmin possono sempre accedere alle funzioni, anche se disabilitate qui.',
        ),
        const SizedBox(height: 24),
        if (busy) const LinearProgressIndicator(),
        ...definitions.map(
          (definition) => SwitchListTile(
            title: Text('${definition['label']}'),
            subtitle: Text('${definition['description']}'),
            value: flags[definition['key']] == true,
            onChanged: busy || revision == null
                ? null
                : (value) =>
                      setState(() => flags['${definition['key']}'] = value),
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        FilledButton(
          onPressed: busy || revision == null ? null : _save,
          child: const Text('Salva abilitazioni'),
        ),
        TextButton(
          onPressed: busy ? null : _load,
          child: const Text('Ricarica dal server'),
        ),
      ],
    ),
  );
}
