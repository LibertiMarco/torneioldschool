import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'update_gate.dart';
import 'auth.dart';
import 'login_page.dart';
import 'ads.dart';
import 'push.dart';
import 'access_gate.dart';
import 'staff_dashboard.dart';
import 'account_page.dart';
import 'personal_player_page.dart';

import 'demo.dart';
import 'theme.dart';
import 'section_selector.dart';
import 'tournament_list.dart';
import 'home_dashboard.dart';
import 'browser_runtime_stub.dart'
    if (dart.library.js_interop) 'browser_runtime_web.dart';

export 'tournament_page.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  if (const bool.fromEnvironment('TOS_PREVIEW')) {
    final api = DemoApi();
    runApp(TosApp(api: api, auth: DemoAuth(api)));
    return;
  }
  if (const bool.fromEnvironment('TOS_WEB_PREVIEW')) {
    final auth = createBrowserAuth();
    runApp(TosApp(api: auth.api, auth: auth));
    return;
  }
  final api = TosApi(
    baseUrl: Uri.parse(
      const String.fromEnvironment(
        'TOS_BASE_URL',
        defaultValue: 'https://torneioldschool.it',
      ),
    ),
  );
  runApp(TosApp(api: api, auth: TosAuth(api)));
}

class TosApp extends StatefulWidget {
  const TosApp({
    super.key,
    required this.api,
    required this.auth,
    this.themeStorage,
  });
  final TosApi api;
  final TosAuth auth;
  final SessionStore? themeStorage;
  @override
  State<TosApp> createState() => _TosAppState();
}

class _TosAppState extends State<TosApp> {
  static const _themeKey = 'tos.app.theme';
  final _navigatorKey = GlobalKey<NavigatorState>();
  final _messengerKey = GlobalKey<ScaffoldMessengerState>();
  late final _push = PushController(
    auth: widget.auth,
    backend: FirebasePushBackend(),
    supported:
        !kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS) &&
        !widget.api.preview,
  );
  final List<StreamSubscription<RemoteMessage>> _pushListeners = [];
  late final SessionStore _themeStorage =
      widget.themeStorage ?? const SecureSessionStore();
  ThemeMode _themeMode = ThemeMode.light;
  bool _themeChanged = false;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb && !widget.api.preview) {
      widget.auth.presentNativeLogin = () async {
        final navigator = _navigatorKey.currentState;
        if (navigator != null) {
          await navigator.push(
            MaterialPageRoute<void>(
              builder: (_) => NativeLoginPage(auth: widget.auth),
            ),
          );
        }
      };
    }
    _loadTheme();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && !widget.api.preview) {
        unawaited(_startServices());
      }
    });
  }

  Future<void> _startServices() async {
    await AdsController.instance.start();
    if (!mounted) return;
    await _startPush();
    if (mounted) await _push.requestInitialPermission();
  }

  Future<void> _startPush() async {
    await _push.start();
    if (!mounted || !_push.ready) return;
    _pushListeners.add(
      FirebaseMessaging.onMessage.listen((message) {
        if (widget.auth.user == null || !_push.enabled) return;
        final notification = message.notification;
        _messengerKey.currentState?.showSnackBar(
          SnackBar(
            content: Text(
              [
                notification?.title,
                notification?.body,
              ].whereType<String>().join('\n'),
            ),
            action: pushLink(message.data['url']?.toString()) == null
                ? null
                : SnackBarAction(
                    label: 'Apri',
                    onPressed: () => _openPush(message),
                  ),
          ),
        );
      }),
    );
    _pushListeners.add(FirebaseMessaging.onMessageOpenedApp.listen(_openPush));
    try {
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (mounted && initial != null) _openPush(initial);
    } catch (_) {
      // Opening the app must remain possible when a notification cannot be read.
    }
  }

  void _openPush(RemoteMessage message) {
    final url = pushLink(message.data['url']?.toString());
    if (url == null) return;
    if (url.path == '/account.php') {
      final navigator = _navigatorKey.currentState;
      if (navigator != null) {
        navigator.push(
          MaterialPageRoute<void>(
            builder: (_) => AccountPage(auth: widget.auth, section: 'calcio'),
          ),
        );
      }
      return;
    }
    launchUrl(url, mode: LaunchMode.inAppBrowserView);
  }

  @override
  void dispose() {
    for (final listener in _pushListeners) {
      listener.cancel();
    }
    widget.auth.presentNativeLogin = null;
    _push.dispose();
    super.dispose();
  }

  Future<void> _loadTheme() async {
    try {
      final value = await _themeStorage.read(_themeKey);
      if (!mounted || _themeChanged) return;
      setState(
        () => _themeMode = value == 'dark' ? ThemeMode.dark : ThemeMode.light,
      );
    } catch (_) {
      // The app opens in light mode when local preferences are unavailable.
    }
  }

  Future<void> _setTheme(ThemeMode value) async {
    _themeChanged = true;
    setState(() => _themeMode = value);
    try {
      await _themeStorage.write(
        _themeKey,
        value == ThemeMode.dark ? 'dark' : 'light',
      );
    } catch (_) {
      // Keep the selected theme for this session if storage is unavailable.
    }
  }

  Future<void> _showThemePicker() async {
    final pickerContext = _navigatorKey.currentContext;
    if (pickerContext == null) return;
    final selection = await showModalBottomSheet<ThemeMode>(
      context: pickerContext,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Aspetto', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              Text(
                'Scegli il tema dell’app',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 18),
              SegmentedButton<ThemeMode>(
                segments: const [
                  ButtonSegment(
                    value: ThemeMode.light,
                    icon: Icon(Icons.light_mode_outlined),
                    label: Text('Chiaro'),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    icon: Icon(Icons.dark_mode_outlined),
                    label: Text('Scuro'),
                  ),
                ],
                selected: {_themeMode},
                onSelectionChanged: (values) =>
                    Navigator.pop(context, values.first),
              ),
            ],
          ),
        ),
      ),
    );
    if (selection != null) await _setTheme(selection);
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    navigatorKey: _navigatorKey,
    scaffoldMessengerKey: _messengerKey,
    title: 'Tornei Old School',
    debugShowCheckedModeBanner: false,
    builder: (context, child) => PushScope(
      controller: _push,
      child: AppAuthScope(
        auth: widget.auth,
        child: widget.api.preview
            ? Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 560),
                  child: Column(
                    children: [
                      Material(
                        color: const Color(0xffffe6a7),
                        child: SafeArea(
                          bottom: false,
                          child: SizedBox(
                            width: double.infinity,
                            child: Padding(
                              padding: const EdgeInsets.all(10),
                              child: Text(
                                'Anteprima · dati dimostrativi',
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.labelLarge,
                              ),
                            ),
                          ),
                        ),
                      ),
                      Expanded(child: child!),
                    ],
                  ),
                ),
              )
            : !kIsWeb &&
                  (defaultTargetPlatform == TargetPlatform.android ||
                      defaultTargetPlatform == TargetPlatform.iOS)
            ? UpdateGate(api: widget.api, child: child!)
            : child!,
      ),
    ),
    theme: siteTheme(),
    darkTheme: siteDarkTheme(),
    themeMode: _themeMode,
    home: HomePage(
      api: widget.api,
      auth: widget.auth,
      onTheme: _showThemePicker,
    ),
  );
}

class HomePage extends StatefulWidget {
  const HomePage({
    super.key,
    required this.api,
    required this.auth,
    required this.onTheme,
  });
  final TosApi api;
  final TosAuth auth;
  final VoidCallback onTheme;
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int page = 0;
  String section = 'calcio';
  bool _hadUser = false;
  @override
  void initState() {
    super.initState();
    _hadUser = widget.auth.user != null;
    widget.auth.addListener(_authChanged);
    widget.auth.restore();
  }

  void _authChanged() {
    final hasUser = widget.auth.user != null;
    if (page == 4 && !(widget.auth.admin || widget.auth.graphics)) {
      page = 0;
    }
    if (_hadUser && !hasUser) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
      });
    }
    _hadUser = hasUser;
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.auth.removeListener(_authChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    final hasStaffAccess = auth.admin || auth.graphics;
    final selectedPage = page == 4 && !hasStaffAccess ? 0 : page;
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
        foregroundColor: Theme.of(context).appBarTheme.foregroundColor,
        elevation: 0,
        title: Row(
          children: [
            Image.asset('assets/logo_old_school.png', width: 34, height: 34),
            const SizedBox(width: 12),
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: const Text('Tornei Old School', maxLines: 1),
              ),
            ),
          ],
        ),
        actions: [
          const AdsPrivacyButton(),
          IconButton(
            tooltip: 'Aspetto chiaro o scuro',
            onPressed: widget.onTheme,
            icon: Icon(
              Theme.of(context).brightness == Brightness.dark
                  ? Icons.dark_mode_rounded
                  : Icons.light_mode_rounded,
            ),
          ),
        ],
      ),
      body: selectedPage <= 2
          ? Column(
              children: [
                SectionSelector(
                  section: section,
                  onChanged: (value) => setState(() => section = value),
                ),
                Expanded(
                  child: selectedPage == 0
                      ? HomeDashboard(
                          key: ValueKey('home-$section-${auth.user?['id']}'),
                          api: widget.api,
                          auth: auth,
                          section: section,
                          onTournaments: () => setState(() => page = 1),
                        )
                      : selectedPage == 1
                      ? AccessGate(
                          builder: (_) => TournamentList(
                            key: ValueKey('tournaments-$section'),
                            api: widget.api,
                            section: section,
                          ),
                        )
                      : PersonalPlayerPage(
                          auth: auth,
                          section: section,
                          onAccount: () => setState(() => page = 3),
                        ),
                ),
              ],
            )
          : selectedPage == 3
          ? auth.user == null
                ? _account(auth)
                : AccountPage(auth: auth, section: section)
          : StaffDashboard(auth: auth),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedPage,
        onDestinationSelected: (value) {
          if (value == 4 && !(auth.admin || auth.graphics)) return;
          setState(() => page = value);
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            label: 'Home',
          ),
          const NavigationDestination(
            icon: Icon(Icons.emoji_events_outlined),
            label: 'Tornei',
          ),
          const NavigationDestination(
            icon: Icon(Icons.badge_outlined),
            selectedIcon: Icon(Icons.badge),
            label: 'Profilo',
          ),
          const NavigationDestination(
            icon: Icon(Icons.manage_accounts_outlined),
            label: 'Account',
          ),
          if (hasStaffAccess)
            const NavigationDestination(
              icon: Icon(Icons.admin_panel_settings_outlined),
              label: 'Gestione',
            ),
        ],
      ),
    );
  }

  Widget _account(TosAuth auth) => ListView(
    padding: const EdgeInsets.all(24),
    children: [
      const Icon(Icons.account_circle_outlined, size: 72),
      const SizedBox(height: 24),
      if (auth.user == null) ...[
        const Text(
          'Il tuo account Old School',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        const Text('Accedi con lo stesso account che utilizzi sul sito.'),
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: auth.busy ? null : auth.login,
          icon: const Icon(Icons.login),
          label: Text(
            auth.busy
                ? 'Accesso in corso…'
                : widget.api.preview
                ? 'Accedi come admin demo'
                : 'Accedi',
          ),
        ),
      ] else ...[
        Text(
          '${auth.user!['nome']} ${auth.user!['cognome']}',
          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Text('${auth.user!['email']}'),
        const SizedBox(height: 16),
        Text('Ruolo: ${auth.user!['ruolo']}'),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: auth.busy ? null : auth.logout,
          icon: const Icon(Icons.logout),
          label: const Text('Esci'),
        ),
      ],
      if (auth.error != null)
        Padding(
          padding: const EdgeInsets.only(top: 16),
          child: Text(
            auth.error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
      TextButton.icon(
        onPressed: () => launchUrl(
          widget.api.baseUrl.resolve('/privacy.php'),
          mode: LaunchMode.externalApplication,
        ),
        icon: const Icon(Icons.policy_outlined),
        label: const Text('Informativa privacy'),
      ),
    ],
  );
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel({super.key, required this.error, required this.retry});
  final Object error;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) =>
      AccessRequiredPanel.handles(context, error)
      ? AccessRequiredPanel(retry: retry)
      : Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.cloud_off_outlined, size: 40),
                const SizedBox(height: 16),
                Text(
                  error is ApiError
                      ? error.toString()
                      : 'Servizio temporaneamente non disponibile.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                FilledButton(onPressed: retry, child: const Text('Riprova')),
              ],
            ),
          ),
        );
}
