import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_photo_canvas.dart';

void main() {
  testWidgets('One finger moves the photo without scrolling the page', (
    tester,
  ) async {
    final scroll = ScrollController();
    final events = <Map<String, dynamic>>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            controller: scroll,
            child: Column(
              children: [
                StaffPointerCanvas(
                  image: const ColoredBox(color: Colors.blue),
                  width: 1080,
                  height: 1920,
                  photo: const {
                    'active': true,
                    'selection': {'x': .1, 'y': .2, 'width': .8, 'height': .5},
                  },
                  send: events.add,
                ),
                const SizedBox(height: 1000),
              ],
            ),
          ),
        ),
      ),
    );
    await tester.drag(find.byType(StaffPointerCanvas), const Offset(0, -100));
    await tester.pumpAndSettle();
    expect(scroll.offset, 0);
    expect(events.where((e) => e['event'] == 'pointermove'), isNotEmpty);
    expect(events.last['event'], 'pointerup');
    await tester.pumpWidget(const SizedBox());
    scroll.dispose();
  });
  testWidgets(
    'Website photo editor receives drag, resize handles and both fingers',
    (tester) async {
      final events = <Map<String, dynamic>>[];
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 360,
              child: StaffPointerCanvas(
                image: const ColoredBox(color: Colors.blue),
                width: 1080,
                height: 1920,
                photo: const {
                  'active': true,
                  'selection': {'x': .1, 'y': .2, 'width': .8, 'height': .5},
                },
                send: events.add,
              ),
            ),
          ),
        ),
      );
      expect(
        find.byKey(const ValueKey('staff-photo-handle-se')),
        findsOneWidget,
      );
      final resize = await tester.startGesture(
        tester.getCenter(find.byKey(const ValueKey('staff-photo-handle-se'))),
      );
      await resize.moveBy(const Offset(15, 10));
      await resize.up();
      expect(events.first['handle'], 'se');
      expect(
        events.map((e) => e['event']),
        containsAllInOrder(['pointerdown', 'pointermove', 'pointerup']),
      );
      expect(events[1]['x'], greaterThan(events[0]['x'] as double));
      events.clear();
      final origin = tester.getTopLeft(find.byType(StaffPointerCanvas));
      final first = await tester.startGesture(
        origin + const Offset(120, 200),
        pointer: 3,
      );
      final second = await tester.startGesture(
        origin + const Offset(180, 200),
        pointer: 4,
      );
      await first.moveBy(const Offset(-20, 10));
      await second.moveBy(const Offset(20, 10));
      await first.up();
      await second.up();
      expect(events.where((e) => e['event'] == 'pointerdown').length, 2);
      expect(
        events
            .where((e) => e['event'] == 'pointermove')
            .map((e) => e['pointer'])
            .toSet()
            .length,
        2,
      );
      expect(events.first['handle'], '');
    },
  );
}
