import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:webview_flutter_platform_interface/webview_flutter_platform_interface.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/login_page.dart';

class CaptchaPlatform extends WebViewPlatform {
  late CaptchaController controller;
  @override
  PlatformWebViewController createPlatformWebViewController(
    PlatformWebViewControllerCreationParams params,
  ) => controller = CaptchaController(params);
  @override
  PlatformNavigationDelegate createPlatformNavigationDelegate(
    PlatformNavigationDelegateCreationParams params,
  ) => CaptchaNavigation(params);
  @override
  PlatformWebViewWidget createPlatformWebViewWidget(
    PlatformWebViewWidgetCreationParams params,
  ) => CaptchaWidget(params);
}

class CaptchaController extends PlatformWebViewController {
  CaptchaController(super.params) : super.implementation();
  final channels = <String, JavaScriptChannelParams>{};
  int loads = 0;
  @override
  Future<void> setJavaScriptMode(JavaScriptMode mode) async {}
  @override
  Future<void> addJavaScriptChannel(JavaScriptChannelParams params) async {
    channels[params.name] = params;
  }

  @override
  Future<void> setPlatformNavigationDelegate(
    PlatformNavigationDelegate handler,
  ) async {}
  @override
  Future<void> loadRequest(LoadRequestParams params) async {
    loads++;
  }

  void send(String channel, String value) =>
      channels[channel]!.onMessageReceived(JavaScriptMessage(message: value));
}

class CaptchaNavigation extends PlatformNavigationDelegate {
  CaptchaNavigation(super.params) : super.implementation();
  @override
  Future<void> setOnNavigationRequest(
    NavigationRequestCallback callback,
  ) async {}
  @override
  Future<void> setOnWebResourceError(WebResourceErrorCallback callback) async {}
}

class CaptchaWidget extends PlatformWebViewWidget {
  CaptchaWidget(super.params) : super.implementation();
  @override
  Widget build(BuildContext context) =>
      const SizedBox(key: ValueKey('inline-captcha-view'));
}

void main() {
  testWidgets('Captcha stays in the form, retries and expires', (tester) async {
    final previous = WebViewPlatform.instance;
    final platform = CaptchaPlatform();
    WebViewPlatform.instance = platform;
    if (previous != null) {
      addTearDown(() => WebViewPlatform.instance = previous);
    }
    final auth = DemoAuth(DemoApi());
    await tester.pumpWidget(MaterialApp(home: NativeLoginPage(auth: auth)));
    await tester.enterText(
      find.byType(TextFormField).first,
      'test@example.test',
    );
    await tester.enterText(find.byType(TextFormField).last, 'Password123!');
    await tester.ensureVisible(find.text('Non sono un robot'));
    await tester.tap(find.text('Non sono un robot'));
    await tester.pumpAndSettle();
    expect(find.byType(Scaffold), findsOneWidget);
    expect(find.byKey(const ValueKey('inline-captcha-view')), findsOneWidget);
    expect(
      tester
          .widget<TextFormField>(find.byType(TextFormField).first)
          .controller!
          .text,
      'test@example.test',
    );
    platform.controller.send('TosCaptchaStatus', 'error');
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Riprova verifica'));
    await tester.tap(find.text('Riprova verifica'));
    await tester.pumpAndSettle();
    expect(platform.controller.loads, 2);
    platform.controller.send('TosCaptcha', '');
    await tester.pump();
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    platform.controller.send('TosCaptcha', 'test-token');
    await tester.pumpAndSettle();
    expect(find.text('Verifica completata'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );
    await tester.pump(const Duration(seconds: 111));
    expect(find.text('Non sono un robot'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    expect(auth.user, isNull);
  });
  testWidgets(
    'Login shows verification before submit and guards keyboard submit',
    (tester) async {
      final auth = DemoAuth(DemoApi());
      await tester.pumpWidget(MaterialApp(home: NativeLoginPage(auth: auth)));
      expect(find.text('Non sono un robot'), findsOneWidget);
      expect(
        tester.getTopLeft(find.text('Non sono un robot')).dy,
        greaterThan(tester.getTopLeft(find.byType(TextFormField).last).dy),
      );
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      await tester.enterText(
        find.byType(TextFormField).at(0),
        'test@example.test',
      );
      await tester.enterText(find.byType(TextFormField).at(1), 'Password123!');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();
      expect(
        find.text('Completa prima la verifica Non sono un robot.'),
        findsOneWidget,
      );
      expect(find.text('Verifica di sicurezza'), findsNothing);
      expect(auth.user, isNull);
      await tester.ensureVisible(find.text('Password dimenticata?'));
      await tester.tap(find.text('Password dimenticata?'));
      await tester.pumpAndSettle();
      expect(find.text('Non sono un robot'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
    },
  );
}
