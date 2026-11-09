import 'package:flutter/material.dart';

import 'auth.dart';
import 'api.dart';
import 'lineup_ad_slot.dart';

class AdminRolePage extends StatefulWidget {
  const AdminRolePage({super.key, required this.auth, required this.user});
  final TosAuth auth;
  final Map<String, dynamic> user;
  @override
  State<AdminRolePage> createState() => _AdminRolePageState();
}

class _AdminRolePageState extends State<AdminRolePage> {
  late String role = '${widget.user['ruolo']}';
  bool busy = false;
  String? error;
  Future<void> save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.authorized(
        'api/mobile/v1/admin/user_role.php',
        body: {
          'id': int.parse('${widget.user['id']}'),
          'role': role,
          'expected_role': widget.user['ruolo'],
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Profilo aggiornato.')));
        Navigator.pop(context);
      }
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is ApiError
              ? failure.message
              : 'Salvataggio non riuscito. Riprova.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Profilo account')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const LineupAdSlot(),
        const SizedBox(height: 16),
        Text(
          '${widget.user['nome']} ${widget.user['cognome']}',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        Text('${widget.user['email']}'),
        const SizedBox(height: 20),
        DropdownButtonFormField<String>(
          initialValue: role,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Profilo',
            prefixIcon: Icon(Icons.badge_outlined),
          ),
          items: [
            for (final value in ['user', 'grafico', 'admin', 'sysadmin'])
              DropdownMenuItem(
                value: value,
                child: Text(
                  {
                    'user': 'Utente',
                    'grafico': 'Grafico',
                    'admin': 'Amministratore',
                    'sysadmin': 'Sysadmin',
                  }[value]!,
                ),
              ),
          ],
          onChanged: busy ? null : (value) => setState(() => role = value!),
        ),
        const SizedBox(height: 16),
        if (error != null)
          Text(
            error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        FilledButton.icon(
          onPressed: busy || role == widget.user['ruolo'] ? null : save,
          icon: const Icon(Icons.save_outlined),
          label: Text(busy ? 'Salvataggio...' : 'Salva profilo'),
        ),
        const SizedBox(height: 20),
        const LineupAdSlot(),
      ],
    ),
  );
}
