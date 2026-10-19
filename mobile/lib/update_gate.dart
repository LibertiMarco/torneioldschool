import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';

class UpdateGate extends StatefulWidget {
  const UpdateGate({super.key, required this.api, required this.child});
  final TosApi api;
  final Widget child;

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> with WidgetsBindingObserver {
  static const _channel = MethodChannel('tos/app_version');
  Timer? _timer;
  bool _checking = true;
  bool _busy = false;
  bool _required = false;
  String? _error;
  Uri? _url;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _check();
    _timer = Timer.periodic(const Duration(minutes: 1), (_) => _check());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _check();
  }

  Future<void> _check() async {
    if (_busy) return;
    _busy = true;
    try {
      final build = await _channel.invokeMethod<int>('buildNumber');
      if (build == null) throw const FormatException('Missing build');
      final response = await widget.api.client
          .get(
            widget.api.uri('api/mobile/v1/app_version.php', {
              't': DateTime.now().millisecondsSinceEpoch.toString(),
            }),
            headers: {'Cache-Control': 'no-cache'},
          )
          .timeout(const Duration(seconds: 15));
      if (response.statusCode != 200) throw const FormatException('Policy');
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final platform = defaultTargetPlatform == TargetPlatform.iOS
          ? 'ios'
          : 'android';
      final policy = data[platform] as Map<String, dynamic>;
      final latest = policy['build'] as int;
      final url = Uri.parse(policy['url'] as String);
      if (latest < 1 || url.scheme != 'https' || url.host.isEmpty) {
        throw const FormatException('Invalid release policy');
      }
      if (!mounted) return;
      setState(() {
        _required = build < latest;
        _url = url;
        _checking = false;
        _error = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Impossibile verificare gli aggiornamenti. Controlla la connessione e riprova.';
      });
    } finally {
      _busy = false;
    }
  }

  Future<void> _update() async {
    try {
      if (_url != null &&
          await launchUrl(_url!, mode: LaunchMode.externalApplication)) {
        return;
      }
    } catch (_) {
      // Keep the mandatory update screen visible if the store cannot open.
    }
    if (mounted) {
      setState(() => _error = 'Impossibile aprire il download. Riprova.');
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_checking && !_required) return widget.child;
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.system_update, size: 64),
                  const SizedBox(height: 24),
                  Text(
                    _required
                        ? 'Aggiornamento necessario'
                        : 'Verifica aggiornamenti',
                    style: Theme.of(context).textTheme.headlineSmall,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  if (_required)
                    const Text(
                      'È disponibile una nuova versione. Aggiorna l’app per continuare.',
                      textAlign: TextAlign.center,
                    ),
                  if (_error != null)
                    Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                  if (_required)
                    FilledButton(
                      onPressed: _update,
                      child: const Text('Aggiorna ora'),
                    ),
                  if (_error != null || _required)
                    TextButton(
                      onPressed: _check,
                      child: const Text('Verifica di nuovo'),
                    )
                  else
                    const CircularProgressIndicator(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
