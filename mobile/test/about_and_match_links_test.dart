import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/about_page.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/external_link_button.dart';
import 'package:tornei_old_school/home_dashboard.dart';

class AboutApi extends DemoApi {
  bool unavailable = false;
  int calls = 0;

  @override
  Future<Map<String, dynamic>> about() async {
    calls++;
    if (unavailable) throw const ApiError('Contenuti non disponibili.');
    return {
      ...await super.about(),
      'staff': [
        {
          'label': 'Arbitri',
          'description':
              'Direzione di gara affidata al nostro team di ufficiali.',
          'members': [
            {'nome': 'Arbitro dal sito', 'ruolo': 'Direttore di gara'},
          ],
        },
      ],
    };
  }
}

class LinkedMatchApi extends DemoApi {
  @override
  Future<Map<String, dynamic>> match(String id) async => {
    ...await super.match(id),
    'link_youtube': 'https://youtu.be/partita',
    'link_instagram': 'https://www.instagram.com/reel/partita/',
  };
}

void main() {
  const channel = MethodChannel('plugins.flutter.io/url_launcher');
  final launched = <String>[];
  bool launchSucceeds = true;

  setUp(() {
    launched.clear();
    launchSucceeds = true;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          if (call.method == 'launch') {
            launched.add((call.arguments as Map)['url'] as String);
            return launchSucceeds;
          }
          return true;
        });
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  testWidgets('Home opens complete about content and live staff at 320 px', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = AboutApi();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HomeDashboard(
            api: api,
            section: 'calcio',
            onTournaments: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Scopri chi siamo'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Scopri chi siamo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Scopri chi siamo'));
    await tester.pumpAndSettle();
    expect(find.byType(AboutPage), findsOneWidget);
    expect(api.calls, 1);
    expect(
      find.text('Passione, amicizia e sport - lo spirito Old School'),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Organizzatori'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(AboutPage),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('Frank'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Emanuele'),
      150,
      scrollable: find
          .descendant(
            of: find.byType(AboutPage),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('Emanuele'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Arbitro dal sito'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(AboutPage),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('Direttore di gara'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('About content can be retried after a connection failure', (
    tester,
  ) async {
    final api = AboutApi()..unavailable = true;
    await tester.pumpWidget(MaterialApp(home: AboutPage(api: api)));
    await tester.pumpAndSettle();
    expect(find.text('Contenuti non disponibili.'), findsOneWidget);
    api.unavailable = false;
    await tester.tap(find.text('Riprova'));
    await tester.pumpAndSettle();
    expect(api.calls, 2);
    expect(
      find.text('Passione, amicizia e sport - lo spirito Old School'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('Match page opens both supplied links on a narrow phone', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      MaterialApp(
        home: MatchPage(api: LinkedMatchApi(), id: '1'),
      ),
    );
    await tester.pumpAndSettle();
    for (final label in ['Guarda su YouTube', 'Guarda su Instagram']) {
      await tester.scrollUntilVisible(find.text(label), 150);
      await tester.pumpAndSettle();
      await tester.tap(find.text(label));
      await tester.pumpAndSettle();
    }
    expect(launched, [
      'https://youtu.be/partita',
      'https://www.instagram.com/reel/partita/',
    ]);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Unavailable links are hidden and failed launches show a message',
    (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MatchLinks(
              match: {
                'link_youtube': 'javascript:alert(1)',
                'link_instagram': '',
              },
            ),
          ),
        ),
      );
      expect(find.byType(ExternalLinkButton), findsNothing);
      launchSucceeds = false;
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MatchLinks(
              match: {
                'link_youtube': 'https://youtu.be/partita',
                'link_instagram': null,
              },
            ),
          ),
        ),
      );
      await tester.tap(find.text('Guarda su YouTube'));
      await tester.pumpAndSettle();
      expect(find.text('Guarda su Instagram'), findsNothing);
      expect(find.text('Impossibile aprire il link. Riprova.'), findsOneWidget);
    },
  );

  test(
    'External links normalize web addresses and accept explicit email links',
    () {
      expect(
        externalLinkUri('youtu.be/partita')?.toString(),
        'https://youtu.be/partita',
      );
      expect(
        externalLinkUri('//www.instagram.com/reel/partita/')?.scheme,
        'https',
      );
      expect(externalLinkUri('https://user:password@example.com'), isNull);
      expect(externalLinkUri('mailto:info@torneioldschool.it'), isNull);
      expect(
        externalLinkUri(
          'mailto:info@torneioldschool.it',
          allowEmail: true,
        )?.scheme,
        'mailto',
      );
    },
  );
}
