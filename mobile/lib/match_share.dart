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
            options: const {false: 'Quadrato', true: 'Storia'},
            onChanged: (value) => setState(() => _story = value),
          ),
        ),
        const SizedBox(height: 20),
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: AspectRatio(
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
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Scegli il formato e condividi su WhatsApp o nelle tue storie.',
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

/// Fixed dimensions keep the exported post independent of the device size.
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
            width: 52,
            height: 52,
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
            ),
            child: SiteImage(
              api: api,
              identityName: name,
              path: logo,
              width: 38,
              height: 38,
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
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              height: 1.2,
              fontWeight: FontWeight.w700,
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
      child: Container(
        width: 360,
        height: story ? 640 : 360,
        color: siteBackground,
        padding: EdgeInsets.fromLTRB(20, story ? 40 : 16, 20, story ? 34 : 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Image.asset(
                  'assets/logo_old_school.png',
                  width: 32,
                  height: 32,
                ),
                const SizedBox(width: 9),
                const Expanded(
                  child: Text(
                    'Tornei Old School',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.4,
                    ),
                  ),
                ),
                Container(width: 22, height: 3, color: siteRed),
              ],
            ),
            SizedBox(height: story ? 24 : 10),
            const Text(
              'RISULTATO FINALE',
              style: TextStyle(
                color: siteRed,
                fontSize: 9,
                letterSpacing: 1.6,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${match['torneo_nome'] ?? match['torneo'] ?? 'Tornei Old School'}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.w900,
                letterSpacing: -.6,
              ),
            ),
            if (meta.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(meta, style: const TextStyle(fontSize: 10, color: muted)),
            ],
            SizedBox(height: story ? 22 : 10),
            Container(
              padding: EdgeInsets.symmetric(
                horizontal: 14,
                vertical: story ? 16 : 10,
              ),
              decoration: BoxDecoration(
                color: siteBlue,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      team(
                        '${match['squadra_casa'] ?? ''}',
                        '${match['logo_casa'] ?? ''}',
                      ),
                      SizedBox(
                        width: 92,
                        height: 52,
                        child: Center(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              '${match['gol_casa']} – ${match['gol_ospite']}',
                              style: const TextStyle(
                                fontSize: 36,
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                letterSpacing: -1.5,
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
                      style: const TextStyle(
                        color: Color(0xffcbd6e2),
                        fontSize: 9,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (scorers.isNotEmpty) ...[
              SizedBox(height: story ? 22 : 8),
              if (story) ...[
                const Text(
                  'Il tabellino',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.5,
                  ),
                ),
                const SizedBox(height: 10),
              ],
              Container(
                padding: EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: story ? 12 : 7,
                ),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xffdbe3eb)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final event in scorers.take(limit))
                      Padding(
                        padding: EdgeInsets.symmetric(vertical: story ? 3 : 1),
                        child: Text(
                          [
                            '${event['nome'] ?? ''} ${event['cognome'] ?? ''}'
                                .trim(),
                            if ((int.tryParse('${event['goal']}') ?? 0) > 0)
                              '${event['goal']} gol',
                            if ((int.tryParse('${event['autogol']}') ?? 0) > 0)
                              '${event['autogol']} autogol',
                            if ('${event['squadra'] ?? ''}'.isNotEmpty)
                              '${event['squadra']}',
                          ].join(' · '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    if (scorers.length > limit)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          '+ ${scorers.length - limit} nel dettaglio partita',
                          style: const TextStyle(fontSize: 9, color: muted),
                        ),
                      ),
                  ],
                ),
              ),
            ],
            const Spacer(),
            const Text(
              'torneioldschool.it',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                color: muted,
                letterSpacing: .3,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
