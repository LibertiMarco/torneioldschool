import 'package:flutter/material.dart';

import 'admin.dart';
import 'ads.dart';
import 'auth.dart';
import 'staff_controls.dart';
import 'native_staff_tool.dart';
import 'theme.dart';

class StaffTool {
  const StaffTool(
    this.title,
    this.description,
    this.icon,
    this.path,
    this.category,
  );
  final String title, description, path, category;
  final IconData icon;
}

const graphicsTools = [
  StaffTool(
    'Matchday',
    'Programma della giornata',
    Icons.event_outlined,
    'api/grafiche_settimana.php',
    'Grafiche',
  ),
  StaffTool(
    'Fulltime e MVP',
    'Risultati e migliori in campo',
    Icons.sports_soccer,
    'api/grafiche_post_partita.php',
    'Grafiche',
  ),
  StaffTool(
    'Template',
    'Modelli per i post partita',
    Icons.layers_outlined,
    'api/grafiche_post_partita.php?templates=1',
    'Grafiche',
  ),
  StaffTool(
    'Copertine',
    'Immagini per le partite',
    Icons.image_outlined,
    'api/copertina_partite.php',
    'Grafiche',
  ),
  StaffTool(
    'Classifiche',
    'Graduatorie da condividere',
    Icons.leaderboard_outlined,
    'api/grafiche_classifiche.php',
    'Grafiche',
  ),
  StaffTool(
    'Presentazioni',
    'Le squadre del torneo',
    Icons.groups_outlined,
    'api/grafiche_presentazione.php',
    'Grafiche',
  ),
];
const adminTools = [
  StaffTool(
    'Arbitri',
    'Gestisci i nomi nella picklist',
    Icons.sports_outlined,
    'api/gestione_arbitri.php',
    'Sport',
  ),
  StaffTool(
    'Tornei',
    'Competizioni e impostazioni',
    Icons.emoji_events_outlined,
    'api/gestione_tornei.php',
    'Sport',
  ),
  StaffTool(
    'Calendario e risultati',
    'Partite, referti e punteggi',
    Icons.calendar_month_outlined,
    'api/gestione_partite.php',
    'Sport',
  ),
  StaffTool(
    'Squadre',
    'Rose e dati delle squadre',
    Icons.shield_outlined,
    'api/gestione_squadre.php',
    'Sport',
  ),
  StaffTool(
    'Giocatori',
    'Anagrafiche e statistiche',
    Icons.person_outline,
    'api/gestione_giocatori.php',
    'Sport',
  ),
  StaffTool(
    'Crea giornata',
    'Componi il calendario',
    Icons.playlist_add_outlined,
    'api/crea_giornata_automatica.php',
    'Sport',
  ),
  StaffTool(
    'Video partite',
    'Collega i video alle partite',
    Icons.play_circle_outline,
    'api/sincronizza_video_partite.php',
    'Contenuti',
  ),
  StaffTool(
    'Account giocatore',
    'Richieste e associazioni giocatori',
    Icons.link_outlined,
    'api/gestione_account_giocatore.php',
    'Account',
  ),
  StaffTool(
    'Accesso app',
    'Abilitazioni per l’app',
    Icons.phone_iphone_outlined,
    'admin_mobile.php',
    'Account',
  ),
  StaffTool(
    'Articoli',
    'Notizie e pubblicazioni',
    Icons.article_outlined,
    'api/gestione_blog.php',
    'Contenuti',
  ),
  StaffTool(
    'Staff',
    'La squadra Old School',
    Icons.badge_outlined,
    'api/gestione_staff.php',
    'Contenuti',
  ),
  StaffTool(
    'Albo d’oro',
    'Vincitori e riconoscimenti',
    Icons.workspace_premium_outlined,
    'api/gestione_albo.php',
    'Contenuti',
  ),
  StaffTool(
    'Totocalcio',
    'Pronostici e concorsi',
    Icons.confirmation_number_outlined,
    'api/gestione_totocalcio.php',
    'Extra',
  ),
  StaffTool(
    'Fantacalcio',
    'Gestione del fantacalcio',
    Icons.sports_outlined,
    'api/gestione_fantacalcio.php',
    'Extra',
  ),
];

const accountTool = StaffTool(
  'Gestione account',
  'Utenti e abilitazioni',
  Icons.manage_accounts_outlined,
  'account-users',
  'Account',
);

class StaffDashboard extends StatefulWidget {
  const StaffDashboard({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<StaffDashboard> createState() => _StaffDashboardState();
}

class _StaffDashboardState extends State<StaffDashboard> {
  String category = 'Tutti';
  final search = TextEditingController();
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  void openTool(StaffTool tool) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => StaffToolPage(auth: widget.auth, tool: tool),
    ),
  );
  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    if (!auth.admin && !auth.graphics) {
      return const Center(child: Text('Area riservata allo staff.'));
    }
    final tools = auth.admin
        ? [...adminTools, accountTool, ...graphicsTools]
        : graphicsTools;
    final query = search.text.trim().toLowerCase();
    final visible = tools
        .where(
          (tool) =>
              (category == 'Tutti' || tool.category == category) &&
              '${tool.title} ${tool.description} ${tool.category}'
                  .toLowerCase()
                  .contains(query),
        )
        .toList();
    return StaffTheme(
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const AdaptiveAdBanner(),
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              gradient: LinearGradient(
                colors: Theme.of(context).brightness == Brightness.dark
                    ? [
                        Theme.of(context).colorScheme.surfaceContainer,
                        Theme.of(context).colorScheme.primaryContainer,
                      ]
                    : const [Color(0xff10263d), Color(0xff164d69)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              border: Border.all(color: const Color(0xff38718a)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'AREA STAFF',
                  style: TextStyle(
                    color: Color(0xff7ee9ee),
                    letterSpacing: 2,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  auth.admin
                      ? 'Dashboard amministratore'
                      : 'Generatore grafiche',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  auth.admin
                      ? 'Tornei, risultati, account e contenuti.'
                      : 'Scegli il formato e crea la prossima grafica.',
                  style: TextStyle(color: Color(0xffc7dce6), height: 1.4),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: search,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              labelText: 'Cerca uno strumento',
              hintText: 'Squadre, risultati, grafiche…',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: query.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Cancella ricerca',
                      icon: const Icon(Icons.close),
                      onPressed: () => setState(search.clear),
                    ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
          const SizedBox(height: 18),
          if (auth.admin) ...[
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final value in [
                    'Tutti',
                    'Sport',
                    'Account',
                    'Contenuti',
                    'Grafiche',
                    'Extra',
                  ])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: StaffFilter(
                        label: value,
                        selected: category == value,
                        onSelected: (_) => setState(() => category = value),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],
          Text(
            '${visible.length} ${visible.length == 1 ? 'strumento' : 'strumenti'}${category == 'Tutti' ? '' : ' · $category'}',
            style: Theme.of(context).textTheme.labelLarge,
          ),
          const SizedBox(height: 12),
          if (visible.isEmpty)
            Column(
              children: [
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Nessuno strumento corrisponde alla ricerca.'),
                ),
                OutlinedButton.icon(
                  icon: const Icon(Icons.filter_alt_off_outlined),
                  label: const Text('Reimposta ricerca e filtri'),
                  onPressed: () => setState(() {
                    category = 'Tutti';
                    search.clear();
                  }),
                ),
              ],
            ),
          for (final tool in visible)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _StaffShortcut(
                title: tool.title,
                subtitle: tool.description,
                icon: tool.icon,
                onTap: () => openTool(tool),
              ),
            ),
        ],
      ),
    );
  }
}

class _StaffShortcut extends StatelessWidget {
  const _StaffShortcut({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });
  final String title, subtitle;
  final IconData icon;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => StaffTapSurface(
    onTap: onTap,
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [siteBlue, Color(0xff245c78)]),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Icon(icon, color: Colors.white, size: 20),
      ),
      title: Text(
        title,
        style: TextStyle(
          fontWeight: FontWeight.w700,
          color: Theme.of(context).colorScheme.onSurface,
        ),
      ),
      subtitle: Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
      trailing: const Icon(Icons.arrow_forward_rounded, color: staffAccent),
    ),
  );
}

class StaffToolPage extends StatelessWidget {
  const StaffToolPage({super.key, required this.auth, required this.tool});
  final TosAuth auth;
  final StaffTool tool;
  @override
  Widget build(BuildContext context) {
    final allowed = tool.category == 'Grafiche'
        ? auth.graphics || auth.admin
        : auth.admin;
    if (!allowed) {
      return Scaffold(
        appBar: AppBar(title: Text(tool.title)),
        body: const Center(child: Text('Area riservata allo staff.')),
      );
    }
    if (tool.path == accountTool.path) {
      return StaffTheme(
        child: Scaffold(
          appBar: AppBar(
            leading: Navigator.of(context).canPop()
                ? const StaffBackButton()
                : null,
            title: const Text('Gestione account'),
          ),
          body: AdminUsersPage(auth: auth),
        ),
      );
    }
    return NativeStaffTool(
      auth: auth,
      title: tool.title,
      description: tool.description,
      path: tool.path,
      icon: tool.icon,
      graphicsOnly: tool.category == 'Grafiche',
    );
  }
}
