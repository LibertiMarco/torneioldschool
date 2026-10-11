import 'package:flutter/material.dart';

import 'admin.dart';
import 'auth.dart';
import 'external_link_button.dart';
import 'theme.dart';
import 'staff_dashboard_embed_stub.dart'
    if (dart.library.js_interop) 'staff_dashboard_embed_web.dart';

class StaffDashboard extends StatelessWidget {
  const StaffDashboard({super.key, required this.auth});
  final TosAuth auth;

  @override
  Widget build(BuildContext context) {
    if (!auth.admin && !auth.graphics) {
      return const Center(child: Text('Area riservata allo staff.'));
    }
    final path = auth.admin
        ? 'admin_dashboard.php'
        : 'api/generatore_grafiche.php';
    final fallback = ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text(
          auth.admin ? 'Dashboard amministratore' : 'Generatore grafiche',
          style: const TextStyle(
            fontSize: 26,
            fontWeight: FontWeight.w800,
            color: siteBlue,
          ),
        ),
        const SizedBox(height: 20),
        if (auth.admin) ...[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Strumenti amministrativi',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Tornei, squadre, giocatori, calendario e contenuti.',
                  ),
                  const SizedBox(height: 16),
                  ExternalLinkButton(
                    uri: auth.api.uri('admin_dashboard.php'),
                    label: 'Apri dashboard',
                    icon: Icons.dashboard_outlined,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              leading: const Icon(
                Icons.manage_accounts_outlined,
                color: siteBlue,
              ),
              title: const Text('Gestione account'),
              subtitle: const Text('Utenti e abilitazioni'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => Scaffold(
                    appBar: AppBar(title: const Text('Gestione account')),
                    body: AdminUsersPage(auth: auth),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
        ],
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (auth.admin)
                  const Text(
                    'Generatore grafiche',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                const SizedBox(height: 8),
                const Text(
                  'Matchday, Fulltime, MVP, copertine, classifiche e presentazioni squadre.',
                ),
                const SizedBox(height: 16),
                ExternalLinkButton(
                  uri: auth.api.uri('api/generatore_grafiche.php'),
                  label: 'Apri generatore grafiche',
                  icon: Icons.auto_awesome_outlined,
                ),
              ],
            ),
          ),
        ),
      ],
    );
    return auth.api.preview
        ? fallback
        : staffDashboardEmbed(
            uri: auth.api.uri(path, {'embed': '1'}),
            fallback: fallback,
          );
  }
}
