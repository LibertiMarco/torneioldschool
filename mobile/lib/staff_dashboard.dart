import 'package:flutter/material.dart';

import 'admin.dart';
import 'auth.dart';
import 'external_link_button.dart';
import 'theme.dart';
import 'staff_tool_embed_stub.dart'
    if (dart.library.js_interop) 'staff_tool_embed_web.dart';

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
    'Collega profili e giocatori',
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

class StaffDashboard extends StatefulWidget {
  const StaffDashboard({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<StaffDashboard> createState() => _StaffDashboardState();
}

class _StaffDashboardState extends State<StaffDashboard> {
  String category = 'Tutti';
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
        ? [...adminTools, ...graphicsTools]
        : graphicsTools;
    final visible = tools.where(
      (tool) => category == 'Tutti' || tool.category == category,
    );
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            gradient: const LinearGradient(
              colors: [Color(0xff10263d), Color(0xff164d69)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            border: Border.all(color: const Color(0xff38718a)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.dashboard_customize_outlined,
                color: Color(0xff7ee9ee),
                size: 30,
              ),
              const SizedBox(height: 16),
              const Text(
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
                auth.admin ? 'Dashboard amministratore' : 'Generatore grafiche',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 25,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                auth.admin
                    ? 'Il tuo centro di gestione, a portata di tap.'
                    : 'Scegli il formato e crea la prossima grafica.',
                style: const TextStyle(color: Color(0xffc7dce6), height: 1.4),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        if (auth.admin) ...[
          _StaffShortcut(
            title: 'Gestione account',
            subtitle: 'Utenti e abilitazioni',
            icon: Icons.manage_accounts_outlined,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: const Text('Gestione account')),
                  body: AdminUsersPage(auth: auth),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          _StaffShortcut(
            title: 'Generatore grafiche',
            subtitle: 'Matchday, risultati e classifiche',
            icon: Icons.auto_awesome_outlined,
            onTap: () => setState(() => category = 'Grafiche'),
          ),
          const SizedBox(height: 22),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final value in [
                'Tutti',
                'Sport',
                'Account',
                'Contenuti',
                'Grafiche',
                'Extra',
              ])
                ChoiceChip(
                  label: Text(value),
                  selected: category == value,
                  onSelected: (_) => setState(() => category = value),
                  selectedColor: const Color(0xffd8f3f5),
                  side: BorderSide(
                    color: category == value
                        ? const Color(0xff45a5b3)
                        : const Color(0xffdce3eb),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 18),
        ],
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = MediaQuery.textScalerOf(context).scale(15) > 22.5
                ? 1
                : constraints.maxWidth >= 520
                ? 3
                : 2;
            // Wrap lets each tool grow naturally, including with enlarged text.
            return Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (final tool in visible)
                  SizedBox(
                    width:
                        (constraints.maxWidth - 10 * (columns - 1)) / columns,
                    child: Material(
                      color: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18),
                        side: const BorderSide(color: Color(0xffdce5ed)),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: () => openTool(tool),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xffeaf5f8),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(
                                  tool.icon,
                                  color: const Color(0xff17617b),
                                ),
                              ),
                              const SizedBox(height: 14),
                              Text(
                                tool.title,
                                style: const TextStyle(
                                  color: siteBlue,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 15,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                tool.description,
                                style: const TextStyle(
                                  color: Color(0xff637487),
                                  fontSize: 12,
                                  height: 1.4,
                                ),
                              ),
                              const SizedBox(height: 12),
                              const Align(
                                alignment: Alignment.centerRight,
                                child: Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 18,
                                  color: Color(0xff17617b),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ],
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
  Widget build(BuildContext context) => Material(
    color: const Color(0xffedf4f8),
    borderRadius: BorderRadius.circular(18),
    clipBehavior: Clip.antiAlias,
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      leading: Icon(icon, color: siteBlue),
      title: Text(
        title,
        style: const TextStyle(fontWeight: FontWeight.w800, color: siteBlue),
      ),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right, color: siteBlue),
      onTap: onTap,
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
    final source = Uri.parse(tool.path);
    final uri = auth.api.uri(source.path, {
      ...source.queryParameters,
      'embed': '1',
    });
    final fallback = ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Icon(tool.icon, size: 48, color: siteBlue),
        const SizedBox(height: 20),
        Text(tool.description, textAlign: TextAlign.center),
        const SizedBox(height: 20),
        if (auth.api.preview)
          const Padding(
            padding: EdgeInsets.only(bottom: 16),
            child: Text(
              'Gli strumenti di gestione sono disponibili con il tuo account sul sito. Questa demo non modifica i dati.',
              textAlign: TextAlign.center,
            ),
          ),
        ExternalLinkButton(
          uri: uri,
          label: 'Apri ${tool.title}',
          icon: Icons.open_in_new,
        ),
      ],
    );
    return Scaffold(
      appBar: AppBar(title: Text(tool.title)),
      body: !allowed
          ? const Center(child: Text('Area riservata allo staff.'))
          : auth.api.preview
          ? fallback
          : staffToolEmbed(uri: uri, fallback: fallback),
    );
  }
}
