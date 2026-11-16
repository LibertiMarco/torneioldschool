import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:share_plus/share_plus.dart';

import 'api.dart';
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';

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
            'tos-risultato-${widget.id}-${_story ? 'storia' : 'quadrato'}.png';
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
        SegmentedButton<bool>(
          segments: const [
            ButtonSegment(
              value: false,
              label: Text('Quadrato'),
              icon: Icon(Icons.crop_square),
            ),
            ButtonSegment(
              value: true,
              label: Text('Storia'),
              icon: Icon(Icons.crop_portrait),
            ),
          ],
          selected: {_story},
          onSelectionChanged: _busy
              ? null
              : (selection) => setState(() => _story = selection.single),
        ),
        const SizedBox(height: 20),
        AspectRatio(
          aspectRatio: _story ? 9 / 16 : 1,
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
        const SizedBox(height: 16),
        const Text(
          'Condividi l’immagine su WhatsApp o nelle tue storie. Il collegamento alla partita viene allegato dove supportato.',
        ),
      ],
    ),
    bottomNavigationBar: SafeArea(
      minimum: const EdgeInsets.all(16),
      child: Builder(
        builder: (context) => FilledButton.icon(
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

/// Fixed canvas dimensions produce 1080x1080 / 1080x1920 PNGs at 3x.
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
    final scorers = events
        .where(
          (e) =>
              (int.tryParse('${e['goal']}') ?? 0) > 0 ||
              (int.tryParse('${e['autogol']}') ?? 0) > 0,
        )
        .toList();
    final limit = story ? 9 : 3;
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
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
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
          const SizedBox(height: 10),
          Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              height: 1.15,
            ),
          ),
        ],
      ),
    );
    return DefaultTextStyle(
      style: const TextStyle(
        fontFamily: 'Roboto',
        color: Colors.white,
        fontSize: 12,
      ),
      child: Container(
        width: 360,
        height: story ? 640 : 360,
        padding: EdgeInsets.fromLTRB(22, story ? 40 : 18, 22, story ? 36 : 16),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [siteBlue, Color(0xff254b69), siteBlue],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Image.asset(
                  'assets/logo_old_school.png',
                  width: 30,
                  height: 30,
                ),
                const SizedBox(width: 9),
                const Expanded(
                  child: Text(
                    'TORNEI OLD SCHOOL',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                ),
                Container(width: 24, height: 4, color: siteRed),
              ],
            ),
            SizedBox(height: story ? 28 : 10),
            Text(
              '${match['torneo_nome'] ?? match['torneo'] ?? 'Tornei Old School'}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 13, color: Color(0xffbdd7e8)),
            ),
            if (meta.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                meta,
                style: const TextStyle(fontSize: 10, color: Color(0xffbdd7e8)),
              ),
            ],
            SizedBox(height: story ? 24 : 12),
            const Text(
              'RISULTATO FINALE',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w800,
                letterSpacing: 2,
                color: Color(0xffbdd7e8),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                team(
                  '${match['squadra_casa'] ?? ''}',
                  '${match['logo_casa'] ?? ''}',
                ),
                SizedBox(
                  width: 94,
                  height: 66,
                  child: Center(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        '${match['gol_casa']} – ${match['gol_ospite']}',
                        style: const TextStyle(
                          fontSize: 36,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
                ),
                team(
                  '${match['squadra_ospite'] ?? ''}',
                  '${match['logo_ospite'] ?? ''}',
                ),
              ],
            ),
            if ('${match['decisa_rigori']}' == '1') ...[
              const SizedBox(height: 6),
              Text(
                'Rigori ${match['rigori_casa']} – ${match['rigori_ospite']}',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            SizedBox(height: story ? 24 : 8),
            if (scorers.isNotEmpty) ...[
              const Divider(color: Color(0xff5d7b92), height: 12),
              for (final event in scorers.take(limit))
                Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text(
                    [
                      '${event['nome'] ?? ''} ${event['cognome'] ?? ''}'.trim(),
                      if ((int.tryParse('${event['goal']}') ?? 0) > 0)
                        '${event['goal']} gol',
                      if ((int.tryParse('${event['autogol']}') ?? 0) > 0)
                        '${event['autogol']} autogol',
                      if ('${event['squadra'] ?? ''}'.isNotEmpty)
                        '${event['squadra']}',
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 10),
                  ),
                ),
              if (scorers.length > limit)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '+ ${scorers.length - limit} nel dettaglio partita',
                    style: const TextStyle(
                      fontSize: 9,
                      color: Color(0xffbdd7e8),
                    ),
                  ),
                ),
            ],
            const Spacer(),
            const Text(
              'Risultati e classifiche su torneioldschool.it',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w600,
                color: Color(0xffbdd7e8),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
