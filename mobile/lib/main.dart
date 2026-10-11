import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'staff_dashboard.dart';
import 'account_page.dart';
import 'personal_player_page.dart';

import 'demo.dart';
import 'theme.dart';
import 'section_selector.dart';
import 'tournament_list.dart';
import 'home_dashboard.dart';
import 'browser_runtime_stub.dart'
    if (dart.library.js_interop) 'browser_runtime_web.dart';

export 'tournament_page.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  if (const bool.fromEnvironment('TOS_PREVIEW')) {
    final api = DemoApi();
    runApp(TosApp(api: api, auth: DemoAuth(api)));
    return;
  }
  if (const bool.fromEnvironment('TOS_WEB_PREVIEW')) {
    final auth = createBrowserAuth();
    runApp(TosApp(api: auth.api, auth: auth));
    return;
  }
  final api = TosApi(
    baseUrl: Uri.parse(
      const String.fromEnvironment(
        'TOS_BASE_URL',
        defaultValue: 'https://torneioldschool.it',
      ),
    ),
  );
  runApp(TosApp(api: api, auth: TosAuth(api)));
}

class TosApp extends StatelessWidget {
  const TosApp({super.key, required this.api, required this.auth});
  final TosApi api;
  final TosAuth auth;
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Tornei Old School',
    debugShowCheckedModeBanner: false,
    builder: api.preview
        ? (context, child) => Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Column(
                children: [
                  Material(
                    color: const Color(0xffffe6a7),
                    child: SafeArea(
                      bottom: false,
                      child: SizedBox(
                        width: double.infinity,
                        child: Padding(
                          padding: const EdgeInsets.all(10),
                          child: Text(
                            'Anteprima · dati dimostrativi',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.labelLarge,
                          ),
                        ),
                      ),
                    ),
                  ),
                  Expanded(child: child!),
                ],
              ),
            ),
          )
        : null,
    theme: siteTheme(),
    home: HomePage(api: api, auth: auth),
  );
}

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.api, required this.auth});
  final TosApi api;
  final TosAuth auth;
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int page = 0;
  String section = 'calcio';
  @override
  void initState() {
    super.initState();
    widget.auth.addListener(_authChanged);
    widget.auth.restore();
  }

  void _authChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.auth.removeListener(_authChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    final hasStaffAccess = auth.admin || auth.graphics;
    final selectedPage = page == 4 && !hasStaffAccess ? 3 : page;
    return Scaffold(
      appBar: AppBar(
        backgroundColor: selectedPage == 0 ? siteBackground : siteBlue,
        foregroundColor: selectedPage == 0 ? siteBlue : Colors.white,
        elevation: 0,
        title: Row(
          children: [
            Image.asset('assets/logo_old_school.png', width: 34, height: 34),
            const SizedBox(width: 12),
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: selectedPage == 0
                    ? const Text(
                        'TORNEI OLD SCHOOL',
                        maxLines: 1,
                        style: TextStyle(
                          fontSize: 16,
                          letterSpacing: 2,
                          fontWeight: FontWeight.w900,
                        ),
                      )
                    : const Text('Tornei Old School', maxLines: 1),
              ),
            ),
          ],
        ),
      ),
      body: selectedPage <= 2
          ? Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: SectionSelector(
                    section: section,
                    onChanged: (value) => setState(() => section = value),
                  ),
                ),
                Expanded(
                  child: selectedPage == 0
                      ? HomeDashboard(
                          key: ValueKey('home-$section'),
                          api: widget.api,
                          section: section,
                          onTournaments: () => setState(() => page = 1),
                        )
                      : selectedPage == 1
                      ? TournamentList(
                          key: ValueKey('tournaments-$section'),
                          api: widget.api,
                          section: section,
                        )
                      : PersonalPlayerPage(
                          auth: auth,
                          section: section,
                          onAccount: () => setState(() => page = 3),
                        ),
                ),
              ],
            )
          : selectedPage == 3
          ? auth.user == null
                ? _account(auth)
                : AccountPage(auth: auth, section: section)
          : StaffDashboard(auth: auth),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedPage,
        onDestinationSelected: (value) => setState(() => page = value),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            label: 'Home',
          ),
          const NavigationDestination(
            icon: Icon(Icons.emoji_events_outlined),
            label: 'Tornei',
          ),
          const NavigationDestination(
            icon: Icon(Icons.badge_outlined),
            selectedIcon: Icon(Icons.badge),
            label: 'Profilo',
          ),
          const NavigationDestination(
            icon: Icon(Icons.manage_accounts_outlined),
            label: 'Account',
          ),
          if (hasStaffAccess)
            const NavigationDestination(
              icon: Icon(Icons.admin_panel_settings_outlined),
              label: 'Gestione',
            ),
        ],
      ),
    );
  }

  Widget _account(TosAuth auth) => ListView(
    padding: const EdgeInsets.all(24),
    children: [
      const Icon(Icons.account_circle_outlined, size: 72),
      const SizedBox(height: 24),
      if (auth.user == null) ...[
        const Text(
          'Il tuo account Old School',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        const Text('Accedi con lo stesso account che utilizzi sul sito.'),
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: auth.busy ? null : auth.login,
          icon: const Icon(Icons.login),
          label: Text(
            auth.busy
                ? 'Accesso in corso…'
                : widget.api.preview
                ? 'Accedi come admin demo'
                : 'Accedi',
          ),
        ),
      ] else ...[
        Text(
          '${auth.user!['nome']} ${auth.user!['cognome']}',
          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Text('${auth.user!['email']}'),
        const SizedBox(height: 16),
        Text('Ruolo: ${auth.user!['ruolo']}'),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: auth.busy ? null : auth.logout,
          icon: const Icon(Icons.logout),
          label: const Text('Esci'),
        ),
      ],
      if (auth.error != null)
        Padding(
          padding: const EdgeInsets.only(top: 16),
          child: Text(
            auth.error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
    ],
  );
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel({super.key, required this.error, required this.retry});
  final Object error;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 40),
          const SizedBox(height: 16),
          Text(
            error is ApiError
                ? error.toString()
                : 'Servizio temporaneamente non disponibile.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: retry, child: const Text('Riprova')),
        ],
      ),
    ),
  );
}
