import 'package:flutter/material.dart';

/// Local identity artwork; never downloads or derives artwork from club logos.
class OriginalEmblem extends StatelessWidget {
  const OriginalEmblem({
    super.key,
    required this.name,
    this.tournament = false,
    this.width = 56,
    this.height = 56,
  });

  final String name;
  final bool tournament;
  final double width, height;

  static const palette = [
    Color(0xff176b68),
    Color(0xff7451a1),
    Color(0xffa44936),
    Color(0xff334a5a),
    Color(0xff825b29),
    Color(0xff495fa0),
  ];

  @override
  Widget build(BuildContext context) {
    final normalized = name
        .trim()
        .replaceAll(RegExp(r'\s+'), ' ')
        .toUpperCase();
    final words = normalized.split(' ').where((word) => word.isNotEmpty);
    final initials = words.take(2).map((word) => word.characters.first).join();
    var hash = 0;
    for (final rune in normalized.runes) {
      hash = (hash * 31 + rune) & 0x7fffffff;
    }
    return Semantics(
      label: '${tournament ? 'Torneo' : 'Squadra'} $name',
      image: true,
      child: SizedBox(
        width: width,
        height: height,
        child: Center(
          child: AspectRatio(
            aspectRatio: 1,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: palette[hash % palette.length],
                shape: tournament ? BoxShape.rectangle : BoxShape.circle,
                borderRadius: tournament ? BorderRadius.circular(12) : null,
              ),
              child: Padding(
                padding: const EdgeInsets.all(5),
                child: Center(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      initials.isEmpty ? (tournament ? 'T' : 'S') : initials,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        decoration: TextDecoration.none,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
