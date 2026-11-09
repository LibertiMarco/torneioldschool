import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'api.dart';
import 'cup_bracket.dart' show cupMatchWinner, cupMatchWinnerLogo;
import 'details.dart';
import 'home_dashboard.dart' show openPage, SiteImage;
import 'tournament_logic.dart';

Color _cupTone(String phase) =>
    const {
      'GOLD': Color(0xffbf9638),
      'SILVER': Color(0xff9da8b5),
      'BRONZO': Color(0xffb57b50),
    }[phase] ??
    const Color(0xff15293e);

class _BracketNode {
  _BracketNode(this.games, this.slot);
  final List<Map<String, dynamic>> games;
  final String slot;
  double height = 0, center = 0;
}

/// A fixed tree whose cards share the vertical centers of their feeder matches.
class CupBracketView extends StatefulWidget {
  const CupBracketView({
    super.key,
    required this.api,
    required this.games,
    required this.phase,
  });
  final TosApi api;
  final List<Map<String, dynamic>> games;
  final String phase;
  @override
  State<CupBracketView> createState() => _CupBracketViewState();
}

class _CupBracketViewState extends State<CupBracketView> {
  final _scroll = ScrollController();
  int _selectedRound = 0;
  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      final index = (_scroll.offset / (_width + _gap)).round().clamp(
        0,
        widget.games.map((m) => matchRound(m, widget.phase)).toSet().length - 1,
      );
      if (mounted && index != _selectedRound) {
        setState(() => _selectedRound = index);
      }
    });
  }

  @override
  void didUpdateWidget(CupBracketView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.phase != widget.phase && _scroll.hasClients) {
      _scroll.jumpTo(0);
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  static const _width = 260.0, _gap = 52.0, _heading = 42.0;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final scaler = MediaQuery.textScalerOf(context);
    final scale = scaler.scale(12) / 12;
    final rounds =
        widget.games.map((m) => matchRound(m, widget.phase)).toSet().toList()
          ..sort(roundOrder);
    final columns = <List<_BracketNode>>[];
    double textHeight(String text, double font, int lines) {
      final painter = TextPainter(
        text: TextSpan(
          text: text,
          style: TextStyle(
            fontSize: font,
            height: 1.25,
            fontFamily: Theme.of(context).textTheme.bodyMedium?.fontFamily,
            fontWeight: FontWeight.w600,
          ),
        ),
        textScaler: scaler,
        textDirection: Directionality.of(context),
        maxLines: lines,
      )..layout(maxWidth: _width - 110);
      return painter.height;
    }

    double gameHeight(Map<String, dynamic> game, bool multiple) {
      var height =
          textHeight('${game['squadra_casa']}', 12.5, 2) +
          textHeight('${game['squadra_ospite']}', 12.5, 2) +
          18;
      if (multiple) height += 18 * scale + 6;
      if (game['template'] != true) height += 32 * scale + 8;
      if (game['id'] != null) height += 16 * scale + 6;
      if (stat(game['decisa_rigori']) == 1) height += 16 * scale + 6;
      return height;
    }

    var unit = 130.0;
    for (var depth = 0; depth < rounds.length; depth++) {
      final groups = <String, List<Map<String, dynamic>>>{};
      for (final game in widget.games.where(
        (m) => matchRound(m, widget.phase) == rounds[depth],
      )) {
        final pair = ['${game['squadra_casa']}', '${game['squadra_ospite']}']
          ..sort();
        groups
            .putIfAbsent('${game['bracket_slot'] ?? pair.join('|')}', () => [])
            .add(game);
      }
      final nodes = groups.entries
          .map(
            (entry) => _BracketNode(
              entry.value,
              '${entry.value.first['bracket_slot'] ?? ''}',
            ),
          )
          .toList();
      for (final node in nodes) {
        node.height =
            36 +
            18 * scale +
            node.games.fold<double>(
              0,
              (total, game) => total + gameHeight(game, node.games.length > 1),
            ) +
            (node.games.length - 1) * 13;
        unit = math.max(unit, (node.height + 24) / math.pow(2, depth));
      }
      columns.add(nodes);
    }
    if (columns.isEmpty) return const SizedBox.shrink();
    final bodyTop = _heading * scale;
    final height = bodyTop + columns.first.length * unit + 16;
    final fullWidth = columns.length * (_width + _gap) + 220;
    for (var depth = 0; depth < columns.length; depth++) {
      for (var i = 0; i < columns[depth].length; i++) {
        columns[depth][i].center =
            bodyTop + (i + .5) * math.pow(2, depth) * unit;
      }
    }
    final paths = <(Offset, Offset)>[];
    for (var depth = 0; depth < columns.length - 1; depth++) {
      for (var i = 0; i < columns[depth].length; i++) {
        final node = columns[depth][i], parentIndex = i ~/ 2;
        if (parentIndex < columns[depth + 1].length &&
            node.slot.isNotEmpty &&
            columns[depth + 1][parentIndex].slot.isNotEmpty) {
          paths.add((
            Offset(depth * (_width + _gap) + _width, node.center),
            Offset(
              (depth + 1) * (_width + _gap),
              columns[depth + 1][parentIndex].center,
            ),
          ));
        }
      }
    }
    final finalIndex = rounds.indexOf('FINALE');
    final finalNode = finalIndex < 0 ? null : columns[finalIndex].first;
    final finalCenter = finalNode?.center ?? height / 2;
    if (finalNode != null) {
      paths.add((
        Offset(finalIndex * (_width + _gap) + _width, finalCenter),
        Offset(columns.length * (_width + _gap), finalCenter),
      ));
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [
            for (var i = 0; i < rounds.length; i++)
              ChoiceChip(
                label: Text(roundLabel(rounds[i], widget.phase)),
                selected: _selectedRound == i,
                showCheckmark: true,
                selectedColor: Color.alphaBlend(
                  _cupTone(widget.phase).withValues(alpha: .3),
                  colors.surface,
                ),
                labelStyle: TextStyle(
                  fontWeight: _selectedRound == i
                      ? FontWeight.w800
                      : FontWeight.w500,
                  color: _selectedRound == i
                      ? colors.onSurface
                      : colors.onSurfaceVariant,
                ),
                onSelected: (_) {
                  setState(() => _selectedRound = i);
                  _scroll.animateTo(
                    (i * (_width + _gap)).clamp(
                      0,
                      _scroll.position.maxScrollExtent,
                    ),
                    duration: const Duration(milliseconds: 280),
                    curve: Curves.easeOut,
                  );
                },
              ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Scorri in orizzontale per seguire il tabellone.',
          style: TextStyle(fontSize: 12, color: colors.onSurfaceVariant),
        ),
        const SizedBox(height: 12),
        Scrollbar(
          controller: _scroll,
          thumbVisibility: true,
          child: SingleChildScrollView(
            key: ValueKey('cup-tree-${widget.phase}'),
            controller: _scroll,
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.only(bottom: 16),
            child: SizedBox(
              width: fullWidth,
              height: height,
              child: Stack(
                children: [
                  Positioned.fill(
                    child: CustomPaint(
                      painter: CupBracketLines(
                        paths: paths,
                        color: colors.outline,
                      ),
                    ),
                  ),
                  for (var depth = 0; depth < columns.length; depth++) ...[
                    Positioned(
                      left: depth * (_width + _gap),
                      top: 0,
                      width: _width,
                      child: Text(
                        roundLabel(rounds[depth], widget.phase),
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ),
                    for (final node in columns[depth])
                      Positioned(
                        key: ValueKey('cup-node-${widget.phase}-${node.slot}'),
                        left: depth * (_width + _gap),
                        top: node.center - node.height / 2,
                        width: _width,
                        height: node.height,
                        child: _BracketCard(
                          api: widget.api,
                          node: node,
                          phase: widget.phase,
                          gameHeight: gameHeight,
                        ),
                      ),
                  ],
                  Positioned(
                    left: columns.length * (_width + _gap),
                    top: finalCenter - (116 + 36 * scale) / 2,
                    width: 220,
                    child: Container(
                      height: 116 + 36 * scale,
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: Color.alphaBlend(
                          _cupTone(widget.phase).withValues(alpha: .22),
                          colors.surface,
                        ),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: _cupTone(widget.phase)),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SiteImage(
                            api: widget.api,
                            path: cupMatchWinnerLogo(finalNode?.games ?? []),
                            identityName:
                                cupMatchWinner(finalNode?.games ?? []) ?? '',
                            width: 64,
                            height: 64,
                            fallback: Icons.shield_outlined,
                            preserveShape: true,
                          ),
                          const SizedBox(height: 10),
                          Text(
                            cupMatchWinner(finalNode?.games ?? []) ??
                                'Vincente finale',
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 14,
                              height: 1.25,
                              fontWeight: FontWeight.w800,
                              color: colors.onSurface,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class CupBracketLines extends CustomPainter {
  CupBracketLines({required this.paths, required this.color});
  final List<(Offset, Offset)> paths;
  final Color color;
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    for (final (start, end) in paths) {
      final middle = (start.dx + end.dx) / 2;
      canvas.drawPath(
        Path()
          ..moveTo(start.dx, start.dy)
          ..lineTo(middle, start.dy)
          ..lineTo(middle, end.dy)
          ..lineTo(end.dx, end.dy),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(CupBracketLines oldDelegate) =>
      oldDelegate.color != color || oldDelegate.paths != paths;
}

class _BracketCard extends StatelessWidget {
  const _BracketCard({
    required this.api,
    required this.node,
    required this.phase,
    required this.gameHeight,
  });
  final TosApi api;
  final _BracketNode node;
  final String phase;
  final double Function(Map<String, dynamic>, bool) gameHeight;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final template = node.games.every((m) => m['template'] == true);
    final played = node.games.every((m) => stat(m['giocata']) == 1);
    Widget team(Map<String, dynamic> game, String side) => Row(
      children: [
        SiteImage(
          api: api,
          path: '${game['logo_$side'] ?? ''}',
          identityName: '${game['squadra_$side'] ?? ''}',
          width: 22,
          height: 22,
          fallback: Icons.shield_outlined,
          preserveShape: true,
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            '${game['squadra_$side'] ?? 'Da definire'}',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 12.5,
              height: 1.25,
              fontWeight: FontWeight.w600,
              color: colors.onSurface,
            ),
          ),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 30,
          child: Text(
            stat(game['giocata']) == 1 ? '${game['gol_$side'] ?? '–'}' : '–',
            textAlign: TextAlign.end,
            style: TextStyle(
              fontSize: 13,
              height: 1.25,
              fontWeight: FontWeight.w800,
              color: colors.onSurface,
            ),
          ),
        ),
      ],
    );
    return Container(
      decoration: BoxDecoration(
        color: Color.alphaBlend(
          (const {
                    'GOLD': Color(0xffdfb94c),
                    'SILVER': Color(0xffaab5c3),
                    'BRONZO': Color(0xffb57b50),
                  }[phase] ??
                  colors.primary)
              .withValues(
                alpha: Theme.of(context).brightness == Brightness.dark
                    ? .22
                    : .25,
              ),
          colors.surface,
        ),
        border: Border.all(
          color:
              const {
                'GOLD': Color(0xffbf9638),
                'SILVER': Color(0xff9da8b5),
                'BRONZO': Color(0xffb57b50),
              }[phase] ??
              colors.outlineVariant,
        ),
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                node.slot == 'F1' ? '' : node.slot,
                style: TextStyle(
                  fontSize: 11,
                  height: 1.25,
                  fontWeight: FontWeight.w800,
                  color: colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  template
                      ? node.games.first['bye'] == true
                            ? 'Accesso diretto'
                            : 'Da creare'
                      : played
                      ? 'Terminata'
                      : 'In programma',
                  textAlign: TextAlign.end,
                  maxLines: 1,
                  style: TextStyle(
                    fontSize: 11,
                    height: 1.25,
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (var i = 0; i < node.games.length; i++) ...[
            if (i > 0)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Divider(height: 1, color: colors.outlineVariant),
              ),
            SizedBox(
              height: gameHeight(node.games[i], node.games.length > 1),
              child: InkWell(
                onTap: node.games[i]['id'] == null
                    ? null
                    : () => openPage(
                        context,
                        MatchPage(
                          api: api,
                          id: '${node.games[i]['id']}',
                          previewMatch: node.games[i],
                        ),
                      ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    if (node.games.length > 1)
                      Text(
                        roundLabel(matchTurn(node.games[i], phase), phase),
                        style: TextStyle(
                          fontSize: 10,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    team(node.games[i], 'casa'),
                    Divider(height: 1, color: colors.outlineVariant),
                    team(node.games[i], 'ospite'),
                    if (node.games[i]['template'] != true)
                      Text(
                        '${node.games[i]['data_partita'] ?? 'Data da definire'} ${node.games[i]['ora_partita'] ?? ''}\n${node.games[i]['campo'] ?? 'Campo da definire'}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 10,
                          height: 1.25,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    if (stat(node.games[i]['decisa_rigori']) == 1)
                      Text(
                        'Rigori ${node.games[i]['rigori_casa']}–${node.games[i]['rigori_ospite']}',
                        style: TextStyle(
                          fontSize: 10,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    if (node.games[i]['id'] != null)
                      Text(
                        'Apri partita →',
                        style: TextStyle(
                          fontSize: 11,
                          color: colors.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
