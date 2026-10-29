import 'package:flutter/material.dart';

import 'auth.dart';

class LogoSettingsCard extends StatefulWidget {
  const LogoSettingsCard({super.key, required this.auth});
  final TosAuth auth;

  @override
  State<LogoSettingsCard> createState() => _LogoSettingsCardState();
}

class _LogoSettingsCardState extends State<LogoSettingsCard> {
  bool _busy = true;
  bool _loaded = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.auth.admin) _request();
  }

  Future<void> _request([bool? enabled]) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = enabled == null
          ? await widget.auth.api.logoSettings()
          : await widget.auth.authorized(
              'api/mobile/v1/logo_settings.php',
              body: {'original_logos': enabled},
            );
      widget.auth.api.originalLogos.value = result['original_logos'] == true;
      if (mounted) setState(() => _loaded = true);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = enabled == null
              ? 'Impossibile caricare l’impostazione.'
              : 'Salvataggio non riuscito. Riprova.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.auth.admin) return const SizedBox.shrink();
    return Card(
      child: Column(
        children: [
          ValueListenableBuilder<bool>(
            valueListenable: widget.auth.api.originalLogos,
            builder: (context, enabled, _) => SwitchListTile(
              title: const Text('Loghi originali di squadre e tornei'),
              subtitle: const Text(
                'Attivo: mostra i loghi originali. Disattivo: mostra gli emblemi '
                'con iniziali. Vale per tutti nell’app e nella preview.',
              ),
              value: enabled,
              onChanged: _busy || !_loaded ? null : _request,
            ),
          ),
          if (_busy) const LinearProgressIndicator(),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(child: Text(_error!)),
                  TextButton(
                    onPressed: _busy ? null : () => _request(),
                    child: const Text('Ricarica'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
