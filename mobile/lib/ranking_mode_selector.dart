import 'package:flutter/material.dart';

import 'sport_controls.dart';

class RankingModeSelector extends StatelessWidget {
  const RankingModeSelector({
    super.key,
    required this.value,
    required this.onChanged,
  });
  final String value;
  final ValueChanged<String> onChanged;
  @override
  Widget build(BuildContext context) => SportSegments<String>(
    value: value,
    options: const {'gol': 'Marcatori', 'presenze': 'Presenze'},
    onChanged: onChanged,
  );
}
