import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/staff_photo_canvas.dart';

void main() {
  for (final format in ['reel', 'youtube']) {
    testWidgets(
      '$format cover supports immediate touch drag, pinch and reset',
      (tester) async {
        final transforms = <Map<String, dynamic>>[];
        final width = format == 'reel' ? 1080.0 : 1280.0;
        final height = format == 'reel' ? 1920.0 : 720.0;
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: StaffPhotoCanvas(
                  image: const ColoredBox(color: Colors.blue),
                  photo: {
                    'width': width,
                    'height': height,
                    'sx': 1,
                    'sy': 1,
                    'dx': 0,
                    'dy': 0,
                    'autoEnable': true,
                  },
                  onTransform: transforms.add,
                ),
              ),
            ),
          ),
        );
        expect(
          tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
          isTrue,
        );
        final canvas = find.byKey(const ValueKey('staff-photo-gesture'));
        await tester.drag(canvas, const Offset(30, 20));
        await tester.pump(const Duration(milliseconds: 60));
        expect(transforms, isNotEmpty);
        expect(transforms.last['dx'], greaterThan(0));
        expect(transforms.last['dy'], greaterThan(0));
        expect(transforms.last['sx'], 1);

        final center = tester.getCenter(canvas);
        final first = await tester.startGesture(center - const Offset(35, 0));
        final second = await tester.startGesture(
          center + const Offset(35, 0),
          pointer: 2,
        );
        await tester.pump();
        await first.moveBy(const Offset(-35, 0));
        await second.moveBy(const Offset(35, 0));
        await tester.pump(const Duration(milliseconds: 60));
        await first.up();
        await second.up();
        expect(transforms.last['sx'], greaterThan(1));
        expect(transforms.last['sx'], transforms.last['sy']);

        await tester.ensureVisible(find.text('Ripristina foto'));
        await tester.tap(find.text('Ripristina foto'));
        expect(transforms.last, {'reset': true});
        await tester.tap(find.byType(SwitchListTile));
        await tester.pump();
        final count = transforms.length;
        await tester.drag(canvas, const Offset(20, 30));
        await tester.pump(const Duration(milliseconds: 60));
        expect(
          transforms.length,
          count,
          reason: 'Disabled editor allows page scrolling',
        );
      },
    );
  }
}
