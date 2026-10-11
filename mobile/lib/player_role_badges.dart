import 'package:flutter/material.dart';

import 'theme.dart';

class PlayerRoleBadges extends StatelessWidget {
  const PlayerRoleBadges({super.key, required this.player});
  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final teamRole = '${player['ruolo_squadra'] ?? ''}'.trim();
    final role = teamRole.isNotEmpty
        ? teamRole
        : '${player['ruolo'] ?? ''}'.trim();
    final goalkeeper = RegExp(
      r'portiere|\bgk\b|^(p|por)$',
      caseSensitive: false,
    ).hasMatch(role);
    final captain =
        (int.tryParse('${player['is_captain'] ?? player['captain'] ?? 0}') ??
            0) ==
        1;
    if (!goalkeeper && !captain) return const SizedBox.shrink();
    Widget badge(
      String label,
      String description,
      Color color,
      Color textColor,
    ) => Tooltip(
      message: description,
      child: Semantics(
        label: description,
        excludeSemantics: true,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(7),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: textColor,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
        ),
      ),
    );
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        if (goalkeeper)
          badge('GK', 'Portiere', const Color(0xffffd43b), siteBlue),
        if (captain) badge('C', 'Capitano', siteRed, Colors.white),
      ],
    );
  }
}
