import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:share_plus/share_plus.dart';

import 'api.dart';
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';
import 'sport_controls.dart';

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
  bool _story = false, _busy = false;

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
        final name =
            'tos-risultato-${widget.id}-${_story ? 'storia' : 'completa'}.png';
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
        AbsorbPointer(
          absorbing: _busy,
          child: SportSegments<bool>(
            value: _story,
            options: const {false: 'Completa', true: 'Storia'},
            onChanged: (value) => setState(() => _story = value),
          ),
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
                      story: _story,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'La grafica si allunga per includere tutte le presenze, i gol e i voti.',
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

class MatchShareArtwork extends StatelessWidget {
  const MatchShareArtwork({
    super.key,
    required this.api,
    required this.match,
    required this.events,
    this.story = false,
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
    final otherPlayers = participants
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
            width: story ? 76 : 60,
            height: story ? 76 : 60,
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            child: SiteImage(
              api: api,
              identityName: name,
              path: logo,
              width: story ? 62 : 46,
              height: story ? 62 : 46,
              preserveShape: true,
              fallback: Icons.shield_outlined,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            name,
            maxLines: story ? 3 : 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: story ? 16 : 14,
              height: 1.15,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );

    Widget playerTable(
      String name,
      String logo,
      List<Map<String, dynamic>> rows,
      Color accent,
    ) => Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              SiteImage(
                api: api,
                path: logo,
                identityName: name,
                width: 32,
                height: 32,
                preserveShape: true,
                fallback: Icons.shield_outlined,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  name,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: accent,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            '${rows.length} presenti',
            style: const TextStyle(fontSize: 11, color: muted),
          ),
          const SizedBox(height: 9),
          Container(
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xffdbe3eb)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  color: accent,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 9,
                  ),
                  child: const Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Giocatore',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      SizedBox(
                        width: 34,
                        child: Text(
                          'Gol',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      SizedBox(
                        width: 48,
                        child: Text(
                          'Voto',
                          textAlign: TextAlign.right,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                for (final entry in rows.asMap().entries)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    color: entry.key.isEven
                        ? const Color(0xfff1f4f7)
                        : Colors.white,
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
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  height: 1.25,
                                ),
                              ),
                              if ((int.tryParse('${entry.value['autogol']}') ??
                                      0) >
                                  0)
                                Text(
                                  'Autogol: ${entry.value['autogol']}',
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: muted,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        SizedBox(
                          width: 34,
                          child: Text(
                            '${entry.value['goal'] ?? 0}',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        SizedBox(
                          width: 48,
                          child: Text(
                            (num.tryParse('${entry.value['voto']}') ?? 0) > 0
                                ? num.parse('${entry.value['voto']}')
                                      .toStringAsFixed(1)
                                      .replaceAll('.', ',')
                                : '—',
                            textAlign: TextAlign.right,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                              color: accent,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                if (rows.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(12),
                    child: Text(
                      'Nessuna presenza registrata.',
                      style: TextStyle(fontSize: 12, color: muted),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );

    return DefaultTextStyle(
      style: const TextStyle(
        fontFamily: 'Roboto',
        color: siteBlue,
        fontSize: 12,
      ),
      child: SizedBox(
        width: 360,
        child: ColoredBox(
          color: Colors.white,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                height: story ? 330 : 222,
                padding: EdgeInsets.fromLTRB(
                  18,
                  story ? 28 : 12,
                  18,
                  story ? 22 : 10,
                ),
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
                    SizedBox(height: story ? 22 : 9),
                    Text(
                      '${match['torneo_nome'] ?? match['torneo'] ?? 'Tornei Old School'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: story ? 26 : 20,
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
                          height: story ? 76 : 60,
                          child: Center(
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                '${match['gol_casa']} – ${match['gol_ospite']}',
                                style: TextStyle(
                                  fontSize: story ? 68 : 54,
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

              Padding(
                padding: const EdgeInsets.fromLTRB(18, 20, 18, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text(
                      'Presenze e voti',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.6,
                      ),
                    ),
                    const SizedBox(height: 16),
                    playerTable(
                      home,
                      '${match['logo_casa'] ?? ''}',
                      homePlayers,
                      siteBlue,
                    ),
                    playerTable(
                      away,
                      '${match['logo_ospite'] ?? ''}',
                      awayPlayers,
                      siteRed,
                    ),
                    if (otherPlayers.isNotEmpty)
                      playerTable('Altri presenti', '', otherPlayers, siteBlue),
                    const Text(
                      '— voto non assegnato',
                      style: TextStyle(fontSize: 10, color: muted),
                    ),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
              const Padding(
                padding: EdgeInsets.fromLTRB(18, 0, 18, 16),
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
