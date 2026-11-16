import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:share_plus/share_plus.dart';

import 'api.dart';
import 'access_gate.dart' show AppAuthScope;
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';
import 'player_role_badges.dart' show isGoalkeeper;

Uri sharedMatchUrl(TosApi api, String id, Map<String, dynamic> match) => api
    .baseUrl
    .resolve('tornei/partita_eventi.php')
    .replace(
      queryParameters: {
        'id': id,
        if ('${match['torneo'] ?? ''}'.isNotEmpty)
          'torneo': '${match['torneo']}',
      },
    );

typedef MatchImageSender = Future<void> Function(
  Uint8List image,
  String text,
  Rect origin,
);

/// Paint the export behind a loading mask without adding a navigation route.
Future<Uint8List?> renderMatchShareImage(
  BuildContext context,
  TosApi api,
  Map<String, dynamic> match,
  List<Map<String, dynamic>> events,
) async {
  final key = GlobalKey();
  final auth = AppAuthScope.of(context);
  final media = MediaQuery.of(context)
      .copyWith(textScaler: TextScaler.noScaling);
  final background = Theme.of(context).scaffoldBackgroundColor;
  Widget artwork = MatchShareArtwork(api: api, match: match, events: events);
  if (auth != null) artwork = AppAuthScope(auth: auth, child: artwork);
  final entry = OverlayEntry(
    builder: (_) => Stack(
      children: [
        Positioned(
          left: 0,
          top: 0,
          width: 360,
          height: 640,
          child: RepaintBoundary(
            key: key,
            child: MediaQuery(data: media, child: artwork),
          ),
        ),
        Positioned.fill(
          child: AbsorbPointer(
            child: Material(
              color: background,
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: 16),
                    Text('Preparazione immagine…'),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
  Overlay.of(context, rootOverlay: true).insert(entry);
  try {
    final images = <ImageProvider>[
      const AssetImage('assets/logo_old_school.png'),
    ];
    if ((api.originalLogos.value || auth?.user != null) &&
        !(auth?.alwaysUseOriginalEmblems ?? false)) {
      for (final field in ['logo_casa', 'logo_ospite']) {
        final path = '${match[field] ?? ''}'.trim();
        final uri = api.baseUrl.resolve(path);
        if (path.isNotEmpty && uri.scheme == 'https' && uri.userInfo.isEmpty) {
          images.add(NetworkImage('$uri'));
        }
      }
    }
    await Future.wait(
      images.map((image) => precacheImage(image, context, onError: (_, _) {})),
    ).timeout(const Duration(seconds: 5), onTimeout: () => <void>[]);
    if (!context.mounted) return null;
    await WidgetsBinding.instance.endOfFrame;
    if (!context.mounted) return null;
    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 3);
    try {
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      if (bytes == null) throw StateError('PNG unavailable');
      return bytes.buffer.asUint8List();
    } finally {
      image.dispose();
    }
  } finally {
    entry.remove();
    entry.dispose();
  }
}

class MatchShareButton extends StatefulWidget {
  const MatchShareButton({
    super.key,
    required this.api,
    required this.id,
    required this.match,
    this.events = const [],
    this.loadEvents,
    this.sender,
    this.outlined = false,
  });
  final TosApi api;
  final String id;
  final Map<String, dynamic> match;
  final List<Map<String, dynamic>> events;
  final Future<List<Map<String, dynamic>>> Function()? loadEvents;
  final MatchImageSender? sender;
  final bool outlined;
  @override
  State<MatchShareButton> createState() => _MatchShareButtonState();
}

class _MatchShareButtonState extends State<MatchShareButton> {
  bool _busy = false;
  Future<void> _share() async {
    if (_busy) return;
    final auth = AppAuthScope.of(context);
    final userId = auth?.user?['id'];
    final box = context.findRenderObject()! as RenderBox;
    final origin = box.localToGlobal(Offset.zero) & box.size;
    setState(() => _busy = true);
    try {
      final events = widget.loadEvents == null
          ? widget.events
          : await widget.loadEvents!();
      if (!mounted ||
          auth?.user?['id'] != userId ||
          !(ModalRoute.of(context)?.isCurrent ?? true)) {
        return;
      }
      final bytes = await renderMatchShareImage(
        context,
        widget.api,
        widget.match,
        events,
      );
      if (!mounted || bytes == null || auth?.user?['id'] != userId) return;
      final match = widget.match;
      final text =
          '${match['squadra_casa']} ${match['gol_casa']}–${match['gol_ospite']} ${match['squadra_ospite']}\n${sharedMatchUrl(widget.api, widget.id, match)}';
      if (widget.sender != null) {
        await widget.sender!(bytes, text, origin);
      } else {
        final name = 'tos-risultato-${widget.id}-storia.png';
        await SharePlus.instance.share(
          ShareParams(
            files: [XFile.fromData(bytes, mimeType: 'image/png', name: name)],
            fileNameOverrides: [name],
            text: text,
            sharePositionOrigin: origin,
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Impossibile preparare la condivisione. Riprova.'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final callback = _busy || (int.tryParse(widget.id) ?? 0) <= 0
        ? null
        : _share;
    final icon = _busy
        ? const SizedBox.square(
            dimension: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : const Icon(Icons.share_outlined);
    final label = Text(_busy ? 'Preparazione…' : 'Condividi risultato');
    return widget.outlined
        ? OutlinedButton.icon(onPressed: callback, icon: icon, label: label)
        : FilledButton.icon(onPressed: callback, icon: icon, label: label);
  }
}

class MatchSharePage extends StatefulWidget {
  const MatchSharePage({
    super.key,
    required this.api,
    required this.id,
    required this.match,
    required this.events,
    this.sender,
  });
  final TosApi api;
  final String id;
  final Map<String, dynamic> match;
  final List<Map<String, dynamic>> events;
  final MatchImageSender? sender;

  @override
  State<MatchSharePage> createState() => _MatchSharePageState();
}

class _MatchSharePageState extends State<MatchSharePage> {
  final _artwork = GlobalKey();
  bool _busy = false;

  Future<void> _share(BuildContext buttonContext) async {
    if (_busy) return;
    final box = buttonContext.findRenderObject()! as RenderBox;
    final origin = box.localToGlobal(Offset.zero) & box.size;
    setState(() => _busy = true);
    try {
      await precacheImage(
        const AssetImage('assets/logo_old_school.png'),
        context,
      );
      if (!mounted) return;
      await WidgetsBinding.instance.endOfFrame;
      if (!mounted) return;
      final boundary =
          _artwork.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final image = await boundary.toImage(pixelRatio: 3);
      late Uint8List bytes;
      try {
        final data = await image.toByteData(format: ui.ImageByteFormat.png);
        if (data == null) throw StateError('PNG unavailable');
        bytes = data.buffer.asUint8List();
      } finally {
        image.dispose();
      }
      if (!mounted) return;
      final match = widget.match;
      final text =
          '${match['squadra_casa']} ${match['gol_casa']}–${match['gol_ospite']} ${match['squadra_ospite']}\n${sharedMatchUrl(widget.api, widget.id, match)}';
      if (widget.sender != null) {
        await widget.sender!(bytes, text, origin);
      } else {
        final name = 'tos-risultato-${widget.id}-storia.png';
        await SharePlus.instance.share(
          ShareParams(
            files: [XFile.fromData(bytes, mimeType: 'image/png', name: name)],
            fileNameOverrides: [name],
            text: text,
            sharePositionOrigin: origin,
          ),
        );
      }
    } catch (error) {
      debugPrint('Match sharing failed: $error');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Impossibile condividere il risultato. Riprova.'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Condividi risultato')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'IL TUO MATCH, DA CONDIVIDERE',
          style: TextStyle(
            color: siteRed,
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: 1.4,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Porta il risultato con te',
          style: Theme.of(context).textTheme.headlineSmall
              ?.copyWith(fontWeight: FontWeight.w900, letterSpacing: -.7),
        ),
        const SizedBox(height: 18),
        const Text(
          'Formato storia Instagram',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 20),
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: FittedBox(
                fit: BoxFit.contain,
                child: RepaintBoundary(
                  key: _artwork,
                  child: MediaQuery(
                    data: MediaQuery.of(context)
                        .copyWith(textScaler: TextScaler.noScaling),
                    child: MatchShareArtwork(
                      api: widget.api,
                      match: widget.match,
                      events: widget.events,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Un’unica immagine con tutte le presenze, i gol e i voti. Casa a sinistra, ospiti a destra.',
          style: Theme.of(context).textTheme.bodyMedium
              ?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
        ),
      ],
    ),
    bottomNavigationBar: SafeArea(
      minimum: const EdgeInsets.all(16),
      child: Builder(
        builder: (context) => FilledButton.icon(
          style: FilledButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          onPressed: _busy ? null : () => _share(context),
          icon: _busy
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.share_outlined),
          label: Text(_busy ? 'Preparazione…' : 'Condividi immagine'),
        ),
      ),
    ),
  );
}

/// Complete report: height grows with the number and names of participants.
List<Map<String, dynamic>> shareParticipants(
  List<Map<String, dynamic>> events,
) => events
    .where((e) => e['presenza'] == true || '${e['presenza'] ?? 1}' == '1')
    .toList();

/// One Instagram story, including both teams in parallel columns.
class MatchShareArtwork extends StatelessWidget {
  const MatchShareArtwork({
    super.key,
    required this.api,
    required this.match,
    required this.events,
    this.story = true,
  });
  final TosApi api;
  final Map<String, dynamic> match;
  final List<Map<String, dynamic>> events;
  final bool story;

  @override
  Widget build(BuildContext context) {
    const muted = Color(0xff627386);
    final home = '${match['squadra_casa'] ?? ''}';
    final away = '${match['squadra_ospite'] ?? ''}';
    String normalized(String value) => value.trim().toLowerCase();
    final participants = shareParticipants(events);
    final homePlayers = participants
        .where((e) => normalized('${e['squadra'] ?? ''}') == normalized(home))
        .toList();
    final awayPlayers = participants
        .where((e) => normalized('${e['squadra'] ?? ''}') == normalized(away))
        .toList();
    final others = participants
        .where(
          (e) => ![
            normalized(home),
            normalized(away),
          ].contains(normalized('${e['squadra'] ?? ''}')),
        )
        .toList();
    final date = DateTime.tryParse('${match['data_partita']}');
    final meta = [
      if (date != null)
        '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}',
      if ((int.tryParse('${match['giornata']}') ?? 0) > 0)
        'Giornata ${match['giornata']}',
    ].join(' · ');
    Widget team(String name, String logo) => Expanded(
      child: Column(
        children: [
          Container(
            width: 60,
            height: 60,
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            child: SiteImage(
              api: api,
              identityName: name,
              path: logo,
              width: 46,
              height: 46,
              preserveShape: true,
              fallback: Icons.shield_outlined,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: 14,
              height: 1.15,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );

    Widget badge(String label, Color color, Color foreground) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(3),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: foreground,
          fontSize: 8,
          fontWeight: FontWeight.w900,
        ),
      ),
    );

    Widget playerTable(
      String label,
      List<Map<String, dynamic>> players,
      Color accent,
    ) => Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(height: 3, color: accent),
        const SizedBox(height: 6),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w900,
            color: accent,
          ),
        ),
        const SizedBox(height: 6),
        Container(
          color: accent,
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 7),
          child: const Row(
            children: [
              Expanded(
                child: Text(
                  'Giocatore',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              SizedBox(
                width: 22,
                child: Text(
                  'Gol',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              SizedBox(
                width: 30,
                child: Text(
                  'Voto',
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
        for (final entry in players.asMap().entries)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 7),
            color: entry.key.isEven ? const Color(0xffeef2f6) : Colors.white,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${entry.value['nome'] ?? ''} ${entry.value['cognome'] ?? ''}'
                            .trim(),
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          height: 1.15,
                        ),
                      ),
                      if (isGoalkeeper(entry.value) ||
                          entry.value['is_captain'] == true ||
                          '${entry.value['is_captain'] ?? entry.value['captain']}' ==
                              '1')
                        Padding(
                          padding: const EdgeInsets.only(top: 3),
                          child: Wrap(
                            spacing: 3,
                            runSpacing: 3,
                            children: [
                              if (isGoalkeeper(entry.value))
                                badge('POR', const Color(0xffffd43b), siteBlue),
                              if (entry.value['is_captain'] == true ||
                                  '${entry.value['is_captain'] ?? entry.value['captain']}' ==
                                      '1')
                                badge('C', siteRed, Colors.white),
                            ],
                          ),
                        ),
                      if ((int.tryParse('${entry.value['autogol']}') ?? 0) > 0)
                        Text(
                          'Autogol: ${entry.value['autogol']}',
                          style: const TextStyle(fontSize: 8, color: muted),
                        ),
                    ],
                  ),
                ),
                SizedBox(
                  width: 22,
                  child: Text(
                    '${entry.value['goal'] ?? 0}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                SizedBox(
                  width: 30,
                  child: Text(
                    (num.tryParse('${entry.value['voto']}') ?? 0) > 0
                        ? num.parse('${entry.value['voto']}')
                              .toStringAsFixed(1)
                              .replaceAll('.', ',')
                        : '—',
                    textAlign: TextAlign.right,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                      color: accent,
                    ),
                  ),
                ),
              ],
            ),
          ),
        if (players.isEmpty)
          const Padding(
            padding: EdgeInsets.all(7),
            child: Text(
              'Nessuna presenza.',
              style: TextStyle(fontSize: 10, color: muted),
            ),
          ),
      ],
    );

    return DefaultTextStyle(
      style: const TextStyle(
        fontFamily: 'Roboto',
        color: siteBlue,
        fontSize: 12,
      ),
      child: SizedBox(
        width: 360,
        height: 640,
        child: ColoredBox(
          color: Colors.white,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                height: 222,
                padding: EdgeInsets.fromLTRB(18, 12, 18, 10),
                decoration: const BoxDecoration(
                  color: siteBlue,
                  border: Border(bottom: BorderSide(color: siteRed, width: 4)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Image.asset(
                          'assets/logo_old_school.png',
                          width: 26,
                          height: 26,
                        ),
                        const SizedBox(width: 8),
                        const Expanded(
                          child: Text(
                            'TORNEI OLD SCHOOL',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              letterSpacing: .6,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: siteRed,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text(
                            'FINALE',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 9),
                    Text(
                      '${match['torneo_nome'] ?? match['torneo'] ?? 'Tornei Old School'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.6,
                      ),
                    ),
                    if (meta.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        meta,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xffc3d0df),
                          fontSize: 11,
                        ),
                      ),
                    ],
                    const Spacer(),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        team(home, '${match['logo_casa'] ?? ''}'),
                        SizedBox(
                          width: 114,
                          height: 60,
                          child: Center(
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                '${match['gol_casa']} – ${match['gol_ospite']}',
                                style: TextStyle(
                                  fontSize: 54,
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: -2,
                                ),
                              ),
                            ),
                          ),
                        ),
                        team(away, '${match['logo_ospite'] ?? ''}'),
                      ],
                    ),
                    if ('${match['decisa_rigori']}' == '1') ...[
                      const SizedBox(height: 6),
                      Text(
                        'Rigori ${match['rigori_casa']} – ${match['rigori_ospite']}',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xffc3d0df),
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ],
                ),
              ),

              const Padding(
                padding: EdgeInsets.fromLTRB(18, 14, 18, 10),
                child: Text(
                  'Presenze e voti',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.6,
                  ),
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                  child: LayoutBuilder(
                    builder: (context, constraints) => FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.topCenter,
                      child: SizedBox(
                        width: constraints.maxWidth,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: playerTable(
                                    home,
                                    homePlayers,
                                    siteBlue,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: playerTable(
                                    away,
                                    awayPlayers,
                                    siteRed,
                                  ),
                                ),
                              ],
                            ),
                            if (others.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              playerTable('Altri presenti', others, siteBlue),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.fromLTRB(18, 10, 18, 4),
                child: Text(
                  'POR: portiere · C: capitano · —: voto non assegnato',
                  style: TextStyle(fontSize: 9, color: muted),
                ),
              ),
              const Padding(
                padding: EdgeInsets.fromLTRB(18, 0, 18, 14),
                child: Text(
                  'torneioldschool.it',
                  textAlign: TextAlign.right,
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
