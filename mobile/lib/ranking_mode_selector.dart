import 'package:flutter/material.dart';

import 'theme.dart';

class RankingModeSelector extends StatelessWidget {
  const RankingModeSelector({
    super.key,
    required this.value,
    required this.onChanged,
  });
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final stacked =
          constraints.maxWidth < 280 ||
          MediaQuery.textScalerOf(context).scale(14) > 18;
      Widget option(String mode, String label, IconData icon) {
        final selected = value == mode;
        return Semantics(
          button: true,
          selected: selected,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            decoration: BoxDecoration(
              color: selected ? siteBlue : Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: selected ? siteBlue : const Color(0xffdbe3f0),
              ),
              boxShadow: selected
                  ? [
                      const BoxShadow(
                        color: Color(0x2415293e),
                        blurRadius: 12,
                        offset: Offset(0, 4),
                      ),
                    ]
                  : [],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () {
                  if (!selected) onChanged(mode);
                },
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 64),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 12,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(7),
                          decoration: BoxDecoration(
                            color: selected ? siteRed : const Color(0xffe8edf5),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(
                            icon,
                            size: 20,
                            color: selected ? Colors.white : siteBlue,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            label,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: selected ? Colors.white : siteBlue,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      }

      final goals = option('gol', 'Marcatori', Icons.sports_soccer);
      final appearances = option(
        'presenze',
        'Presenze',
        Icons.event_available_rounded,
      );
      return stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [goals, const SizedBox(height: 10), appearances],
            )
          : Row(
              children: [
                Expanded(child: goals),
                const SizedBox(width: 10),
                Expanded(child: appearances),
              ],
            );
    },
  );
}
