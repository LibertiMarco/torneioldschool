import 'package:flutter/material.dart';

// Shared section control. Esport is temporarily unavailable in the app.
class SectionSelector extends StatelessWidget {
  const SectionSelector({
    super.key,
    required this.section,
    required this.onChanged,
  });
  final String section;
  final ValueChanged<String>? onChanged;

  // Temporarily hide the section switch throughout the app, including staff.
  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
