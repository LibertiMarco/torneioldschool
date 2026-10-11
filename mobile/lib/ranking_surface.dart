import 'package:flutter/material.dart';

class RankingSurface extends StatelessWidget {
  const RankingSurface({super.key, required this.index, required this.child});
  final int index;
  final Widget child;
  @override
  Widget build(BuildContext context) => Material(
    color: index.isEven ? Colors.white : const Color(0xffeef2f6),
    child: DecoratedBox(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xffdce5ed))),
      ),
      child: child,
    ),
  );
}
