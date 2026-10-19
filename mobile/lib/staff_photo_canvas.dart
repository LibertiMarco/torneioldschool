import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Sends real transforms to the existing PHP/JS generator; no second compositor.
class StaffPhotoCanvas extends StatefulWidget {
  const StaffPhotoCanvas({
    super.key,
    required this.image,
    required this.photo,
    required this.onTransform,
  });
  final Widget image;
  final Map photo;
  final ValueChanged<Map<String, dynamic>> onTransform;
  @override
  State<StaffPhotoCanvas> createState() => _StaffPhotoCanvasState();
}

class _StaffPhotoCanvasState extends State<StaffPhotoCanvas> {
  bool _editing = false;
  Map<String, dynamic>? _start, _pending;
  Offset _focal = Offset.zero;
  Timer? _throttle;
  double number(String key) =>
      num.tryParse('${widget.photo[key]}')?.toDouble() ?? 0;
  @override
  void dispose() {
    _throttle?.cancel();
    super.dispose();
  }

  void _flush() {
    _throttle?.cancel();
    _throttle = null;
    final pending = _pending;
    _pending = null;
    if (pending != null) widget.onTransform(pending);
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        title: const Text('Modifica foto'),
        subtitle: const Text('Trascina per spostare · due dita per lo zoom'),
        value: _editing,
        onChanged: (value) => setState(() => _editing = value),
      ),
      LayoutBuilder(
        builder: (context, constraints) {
          final sourceWidth = number('width'), sourceHeight = number('height');
          if (sourceWidth <= 0 || sourceHeight <= 0) return widget.image;
          final width = math.min(
            constraints.maxWidth,
            520 * sourceWidth / sourceHeight,
          );
          final ratio = sourceWidth / width;
          return SizedBox(
            width: width,
            height: width * sourceHeight / sourceWidth,
            child: GestureDetector(
              key: const ValueKey('staff-photo-gesture'),
              behavior: HitTestBehavior.opaque,
              onScaleStart: !_editing
                  ? null
                  : (details) {
                      _start = {
                        'sx': number('sx'),
                        'sy': number('sy'),
                        'dx': number('dx'),
                        'dy': number('dy'),
                      };
                      _focal = details.localFocalPoint;
                    },
              onScaleUpdate: !_editing
                  ? null
                  : (details) {
                      if (_start == null) return;
                      _pending = {
                        'sx': (_start!['sx'] as double) * details.scale,
                        'sy': (_start!['sy'] as double) * details.scale,
                        'dx':
                            (_start!['dx'] as double) +
                            (details.localFocalPoint.dx - _focal.dx) * ratio,
                        'dy':
                            (_start!['dy'] as double) +
                            (details.localFocalPoint.dy - _focal.dy) * ratio,
                      };
                      _throttle ??= Timer(
                        const Duration(milliseconds: 50),
                        _flush,
                      );
                    },
              onScaleEnd: !_editing
                  ? null
                  : (_) {
                      _flush();
                      _start = null;
                    },
              child: DecoratedBox(
                decoration: BoxDecoration(
                  border: Border.all(
                    color: _editing
                        ? Theme.of(context).colorScheme.secondary
                        : Colors.transparent,
                    width: 2,
                  ),
                ),
                child: widget.image,
              ),
            ),
          );
        },
      ),
      if (_editing)
        TextButton.icon(
          onPressed: () {
            _throttle?.cancel();
            _pending = null;
            _start = null;
            widget.onTransform({'reset': true});
          },
          icon: const Icon(Icons.restart_alt),
          label: const Text('Ripristina foto'),
        ),
    ],
  );
}

/// For generators that already implement pointer editing, forward source-space
/// touches to that editor rather than maintaining another transform model.
class StaffPointerCanvas extends StatelessWidget {
  const StaffPointerCanvas({
    super.key,
    required this.image,
    required this.width,
    required this.height,
    required this.send,
  });
  final Widget image;
  final double width, height;
  final ValueChanged<Map<String, dynamic>> send;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final renderedWidth = math.min(
        constraints.maxWidth,
        520 * width / height,
      );
      final renderedHeight = renderedWidth * height / width;
      void pointer(PointerEvent event, String type) => send({
        'event': type,
        'pointer': event.pointer,
        'x': event.localPosition.dx / renderedWidth,
        'y': event.localPosition.dy / renderedHeight,
      });
      return Center(
        child: SizedBox(
          width: renderedWidth,
          height: renderedHeight,
          child: GestureDetector(
            onScaleStart: (_) {},
            onScaleUpdate: (_) {},
            child: Listener(
              onPointerDown: (event) => pointer(event, 'pointerdown'),
              onPointerMove: (event) => pointer(event, 'pointermove'),
              onPointerUp: (event) => pointer(event, 'pointerup'),
              onPointerCancel: (event) => pointer(event, 'pointercancel'),
              child: image,
            ),
          ),
        ),
      );
    },
  );
}
