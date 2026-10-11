import 'package:flutter/material.dart';

class PlayerEventMarks extends StatelessWidget {
  const PlayerEventMarks({super.key, required this.stats});
  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    int count(String key) => int.tryParse('${stats[key] ?? 0}') ?? 0;
    final goals = count('goal');
    final yellow = count('cartellino_giallo') > 0;
    final red = count('cartellino_rosso') > 0;
    if (goals <= 0 && !yellow && !red) return const SizedBox.shrink();
    Widget card(Color color, String label) => Semantics(
      label: label,
      child: Tooltip(
        message: label,
        child: Container(
          width: 16,
          height: 23,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(3),
            border: Border.all(color: Colors.black.withValues(alpha: 0.12)),
          ),
        ),
      ),
    );
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Wrap(
        spacing: 6,
        runSpacing: 6,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          if (goals > 0)
            Semantics(
              label: '$goals gol',
              child: Wrap(
                spacing: 4,
                runSpacing: 4,
                children: [
                  for (var i = 0; i < goals; i++)
                    Image.asset(
                      'assets/emoji/football.png',
                      width: 22,
                      height: 22,
                      excludeFromSemantics: true,
                    ),
                ],
              ),
            ),
          if (yellow) card(const Color(0xffffd43b), 'Ammonito'),
          if (red) card(const Color(0xffe03131), 'Espulso'),
        ],
      ),
    );
  }
}
