import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/admin.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/external_link_button.dart';
import 'package:tornei_old_school/main.dart';
import 'package:tornei_old_school/staff_dashboard.dart';
import 'package:tornei_old_school/native_staff_tool.dart';

class StaffApi extends DemoApi {
  int userRequests = 0;
  @override
  Future<dynamic> request(
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    String? accessToken,
    List<ApiUpload>? uploads,
  }) {
    if (path == 'api/mobile/v1/admin/users.php') userRequests++;
    return super.request(
      path,
      query: query,
      body: body,
      accessToken: accessToken,
      uploads: uploads,
    );
  }
}

void main() {
  testWidgets(
    'Management stays hidden for guests and disappears after logout',
    (tester) async {
      final api = StaffApi();
      final auth = DemoAuth(api);
      await tester.pumpWidget(TosApp(api: api, auth: auth));
      await tester.pumpAndSettle();
      expect(find.text('Gestione'), findsNothing);
      expect(api.userRequests, 0);

      tester
          .widget<NavigationBar>(find.byType(NavigationBar))
          .onDestinationSelected!(4);
      await tester.pumpAndSettle();
      expect(find.byType(StaffDashboard), findsNothing);
      expect(
        tester.widget<NavigationBar>(find.byType(NavigationBar)).selectedIndex,
        0,
      );

      await auth.login();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Gestione'));
      await tester.pumpAndSettle();
      expect(find.byType(StaffDashboard), findsOneWidget);

      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.text('Gestione'), findsNothing);
      expect(find.byType(StaffDashboard), findsNothing);
      expect(
        tester.widget<NavigationBar>(find.byType(NavigationBar)).selectedIndex,
        0,
      );
      expect(find.text('Consensi e preferenze'), findsNothing);
      expect(api.userRequests, 0);
      expect(tester.takeException(), isNull);
    },
  );

  for (final role in ['admin', 'grafico', 'user']) {
    testWidgets('Management dashboard respects $role permissions at 320px', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(320, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final api = StaffApi();
      final auth = DemoAuth(api)
        ..user = {
          ...api.users.first,
          'ruolo': role,
          'permissions': {'admin': role == 'admin', 'graphics': role != 'user'},
        };
      await tester.pumpWidget(TosApp(api: api, auth: auth));
      await tester.pumpAndSettle();
      expect(api.userRequests, 0);
      if (role == 'user') {
        expect(find.text('Gestione'), findsNothing);
        return;
      }
      await tester.tap(find.text('Gestione'));
      await tester.pumpAndSettle();
      expect(find.byType(StaffDashboard), findsOneWidget);
      expect(api.userRequests, 0);
      if (role == 'grafico') {
        expect(find.text('Generatore grafiche'), findsOneWidget);
      }
      expect(tester.takeException(), isNull);
      if (role == 'grafico') {
        expect(find.text('Gestione account'), findsNothing);
        expect(find.text('Dashboard amministratore'), findsNothing);
        expect(find.text('Apri dashboard'), findsNothing);
        expect(find.text('Tornei'), findsOneWidget); // Only bottom navigation.
        expect(find.text('Matchday'), findsOneWidget);
        await Scrollable.ensureVisible(
          tester.element(find.text('Template')),
          alignment: 0.5,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Template'));
        await tester.pumpAndSettle();
        expect(find.byType(StaffToolPage), findsOneWidget);
        final tool = tester.widget<NativeStaffTool>(
          find.byType(NativeStaffTool),
        );
        expect(Uri.parse(tool.path).queryParameters, {'templates': '1'});
        expect(find.byType(StaffNativeField), findsWidgets);
        expect(find.byType(ExternalLinkButton), findsNothing);
        expect(api.userRequests, 0);
      } else {
        expect(find.text('Dashboard amministratore'), findsOneWidget);
        await tester.enterText(find.byType(TextField), 'Utenti');
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Gestione account'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Gestione account'));
        await tester.pumpAndSettle();
        expect(api.userRequests, 1);
        expect(find.text('Luca Demo'), findsOneWidget);
      }
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('Graphics user cannot load account list directly', (
    tester,
  ) async {
    final api = StaffApi();
    final auth = DemoAuth(api)
      ..user = {
        'permissions': {'admin': false, 'graphics': true},
      };
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: AdminUsersPage(auth: auth)),
      ),
    );
    await tester.pumpAndSettle();
    expect(api.userRequests, 0);
    expect(find.text('Account riservati agli amministratori.'), findsOneWidget);
  });
  testWidgets('Admin filters native tools and graphic tools reject non-staff', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 1100));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = StaffApi();
    final auth = DemoAuth(api)..user = api.users.first;
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: const TextScaler.linear(1.6)),
          child: child!,
        ),
        home: Scaffold(body: StaffDashboard(auth: auth)),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Grafiche'),
      180,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await Scrollable.ensureVisible(
      tester.element(find.text('Grafiche')),
      alignment: .5,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Grafiche'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Matchday').hitTestable(),
      250,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Matchday'), findsOneWidget);
    expect(find.text('Calendario e risultati'), findsNothing);
    expect(find.text('Account giocatore'), findsNothing);
    expect(api.userRequests, 0);
    await tester.ensureVisible(find.text('Matchday'));
    await tester.tap(find.text('Matchday'));
    await tester.pumpAndSettle();
    expect(find.byType(StaffToolPage), findsOneWidget);
    expect(tester.takeException(), isNull);
    auth.user = api.users.last;
    await tester.pumpWidget(
      MaterialApp(
        home: StaffToolPage(auth: auth, tool: graphicsTools.first),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Area riservata allo staff.'), findsOneWidget);
    expect(find.byType(ExternalLinkButton), findsNothing);
  });
}
