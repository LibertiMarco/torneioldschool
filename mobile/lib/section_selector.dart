import 'package:flutter/material.dart';

import 'theme.dart';

// Use the Home selector everywhere, including inside the staff theme.
class SectionSelector extends StatelessWidget {
  const SectionSelector({
    super.key,
    required this.section,
    required this.onChanged,
  });
  final String section;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.center,
    widthFactor: 1,
    heightFactor: 1,
    child: SegmentedButtonTheme(
      data: const SegmentedButtonThemeData(),
      child: SegmentedButton<String>(
        showSelectedIcon: false,
        style: ButtonStyle(
          side: const WidgetStatePropertyAll(BorderSide.none),
          shape: WidgetStatePropertyAll(
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          backgroundColor: WidgetStateProperty.resolveWith(
            (states) =>
                states.contains(WidgetState.selected) ? siteBlue : Colors.white,
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) =>
                states.contains(WidgetState.selected) ? Colors.white : siteBlue,
          ),
        ),
        segments: const [
          ButtonSegment(
            value: 'calcio',
            label: Text('Calcio'),
            icon: Icon(Icons.sports_soccer),
          ),
          ButtonSegment(
            value: 'esport',
            label: Text('Esport'),
            icon: Icon(Icons.sports_esports),
          ),
        ],
        selected: {section},
        onSelectionChanged: onChanged == null
            ? null
            : (value) => onChanged!(value.first),
      ),
    ),
  );
}
