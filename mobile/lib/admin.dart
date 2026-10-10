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
