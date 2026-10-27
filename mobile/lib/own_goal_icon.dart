import 'dart:math' as math;

import 'package:flutter/material.dart';

/// A single match-event symbol: a football with a returning arrow.
class OwnGoalIcon extends StatelessWidget {
  const OwnGoalIcon({super.key, this.size = 28});

  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Autogol',
    image: true,
    child: SizedBox.square(
      dimension: size,
      child: CustomPaint(painter: _OwnGoalPainter()),
    ),
  );
}

class _OwnGoalPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / 32, size.height / 32);
    final outline = Paint()
      ..color = const Color(0xff162739)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    const center = Offset(14, 21);
    canvas.drawCircle(center, 9, Paint()..color = Colors.white);
    canvas.drawCircle(center, 9, outline);

    final pentagon = Path();
    for (var i = 0; i < 5; i++) {
      final angle = -math.pi / 2 + i * 2 * math.pi / 5;
      final point = center + Offset(math.cos(angle), math.sin(angle)) * 3.8;
      if (i == 0) {
        pentagon.moveTo(point.dx, point.dy);
      } else {
        pentagon.lineTo(point.dx, point.dy);
      }
      final edge = center + Offset(math.cos(angle), math.sin(angle)) * 9;
      canvas.drawLine(point, edge, outline);
    }
    pentagon.close();
    canvas.drawPath(pentagon, Paint()..color = const Color(0xff162739));

    final arrow = Path()
      ..moveTo(28, 22)
      ..lineTo(28, 12)
      ..quadraticBezierTo(28, 5, 21, 5)
      ..lineTo(6, 5);
    // A dark keyline keeps the amber return arrow readable on light surfaces.
    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round
      ..strokeWidth = 5
      ..color = const Color(0xff162739);
    canvas.drawPath(arrow, stroke);
    canvas.drawPath(
      arrow,
      stroke
        ..strokeWidth = 3
        ..color = const Color(0xffffb547),
    );
    final head = Path()
      ..moveTo(10, 1)
      ..lineTo(5, 5)
      ..lineTo(10, 9);
    canvas.drawPath(head, stroke);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _OwnGoalPainter oldDelegate) => false;
}
