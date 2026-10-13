import 'package:flutter/material.dart';

import 'api.dart';
import 'external_link_button.dart';
import 'home_dashboard.dart' show SiteImage, openPage;
import 'main.dart' show TournamentPage;

class PlayerAwardsSection extends StatefulWidget {
  const PlayerAwardsSection({
    super.key,
    required this.api,
    this.playerId,
    this.section = 'calcio',
    this.awards,
  });
  final TosApi api;
  final String? playerId;
  final String section;
  final List<Map<String, dynamic>>? awards;
  @override
  State<PlayerAwardsSection> createState() => _PlayerAwardsSectionState();
}

class _PlayerAwardsSectionState extends State<PlayerAwardsSection> {
  ColorScheme get colors => Theme.of(context).colorScheme;
  late Future<List<Map<String, dynamic>>> data;
  void load() {
    data = widget.awards != null
        ? Future.value(widget.awards!)
        : widget.playerId == null || widget.playerId!.isEmpty
        ? Future.value([])
        : widget.api.playerAwards(widget.playerId!, section: widget.section);
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(PlayerAwardsSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.api != widget.api ||
        oldWidget.playerId != widget.playerId ||
        oldWidget.section != widget.section ||
        oldWidget.awards != widget.awards) {
      load();
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        'PALMARÈS',
        style: TextStyle(
          color: colors.secondary,
          fontSize: 10,
          letterSpacing: 2,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 5),
      Text(
        'Premi vinti',
        style: TextStyle(
          color: colors.onSurface,
          fontSize: 25,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 16),
      FutureBuilder<List<Map<String, dynamic>>>(
        future: data,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Caricamento premi',
                ),
              ),
            );
          }
          if (snapshot.hasError) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Premi momentaneamente non disponibili.'),
                TextButton.icon(
                  onPressed: () => setState(load),
                  icon: Icon(Icons.refresh),
                  label: Text('Riprova premi'),
                ),
              ],
            );
          }
          final awards = snapshot.data!;
          if (awards.isEmpty) {
            return Text('Nessun premio vinto in questa sezione.');
          }
          return Column(children: [for (final award in awards) _award(award)]);
        },
      ),
    ],
  );

  Widget _award(Map<String, dynamic> award) {
    final link = '${award['link'] ?? ''}'.trim();
    Uri? uri;
    try {
      if (link.isNotEmpty) {
        uri = externalLinkUri(widget.api.baseUrl.resolve(link).toString());
      }
    } on FormatException {
      uri = null;
    }
    final internal =
        uri != null &&
        uri.origin == widget.api.baseUrl.origin &&
        RegExp(r'^/tornei/[^/]+\.php$').hasMatch(uri.path);
    final target = uri;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: colors.surfaceContainerLow,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: colors.outlineVariant),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: !internal
              ? null
              : () => openPage(
                  context,
                  TournamentPage(
                    api: widget.api,
                    tournament: {
                      'nome': award['competizione'],
                      'filetorneo': target!.path.split('/').last,
                    },
                  ),
                ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SiteImage(
                      api: widget.api,
                      path: '${award['logo'] ?? ''}',
                      width: 48,
                      height: 48,
                      preserveShape: true,
                      fallback: Icons.emoji_events_outlined,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xfffff3cf),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '${award['tipo'] ?? 'Premio'}',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: Color(0xff795b13),
                              ),
                            ),
                          ),
                          const SizedBox(height: 7),
                          Text(
                            '${award['premio'] ?? 'Premio'}',
                            style: TextStyle(
                              color: colors.onSurface,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (internal)
                      Icon(
                        Icons.chevron_right,
                        size: 20,
                        color: colors.onSurface,
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  [
                    if ('${award['competizione'] ?? ''}'.isNotEmpty)
                      '${award['competizione']}',
                    if ('${award['anno'] ?? ''}'.isNotEmpty) '${award['anno']}',
                  ].join(' · '),
                  style: TextStyle(
                    color: colors.onSurfaceVariant,
                    fontSize: 13,
                  ),
                ),
                if ('${award['squadra'] ?? ''}'.isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(
                    'Con ${award['squadra']}',
                    style: TextStyle(color: colors.onSurface, fontSize: 13),
                  ),
                ],
                if (target != null && !internal) ...[
                  const SizedBox(height: 12),
                  ExternalLinkButton(
                    uri: target,
                    label: 'Apri torneo',
                    icon: Icons.open_in_new,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
