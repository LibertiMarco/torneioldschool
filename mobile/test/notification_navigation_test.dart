import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/access_gate.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/details.dart';
import 'package:tornei_old_school/notification_navigation.dart';

class NotificationApi extends DemoApi {
  final opened = <String>[];
  @override
  Future<Map<String, dynamic>> match(String id) async {
    opened.add('match:$id');
    return {
      'id': id,
      'giocata': 1,
      'squadra_casa': 'Casa',
      'squadra_ospite': 'Ospite',
      'gol_casa': 2,
      'gol_ospite': 1,
    };
  }

  @override
  Future<List<Map<String, dynamic>>> events(String id) async {
    opened.add('events:$id');
    return [];
  }

  @override
  Future<Map<String, dynamic>> article(String id) async {
    opened.add('article:$id');
    return {
      'id': id,
      'titolo': 'Risposta al commento',
      'contenuto': '<p>Notizia di prova</p>',
    };
  }
}

void main() {
  test('Routes valid match and article links, rejects untrusted or malformed links', () {
    final target = pushDestination(
      'https://torneioldschool.it/tornei/partita_eventi.php?id=42&torneo=Coppa',
    );
    expect(target!.screen, PushScreen.match);
    expect(target.id, '42');
    expect(
      pushDestination('https://torneioldschool.it/articolo.php?id=7#commenti')!
          .screen,
      PushScreen.article,
    );
    expect(
      pushDestination('https://torneioldschool.it/account.php')!.screen,
      PushScreen.account,
    );
    expect(
      pushDestination('https://torneioldschool.it/unknown.php')!.screen,
      PushScreen.account,
    );
    for (final url in [
      'https://evil.test/tornei/partita_eventi.php?id=42',
      'https://torneioldschool.it@evil.test/tornei/partita_eventi.php?id=42',
      'http://torneioldschool.it/tornei/partita_eventi.php?id=42',
      'https://torneioldschool.it:444/tornei/partita_eventi.php?id=42',
      'https://torneioldschool.it/tornei/partita_eventi.php?id=-1',
      'https://torneioldschool.it/tornei/partita_eventi.php?id=42&id=43',
      'https://torneioldschool.it/tornei/partita_eventi.php?id=9999999999',
      'https://torneioldschool.it/tornei/partita_eventi.php',
    ]) {
      expect(pushDestination(url), isNull, reason: url);
    }
  });

  testWidgets(
    'Cold-start tap waits for navigator, opens match statistics once and returns home',
    (tester) async {
      final api = NotificationApi();
      final auth = DemoAuth(api);
      await auth.login();
      final key = GlobalKey<NavigatorState>();
      final routing = NotificationNavigator(
        api: api,
        auth: auth,
        navigatorKey: key,
      );
      routing.open(
        'https://torneioldschool.it/tornei/partita_eventi.php?id=42',
        messageId: 'push-42',
      );
      await tester.pumpWidget(
        const MaterialApp(home: Text('Verifica aggiornamenti')),
      );
      await tester.pumpAndSettle();
      expect(api.opened, isEmpty);
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: key,
          navigatorObservers: [routing],
          builder: (_, child) => AppAuthScope(auth: auth, child: child!),
          home: const Scaffold(body: Text('Home app')),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(MatchPage), findsOneWidget);
      expect(find.text('Dettaglio partita'), findsOneWidget);
      expect(api.opened, ['match:42', 'events:42']);
      expect(
        routing.open(
          'https://torneioldschool.it/tornei/partita_eventi.php?id=42',
          messageId: 'push-42',
        ),
        false,
      );
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Home app'), findsOneWidget);
      routing.dispose();
    },
  );

  testWidgets(
    'Signed-out notification preserves destination through native login',
    (tester) async {
      final api = NotificationApi();
      final auth = DemoAuth(api);
      final key = GlobalKey<NavigatorState>();
      final routing = NotificationNavigator(
        api: api,
        auth: auth,
        navigatorKey: key,
      );
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: key,
          navigatorObservers: [routing],
          builder: (_, child) => AppAuthScope(auth: auth, child: child!),
          home: const Scaffold(body: Text('Home app')),
        ),
      );
      routing.open(
        'https://torneioldschool.it/tornei/partita_eventi.php?id=43',
      );
      await tester.pumpAndSettle();
      expect(find.text('Accesso richiesto'), findsOneWidget);
      expect(api.opened, isEmpty);
      await auth.login();
      await tester.pumpAndSettle();
      expect(find.byType(MatchPage), findsOneWidget);
      expect(api.opened, ['match:43', 'events:43']);
      routing.dispose();
    },
  );

  testWidgets('Comment reply opens the article in the app', (tester) async {
    final api = NotificationApi();
    final auth = DemoAuth(api);
    await auth.login();
    final key = GlobalKey<NavigatorState>();
    final routing = NotificationNavigator(
      api: api,
      auth: auth,
      navigatorKey: key,
    );
    await tester.pumpWidget(
      MaterialApp(
        navigatorKey: key,
        navigatorObservers: [routing],
        builder: (_, child) => AppAuthScope(auth: auth, child: child!),
        home: const Scaffold(body: Text('Home app')),
      ),
    );
    routing.open('https://torneioldschool.it/articolo.php?id=7');
    await tester.pumpAndSettle();
    expect(find.text('Risposta al commento'), findsOneWidget);
    expect(api.opened, ['article:7']);
    routing.dispose();
  });
}
