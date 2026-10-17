import 'dart:async';
import 'dart:convert';

import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';

import 'ads.dart';

import 'package:flutter_quill/flutter_quill.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:url_launcher/url_launcher.dart';

import 'auth.dart';
import 'staff_controls.dart';
import 'staff_demo.dart';
import 'staff_engine.dart';
import 'staff_engine_native.dart'
    if (dart.library.js_interop) 'staff_engine_web.dart';
import 'staff_rich_editor.dart';
import 'staff_match_forms.dart';
import 'theme.dart';
import 'section_selector.dart';

class NativeStaffTool extends StatefulWidget {
  const NativeStaffTool({
    super.key,
    required this.auth,
    required this.title,
    required this.description,
    required this.path,
    required this.icon,
    required this.graphicsOnly,
    this.engineFactory,
  });
  final TosAuth auth;
  final String title, description, path;
  final IconData icon;
  final bool graphicsOnly;
  final StaffEngine Function(String, void Function(StaffMessage))?
  engineFactory;
  @override
  State<NativeStaffTool> createState() => _NativeStaffToolState();
}

class _NativeStaffToolState extends State<NativeStaffTool> {
  StaffEngine? _engine;
  Timer? _timeout;
  String _document = '', _channel = '', _identity = '';
  String _section = 'calcio';
  String? _error;
  List<dynamic> _blocks = [];
  bool _loading = true,
      _modal = false,
      _confirming = false,
      _actionBusy = false;
  int _run = 0;
  bool get _allowed =>
      widget.auth.admin || (widget.graphicsOnly && widget.auth.graphics);
  Map? get _lineup => _blocks
      .whereType<Map>()
      .where((block) => block['kind'] == 'match-lineup')
      .firstOrNull;
  @override
  void initState() {
    super.initState();
    _identity = '${widget.auth.user?['id']}';
    widget.auth.addListener(_authChanged);
    _start();
  }

  void _authChanged() {
    if (!_allowed || '${widget.auth.user?['id']}' != _identity) {
      _run++;
      _timeout?.cancel();
      _engine?.dispose();
      _engine = null;
      if (mounted) {
        setState(() {
          _blocks = [];
          _loading = false;
          _error = 'Accedi nuovamente al tuo account.';
        });
      }
    }
  }

  Future<void> _start() async {
    if (!_allowed) return;
    final run = ++_run;
    _timeout?.cancel();
    _engine?.dispose();
    _engine = null;
    _channel = TosAuth.randomValue();
    if (mounted) {
      setState(() {
        _error = null;
        _loading = true;
        _blocks = [];
      });
    }
    if (widget.auth.api.preview && widget.engineFactory == null) {
      _receive(staffDemoSnapshot(widget.path, widget.title));
      return;
    }
    try {
      final headers = await widget.auth.staffSessionHeaders();
      if (!mounted || run != _run || !_allowed) return;
      final source = Uri.parse(widget.path);
      final uri = widget.auth.api.uri('app-preview/staff-engine.php', {
        'tool': source.path,
        ...source.queryParameters,
        'sezione': _section,
        'channel': _channel,
      });
      final engine = (widget.engineFactory ?? createStaffEngine)(_channel, (
        message,
      ) {
        if (mounted && run == _run && _allowed) _receive(message);
      });
      _engine = engine;
      _timeout = Timer(const Duration(seconds: 35), () {
        if (mounted && _loading) {
          setState(() {
            _loading = false;
            _error = 'Lo strumento non risponde. Controlla la connessione e riprova.';
          });
        }
      });
      await engine.start(uri, headers);
      if (mounted && run == _run) setState(() {});
    } catch (_) {
      if (mounted && run == _run) {
        setState(() {
          _loading = false;
          _error =
              'Impossibile aprire lo strumento. Accedi nuovamente o riprova.';
        });
      }
    }
  }

  void _receive(StaffMessage message) {
    switch (message['type']) {
      case 'snapshot':
        _timeout?.cancel();
        setState(() {
          _document = '${message['document']}';
          _blocks = message['blocks'] as List? ?? [];
          _loading = false;
          _error = null;
          _modal = message['modal'] == true;
        });
      case 'loading':
        setState(() {
          _loading = true;
        });
        _timeout?.cancel();
        _timeout = Timer(const Duration(seconds: 45), () {
          if (mounted && _loading) {
            setState(() {
              _loading = false;
              _error = 'Risposta non ricevuta. Ricarica lo strumento per verificare il risultato prima di ripetere il salvataggio.';
            });
          }
        });
      case 'error':
        _timeout?.cancel();
        setState(() {
          _loading = false;
          _error = message['message'] as String?;
        });
      case 'notice':
        _notice('${message['message']}');
      case 'confirm':
        _confirm(message);
      case 'pick':
        _pick(Map<String, dynamic>.from(message['field'] as Map));
      case 'download':
        _save(message);
      case 'link':
        final uri = Uri.tryParse('${message['url']}');
        if (uri != null && uri.scheme == 'https') {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
    }
  }

  void _notice(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  void _send(StaffMessage message) {
    if (!_allowed) return;
    if (widget.auth.api.preview && _engine == null) {
      if (message['type'] == 'click') {
        if (['Crea', 'Modifica', 'Elimina'].contains(message['id'])) {
          setState(() {
            final tabs = _blocks
                .whereType<Map>()
                .where((b) => b['kind'] == 'tabs')
                .firstOrNull;
            for (final action in tabs?['blocks'] ?? []) {
              action['selected'] = action['id'] == message['id'];
            }
          });
        } else {
          _notice(
            'Operazione simulata. Nessun dato del sito è stato modificato.',
          );
        }
      }
      return;
    }
    _engine?.send({'document': _document, ...message});
  }

  Future<void> _click(Map block) async {
    if (_actionBusy) return;
    setState(() {
      _actionBusy = true;
    });
    _send({'type': 'click', 'id': block['id']});
    await Future<void>.delayed(const Duration(milliseconds: 650));
    if (mounted) {
      setState(() {
        _actionBusy = false;
      });
    }
  }

  Future<void> _confirm(StaffMessage message) async {
    if (_confirming) return;
    _confirming = true;
    final document = _document;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => StaffTheme(
        child: AlertDialog(
          title: Text('Conferma operazione'),
          content: Text('${message['message']}'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text('Annulla'),
            ),
            StaffActionButton(
              onPressed: () => Navigator.pop(context, true),
              label: 'Conferma',
              icon: staffActionIcon('Conferma'),
            ),
          ],
        ),
      ),
    );
    _confirming = false;
    if (accepted == true &&
        mounted &&
        document == _document &&
        message['action'] is Map) {
      _send({
        ...Map<String, dynamic>.from(message['action'] as Map),
        'approved': message['message'],
      });
    }
  }

  Future<void> _pick(Map<String, dynamic> field) async {
    final document = _document;
    try {
      final accept = '${field['accept'] ?? ''}'
          .split(',')
          .map((part) => part.trim())
          .toList();
      final extensions = accept
          .where((part) => part.startsWith('.'))
          .map((part) => part.substring(1))
          .toList();
      final types = accept.where((part) => part.contains('/')).toList();
      final groups = extensions.isEmpty && types.isEmpty
          ? <XTypeGroup>[]
          : [
              XTypeGroup(
                label: 'File supportati',
                extensions: extensions.isEmpty ? null : extensions,
                mimeTypes: types.isEmpty ? null : types,
              ),
            ];
      final files = field['multiple'] == true
          ? await openFiles(acceptedTypeGroups: groups)
          : [?await openFile(acceptedTypeGroups: groups)];
      if (files.isEmpty || !mounted || document != _document) return;
      final uploads = <Map<String, dynamic>>[];
      for (final file in files) {
        if (await file.length() > 32 * 1024 * 1024) {
          _notice(
            'Il file ${file.name} supera 32 MB. Scegli un file più piccolo.',
          );
          return;
        }
        uploads.add({
          'name': file.name,
          'mime': file.mimeType ?? _fileMime(file.name),
          'data': base64Encode(await file.readAsBytes()),
        });
      }
      if (mounted && document == _document) {
        _send({'type': 'files', 'id': field['id'], 'files': uploads});
      }
    } catch (_) {
      _notice('Impossibile caricare il file. Riprova.');
    }
  }

  String _fileMime(String name) => switch (name.split('.').last.toLowerCase()) {
    'png' => 'image/png',
    'jpg' || 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    _ => 'application/octet-stream',
  };
  Future<void> _save(StaffMessage file) async {
    try {
      final box = context.findRenderObject() as RenderBox?;
      final origin = box == null
          ? const Rect.fromLTWH(0, 0, 1, 1)
          : box.localToGlobal(Offset.zero) & box.size;
      await _engine?.save(file, origin);
    } catch (_) {
      _notice('Salvataggio non riuscito. Riprova.');
    }
  }

  @override
  void dispose() {
    _run++;
    _timeout?.cancel();
    _engine?.dispose();
    widget.auth.removeListener(_authChanged);
    super.dispose();
  }

  Widget _column(List blocks) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [for (final block in blocks.whereType<Map>()) _block(block)],
  );
  Widget _block(Map block) {
    final key = ValueKey(
      '$_document:${block['id'] ?? block['text'] ?? block['label']}',
    );
    switch (block['kind']) {
      case 'match-form':
        return StaffMatchForm(key: key, model: block, render: _block);
      case 'match-lineup':
        return StaffMatchLineup(
          key: key,
          model: block,
          render: _block,
          onChanged: (field, value) =>
              _send({'type': 'change', 'id': field['id'], ...value}),
          onAction: _click,
        );
      case 'field':
        return Padding(
          key: key,
          padding: const EdgeInsets.symmetric(vertical: 7),
          child: StaffNativeField(
            field: Map<String, dynamic>.from(block),
            onChanged: (value) =>
                _send({'type': 'change', 'id': block['id'], ...value}),
            onPick: () => _pick(Map<String, dynamic>.from(block)),
          ),
        );
      case 'action':
        final label = '${block['label']}';
        final disabled = block['disabled'] == true || _actionBusy;
        final danger = block['danger'] == true;
        return Padding(
          key: key,
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: block['tab'] == true
              ? StaffFilter(
                  label: label,
                  selected: block['selected'] == true,
                  onSelected: disabled ? null : (_) => _click(block),
                )
              : StaffActionButton(
                  label: label,
                  icon: danger
                      ? label.toLowerCase().contains('rifiuta')
                            ? Icons.close_rounded
                            : Icons.delete_outline_rounded
                      : staffActionIcon(label),
                  danger: danger,
                  onPressed: disabled ? null : () => _click(block),
                ),
        );
      case 'form':
      case 'panel':
        return Card(
          key: key,
          margin: const EdgeInsets.symmetric(vertical: 10),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: _column(block['blocks'] as List? ?? []),
          ),
        );
      case 'tabs':
        return Padding(
          key: key,
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              for (final child
                  in (block['blocks'] as List? ?? []).whereType<Map>())
                _block(child),
            ],
          ),
        );
      case 'expansion':
        return Card(
          key: key,
          child: ExpansionTile(
            title: Text(
              '${block['title'] ?? 'Impostazioni'}',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            maintainState: true,
            childrenPadding: const EdgeInsets.all(14),
            children: [_column(block['blocks'] as List? ?? [])],
          ),
        );
      case 'heading':
        return Padding(
          key: key,
          padding: const EdgeInsets.only(top: 18, bottom: 8),
          child: Text(
            '${block['text']}',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              color: Theme.of(context).colorScheme.onSurface,
            ),
          ),
        );
      case 'notice':
        if ('${block['text']}'.isEmpty) return const SizedBox.shrink();
        return Container(
          key: key,
          margin: const EdgeInsets.symmetric(vertical: 8),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surfaceContainerHigh,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text('${block['text']}'),
        );
      case 'text':
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 5),
          child: Text('${block['text']}', style: TextStyle(height: 1.4)),
        );
      case 'table':
        return StaffNativeRecords(
          key: key,
          table: Map<String, dynamic>.from(block),
          render: _block,
        );
      case 'image':
        final data = block['data'] as String?;
        final url = block['url'] as String?;
        final image = data != null
            ? Image.memory(
                base64Decode(data.split(',').last),
                gaplessPlayback: true,
                fit: BoxFit.contain,
              )
            : url != null
            ? Image.network(
                url,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Icon(Icons.broken_image_outlined),
              )
            : const SizedBox.shrink();
        return Card(
          key: key,
          margin: const EdgeInsets.symmetric(vertical: 12),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if ('${block['label'] ?? ''}'.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      '${block['label']}',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ConstrainedBox(
                  constraints: BoxConstraints(
                    maxHeight: block['export'] == true ? 520 : 180,
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: image,
                  ),
                ),
                if (block['export'] == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: StaffActionButton(
                      onPressed: () => _send({
                        'type': 'export',
                        'id': block['id'],
                        'name': '${block['label'] ?? 'grafica'}.png',
                      }),
                      icon: Icons.download_outlined,
                      label: 'Salva grafica',
                    ),
                  ),
              ],
            ),
          ),
        );
      default:
        return _column(block['blocks'] as List? ?? []);
    }
  }

  @override
  Widget build(BuildContext context) => StaffTheme(
    child: Localizations(
      locale: const Locale('it'),
      delegates: const [
        FlutterQuillLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      child: Scaffold(
        appBar: AppBar(
          leading: _lineup?['back'] is Map
              ? IconButton(
                  tooltip: 'Torna alla partita',
                  style: IconButton.styleFrom(
                    foregroundColor: Colors.white,
                    backgroundColor: const Color(0xff29445d),
                  ),
                  onPressed: _loading || _actionBusy
                      ? null
                      : () => _click(_lineup!['back'] as Map),
                  icon: Icon(Icons.arrow_back_rounded),
                )
              : Navigator.of(context).canPop()
              ? const StaffBackButton()
              : null,
          title: Text(_lineup == null ? widget.title : 'Statistiche partita'),
          actions: [
            if (_allowed)
              IconButton(
                tooltip: 'Ricarica',
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xff29445d),
                  foregroundColor: Colors.white,
                ),
                icon: Icon(Icons.refresh_rounded),
                onPressed: _loading
                    ? null
                    : _lineup == null
                    ? _start
                    : () => _send({'type': 'reload', 'id': _lineup!['id']}),
              ),
          ],
        ),
        bottomNavigationBar: _lineup == null
            ? null
            : SafeArea(
                child: Material(
                  color: Theme.of(context).colorScheme.surfaceContainerLow,
                  child: Container(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                    decoration: BoxDecoration(
                      border: Border(
                        top: BorderSide(
                          color: Theme.of(context).colorScheme.outlineVariant,
                        ),
                      ),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_lineup!['finalize'] is Map)
                          _block(_lineup!['finalize'] as Map),
                        Text(
                          'La finalizzazione segna la partita come giocata e invia le notifiche.',
                          style: TextStyle(
                            fontSize: 11,
                            color: Theme.of(context)
                                .colorScheme
                                .onSurfaceVariant,
                          ),
                        ),
                        if (_lineup!['save'] is Map)
                          _block(_lineup!['save'] as Map),
                      ],
                    ),
                  ),
                ),
              ),
        body: !_allowed
            ? const Center(child: Text('Area riservata allo staff.'))
            : Stack(
                children: [
                  if (_engine != null)
                    Positioned(
                      left: -1400,
                      top: 0,
                      width: 1200,
                      height: 1800,
                      child: _engine!.host,
                    ),
                  Positioned.fill(
                    child: ColoredBox(
                      color: Theme.of(context).scaffoldBackgroundColor,
                      child: _error != null
                          ? Center(
                              child: Padding(
                                padding: const EdgeInsets.all(24),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.cloud_off_outlined,
                                      size: 44,
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurface,
                                    ),
                                    const SizedBox(height: 16),
                                    Text(_error!, textAlign: TextAlign.center),
                                    const SizedBox(height: 18),
                                    StaffActionButton(
                                      onPressed: _start,
                                      icon: Icons.refresh,
                                      label: 'Riprova',
                                    ),
                                  ],
                                ),
                              ),
                            )
                          : _blocks.isEmpty && _loading
                          ? const Center(child: CircularProgressIndicator())
                          : SingleChildScrollView(
                              key: ValueKey('$_document:$_modal'),
                              padding: const EdgeInsets.fromLTRB(
                                16,
                                10,
                                16,
                                32,
                              ),
                              child: Center(
                                child: ConstrainedBox(
                                  constraints: const BoxConstraints(
                                    maxWidth: 720,
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.stretch,
                                    children: [
                                      if (!_modal && _lineup == null)
                                        Container(
                                          margin: const EdgeInsets.only(
                                            bottom: 8,
                                          ),
                                          padding: const EdgeInsets.all(18),
                                          decoration: BoxDecoration(
                                            gradient: const LinearGradient(
                                              colors: [
                                                siteBlue,
                                                Color(0xff244b6d),
                                              ],
                                            ),
                                            borderRadius: BorderRadius.circular(
                                              20,
                                            ),
                                          ),
                                          child: Row(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Icon(
                                                widget.icon,
                                                color: Colors.white,
                                                size: 30,
                                              ),
                                              const SizedBox(width: 14),
                                              Expanded(
                                                child: Text(
                                                  widget.description,
                                                  style: TextStyle(
                                                    color: Colors.white,
                                                    height: 1.4,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      if (!_modal &&
                                          !widget.graphicsOnly &&
                                          _lineup == null)
                                        Padding(
                                          padding: const EdgeInsets.symmetric(
                                            vertical: 12,
                                          ),
                                          child: SectionSelector(
                                            section: _section,
                                            onChanged: _loading
                                                ? null
                                                : (selection) {
                                                    _section = selection;
                                                    _start();
                                                  },
                                          ),
                                        ),
                                      _column(_blocks),
                                      const AdaptiveAdBanner(),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                    ),
                  ),
                  if (_loading && _blocks.isNotEmpty)
                    Positioned.fill(
                      child: ColoredBox(
                        color: Theme.of(context).colorScheme.surface
                            .withValues(alpha: .8),
                        child: Center(child: CircularProgressIndicator()),
                      ),
                    ),
                ],
              ),
      ),
    ),
  );
}

class StaffNativeField extends StatefulWidget {
  const StaffNativeField({
    super.key,
    required this.field,
    required this.onChanged,
    required this.onPick,
  });
  final Map<String, dynamic> field;
  final ValueChanged<StaffMessage> onChanged;
  final VoidCallback onPick;
  @override
  State<StaffNativeField> createState() => _StaffNativeFieldState();
}

class _StaffNativeFieldState extends State<StaffNativeField> {
  late final TextEditingController _text;
  final _focus = FocusNode();
  late String _value;
  late bool _checked;
  late List<String> _values;
  @override
  void initState() {
    super.initState();
    _value = '${widget.field['value'] ?? ''}';
    _text = TextEditingController(text: _value);
    _checked = widget.field['checked'] == true;
    _values = List<String>.from(widget.field['values'] as List? ?? []);
  }

  @override
  void didUpdateWidget(StaffNativeField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.field['value'] != widget.field['value']) {
      _value = '${widget.field['value'] ?? ''}';
      if (!_focus.hasFocus) _text.text = _value;
    }
    if (oldWidget.field['checked'] != widget.field['checked']) {
      _checked = widget.field['checked'] == true;
    }
    if (oldWidget.field['values'].toString() !=
        widget.field['values'].toString()) {
      _values = List<String>.from(widget.field['values'] as List? ?? []);
    }
  }

  @override
  void dispose() {
    _text.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _change(String value) {
    setState(() {
      _value = value;
    });
    widget.onChanged({'value': value});
  }

  Future<void> _select() async {
    final options = (widget.field['options'] as List? ?? [])
        .whereType<Map>()
        .toList();
    final multiple = widget.field['multiple'] == true;
    var search = '';
    final chosen = {..._values};
    final result = await showModalBottomSheet<List<String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StaffTheme(
        child: StatefulBuilder(
          builder: (context, refresh) => Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SizedBox(
              height: MediaQuery.sizeOf(context).height * .75,
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      '${widget.field['label']}',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: TextField(
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.search),
                        hintText: 'Cerca…',
                        border: OutlineInputBorder(),
                      ),
                      onChanged: (value) => refresh(() {
                        search = value.toLowerCase();
                      }),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Expanded(
                    child: ListView(
                      children: [
                        for (final option in options.where(
                          (o) => '${o['label']}'.toLowerCase().contains(search),
                        ))
                          multiple
                              ? CheckboxListTile(
                                  title: Text('${option['label']}'),
                                  value: chosen.contains(option['value']),
                                  onChanged: option['disabled'] == true
                                      ? null
                                      : (value) => refresh(() {
                                          value == true
                                              ? chosen.add('${option['value']}')
                                              : chosen.remove(
                                                  '${option['value']}',
                                                );
                                        }),
                                )
                              : ListTile(
                                  title: Text('${option['label']}'),
                                  selected: option['value'] == _value,
                                  trailing: option['value'] == _value
                                      ? Icon(
                                          Icons.check,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .error,
                                        )
                                      : null,
                                  enabled: option['disabled'] != true,
                                  onTap: () => Navigator.pop(context, [
                                    '${option['value']}',
                                  ]),
                                ),
                      ],
                    ),
                  ),
                  if (multiple)
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: StaffActionButton(
                        onPressed: () =>
                            Navigator.pop(context, chosen.toList()),
                        label: 'Applica selezione',
                        icon: staffActionIcon('Applica selezione'),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (result != null && mounted) {
      setState(() {
        _values = result;
        _value = result.firstOrNull ?? '';
      });
      widget.onChanged(multiple ? {'values': result} : {'value': _value});
    }
  }

  Future<void> _date() async {
    final type = widget.field['type'];
    var date = DateTime.tryParse(_value) ?? DateTime.now();
    if (type != 'time') {
      final picked = await showDatePicker(
        context: context,
        initialDate: date.year < 1900 || date.year > 2100
            ? DateTime.now()
            : date,
        firstDate: DateTime(1900),
        lastDate: DateTime(2100),
      );
      if (picked == null || !mounted) return;
      date = picked;
    }
    String formatted =
        '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    if (type == 'time' || type == 'datetime-local') {
      final parts = _value.split(':');
      final time = await showTimePicker(
        context: context,
        initialTime: type == 'time'
            ? TimeOfDay(
                hour: int.tryParse(parts.first) ?? 0,
                minute: int.tryParse(parts.last) ?? 0,
              )
            : TimeOfDay.fromDateTime(date),
      );
      if (time == null || !mounted) return;
      final value =
          '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
      formatted = type == 'time' ? value : '${formatted}T$value';
    }
    _text.text = formatted;
    _change(formatted);
  }

  @override
  Widget build(BuildContext context) {
    final f = widget.field, type = f['type'], disabled = f['disabled'] == true;
    final label = '${f['label']}${f['required'] == true ? ' *' : ''}';
    if (type == 'checkbox' || type == 'radio') {
      return CheckboxListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        value: _checked,
        controlAffinity: ListTileControlAffinity.leading,
        onChanged: disabled
            ? null
            : (value) {
                setState(() {
                  _checked = value!;
                });
                widget.onChanged({'checked': _checked});
              },
      );
    }
    if (type == 'rich') {
      return StaffRichEditor(
        value: '${f['value'] ?? ''}',
        label: label,
        onChanged: disabled
            ? (_) {}
            : (value) => widget.onChanged({'value': value}),
      );
    }
    if (type == 'file') {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(label, style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: disabled ? null : widget.onPick,
            icon: Icon(Icons.upload_file_outlined),
            label: Text('Scegli file'),
          ),
          for (final name in f['files'] as List? ?? [])
            Text('$name', maxLines: 2, overflow: TextOverflow.ellipsis),
        ],
      );
    }
    if (type == 'select') {
      final options = (f['options'] as List? ?? []).whereType<Map>();
      final selected = options
          .where(
            (o) => f['multiple'] == true
                ? _values.contains(o['value'])
                : o['value'] == _value,
          )
          .map((o) => '${o['label']}')
          .join(', ');
      return InkWell(
        onTap: disabled ? null : _select,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            enabled: !disabled,
            border: const OutlineInputBorder(
              borderRadius: BorderRadius.all(Radius.circular(12)),
            ),
            suffixIcon: Icon(Icons.expand_more),
          ),
          child: Text(
            selected.isEmpty ? 'Seleziona…' : selected,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      );
    }
    if (type == 'range') {
      final min = double.tryParse('${f['min']}') ?? 0,
          max = double.tryParse('${f['max']}') ?? 100;
      final value = (double.tryParse(_value) ?? min).clamp(min, max);
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('$label · ${value.round()}'),
          Slider(
            value: value,
            min: min,
            max: max,
            onChanged: disabled
                ? null
                : (value) => _change(value.round().toString()),
          ),
        ],
      );
    }
    final date = ['date', 'time', 'datetime-local'].contains(type);
    return TextField(
      controller: _text,
      focusNode: _focus,
      enabled: !disabled,
      readOnly: date,
      obscureText: type == 'password',
      maxLines: type == 'textarea' ? 5 : 1,
      textAlign: f['score'] == true ? TextAlign.center : TextAlign.start,
      style: f['score'] == true
          ? TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Theme.of(context).colorScheme.onSurface,
            )
          : null,
      keyboardType: type == 'number'
          ? const TextInputType.numberWithOptions(decimal: true, signed: true)
          : type == 'email'
          ? TextInputType.emailAddress
          : type == 'tel'
          ? TextInputType.phone
          : type == 'url'
          ? TextInputType.url
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(12)),
        ),
        suffixIcon: date
            ? IconButton(
                onPressed: disabled ? null : _date,
                icon: Icon(
                  type == 'time'
                      ? Icons.schedule
                      : Icons.calendar_month_outlined,
                ),
              )
            : null,
      ),
      onTap: date ? _date : null,
      onChanged: _change,
    );
  }
}

class StaffNativeRecords extends StatefulWidget {
  const StaffNativeRecords({
    super.key,
    required this.table,
    required this.render,
  });
  final Map<String, dynamic> table;
  final Widget Function(Map) render;
  @override
  State<StaffNativeRecords> createState() => _StaffNativeRecordsState();
}

class _StaffNativeRecordsState extends State<StaffNativeRecords> {
  String _search = '';
  final _searchController = TextEditingController();
  int _page = 0;
  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final rows = (widget.table['rows'] as List? ?? [])
        .whereType<Map>()
        .where(
          (row) => (row['search'] as String? ?? jsonEncode(row['cells']))
              .toLowerCase()
              .contains(_search.toLowerCase()),
        )
        .toList();
    final pages = (rows.length / 20).ceil();
    final page = pages == 0 ? 0 : _page.clamp(0, pages - 1);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 8,
          children: [
            for (final action
                in (widget.table['sorting'] as List? ?? []).whereType<Map>())
              widget.render(action),
          ],
        ),
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: TextField(
            controller: _searchController,
            decoration: InputDecoration(
              hintText: 'Cerca nell’elenco (${rows.length})',
              prefixIcon: Icon(Icons.search),
              suffixIcon: _search.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Cancella ricerca elenco',
                      icon: const Icon(Icons.close),
                      onPressed: () => setState(() {
                        _searchController.clear();
                        _search = '';
                        _page = 0;
                      }),
                    ),
              border: const OutlineInputBorder(
                borderRadius: BorderRadius.all(Radius.circular(14)),
              ),
            ),
            onChanged: (value) => setState(() {
              _search = value;
              _page = 0;
            }),
          ),
        ),
        if (rows.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              'Elementi ${page * 20 + 1}–${(page * 20 + 20).clamp(0, rows.length)} di ${rows.length}',
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ),
        if (rows.isEmpty)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text('Nessun elemento da mostrare.'),
          ),
        for (final row in rows.skip(page * 20).take(20))
          Card(
            margin: const EdgeInsets.symmetric(vertical: 6),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final cell
                      in (row['cells'] as List? ?? []).whereType<Map>()) ...[
                    if ('${cell['label'] ?? ''}'.isNotEmpty)
                      Text(
                        '${cell['label']}',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                    for (final block
                        in (cell['blocks'] as List? ?? []).whereType<Map>())
                      widget.render(block),
                    const SizedBox(height: 8),
                  ],
                ],
              ),
            ),
          ),
        if (pages > 1)
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                tooltip: 'Precedenti',
                onPressed: page == 0
                    ? null
                    : () => setState(() {
                        _page = page - 1;
                      }),
                icon: Icon(Icons.chevron_left),
              ),
              Text('${page + 1} / $pages'),
              IconButton(
                tooltip: 'Successivi',
                onPressed: page >= pages - 1
                    ? null
                    : () => setState(() {
                        _page = page + 1;
                      }),
                icon: Icon(Icons.chevron_right),
              ),
            ],
          ),
      ],
    );
  }
}
