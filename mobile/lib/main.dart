import 'package:flutter/material.dart';

import 'api.dart';
import 'auth.dart';
import 'admin.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
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
    theme: ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff15293e)),
      scaffoldBackgroundColor: const Color(0xfff4f6fb),
    ),
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
  late Future<List<Map<String, dynamic>>> tournaments;
  @override
  void initState() {
    super.initState();
    tournaments = widget.api.tournaments(section);
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

  Future<void> reload() async {
    final next = widget.api.tournaments(section);
    setState(() => tournaments = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    final hasStaffAccess = auth.admin || auth.graphics;
    final selectedPage = page == 2 && !hasStaffAccess ? 1 : page;
    return Scaffold(
      appBar: AppBar(title: const Text('Tornei Old School')),
      body: selectedPage == 0
          ? Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'calcio',
                        label: Text('Calcio'),
                        icon: Icon(Icons.sports_soccer),
                      ),
                      ButtonSegment(
                        value: 'esport',
                        label: Text('Esport'),
                        icon: Icon(Icons.sports_esports),
                      ),
                    ],
                    selected: {section},
                    onSelectionChanged: (value) => setState(() {
                      section = value.first;
                      tournaments = widget.api.tournaments(section);
                    }),
                  ),
                ),
                Expanded(
                  child: FutureBuilder<List<Map<String, dynamic>>>(
                    future: tournaments,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState != ConnectionState.done) {
                        return const Center(child: CircularProgressIndicator());
                      }
                      if (snapshot.hasError) {
                        return ErrorPanel(
                          error: snapshot.error!,
                          retry: () {
                            reload().catchError((_) {});
                          },
                        );
                      }
                      final rows = snapshot.data ?? [];
                      return RefreshIndicator(
                        onRefresh: reload,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                          children: rows.isEmpty
                              ? [
                                  const Padding(
                                    padding: EdgeInsets.all(32),
                                    child: Text('Nessun torneo disponibile.'),
                                  ),
                                ]
                              : rows
                                    .map(
                                      (row) => Card(
                                        child: ListTile(
                                          contentPadding: const EdgeInsets.all(
                                            16,
                                          ),
                                          leading: const Icon(
                                            Icons.emoji_events_outlined,
                                          ),
                                          title: Text(
                                            '${row['nome']}',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          subtitle: Text(
                                            '${row['categoria']} · ${row['stato']}',
                                          ),
                                          trailing: const Icon(
                                            Icons.chevron_right,
                                          ),
                                          onTap: () =>
                                              Navigator.of(context).push(
                                                MaterialPageRoute<void>(
                                                  builder: (_) =>
                                                      TournamentPage(
                                                        api: widget.api,
                                                        tournament: row,
                                                      ),
                                                ),
                                              ),
                                        ),
                                      ),
                                    )
                                    .toList(),
                        ),
                      );
                    },
                  ),
                ),
              ],
            )
          : selectedPage == 1
          ? _account(auth)
          : auth.admin
          ? AdminUsersPage(auth: auth)
          : const Center(
              child: Padding(
                padding: EdgeInsets.all(32),
                child: Text(
                  'Gli strumenti di gestione saranno disponibili con il completamento dell’area amministrativa.',
                ),
              ),
            ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedPage,
        onDestinationSelected: (value) => setState(() => page = value),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.emoji_events_outlined),
            label: 'Tornei',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline),
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
          label: Text(auth.busy ? 'Accesso in corso…' : 'Accedi'),
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

class TournamentPage extends StatefulWidget {
  const TournamentPage({
    super.key,
    required this.api,
    required this.tournament,
  });
  final TosApi api;
  final Map<String, dynamic> tournament;
  @override
  State<TournamentPage> createState() => _TournamentPageState();
}

class _TournamentPageState extends State<TournamentPage> {
  late Future<List<Map<String, dynamic>>> standings;
  late Future<List<Map<String, dynamic>>> matches;
  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    final slug = TosApi.tournamentSlug(widget.tournament);
    standings = widget.api.standings(slug);
    matches = widget.api.matches(slug);
  }

  Widget _list(
    Future<List<Map<String, dynamic>>> future,
    Widget Function(Map<String, dynamic>, int) item,
  ) => FutureBuilder<List<Map<String, dynamic>>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Center(child: CircularProgressIndicator());
      }
      if (snapshot.hasError) {
        return ErrorPanel(error: snapshot.error!, retry: () => setState(_load));
      }
      final rows = snapshot.data ?? [];
      if (rows.isEmpty) {
        return const Center(child: Text('Nessun dato disponibile.'));
      }
      return ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: rows.length,
        itemBuilder: (_, index) => item(rows[index], index),
      );
    },
  );
  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: Text('${widget.tournament['nome']}'),
        bottom: const TabBar(
          tabs: [
            Tab(text: 'Classifica'),
            Tab(text: 'Partite'),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          _list(
            standings,
            (row, index) => Card(
              child: ListTile(
                leading: Text(
                  '${index + 1}',
                  style: const TextStyle(fontSize: 22),
                ),
                title: Text('${row['nome']}'),
                subtitle: Text(
                  '${row['giocate']} giocate · DR ${row['differenza_reti']}${row['girone'] == null ? '' : ' · Girone ${row['girone']}'}',
                ),
                trailing: Text(
                  '${row['punti']} pt',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ),
          _list(
            matches,
            (row, _) => Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${row['data_partita'] ?? 'Data da definire'} · ${row['ora_partita'] ?? ''}',
                    ),
                    const SizedBox(height: 12),
                    Text(
                      '${row['squadra_casa']} – ${row['squadra_ospite']}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      row['giocata'].toString() == '1'
                          ? '${row['gol_casa']} : ${row['gol_ospite']}'
                          : 'Da giocare',
                    ),
                    if (row['campo'] != null) Text('${row['campo']}'),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
