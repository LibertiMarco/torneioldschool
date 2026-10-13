import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'staff_engine.dart';
import 'theme.dart';

typedef StaffBlockRenderer = Widget Function(Map block);

class StaffMatchForm extends StatelessWidget {
  const StaffMatchForm({super.key, required this.model, required this.render});
  final Map model;
  final StaffBlockRenderer render;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final section in (model['sections'] as List? ?? []).whereType<Map>())
        Card(
          margin: const EdgeInsets.symmetric(vertical: 8),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${section['title']}',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final columns =
                        constraints.maxWidth >= 480 &&
                            MediaQuery.textScalerOf(context).scale(14) < 21
                        ? 2
                        : 1;
                    return Wrap(
                      spacing: 12,
                      runSpacing: 4,
                      children: [
                        for (final block
                            in (section['blocks'] as List? ?? [])
                                .whereType<Map>())
                          SizedBox(
                            width: block['score'] == true
                                ? (constraints.maxWidth - 12) / 2
                                : (constraints.maxWidth - 12 * (columns - 1)) /
                                      columns,
                            child: render(block),
                          ),
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      for (final block in (model['blocks'] as List? ?? []).whereType<Map>())
        render(block),
    ],
  );
}

class StaffMatchLineup extends StatelessWidget {
  const StaffMatchLineup({
    super.key,
    required this.model,
    required this.render,
    required this.onChanged,
    required this.onAction,
  });
  final Map model;
  final StaffBlockRenderer render;
  final void Function(Map field, StaffMessage value) onChanged;
  final void Function(Map action) onAction;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [siteBlue, Color(0xff245c78)]),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${model['home']}',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Flexible(
                  flex: 2,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      '${model['score']}',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 30,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    '${model['away']}',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            if ('${model['info'] ?? ''}'.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                '${model['info']}',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Color(0xffd2e7f1),
                  fontSize: 12,
                  height: 1.4,
                ),
              ),
            ],
          ],
        ),
      ),
      Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Text(
          'Seleziona chi ha giocato e inserisci gli eventi nella distinta. Il risultato si aggiorna con gol e autogol.',
        ),
      ),
      if ('${model['notice'] ?? ''}'.isNotEmpty)
        Semantics(
          liveRegion: true,
          child: Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: model['error'] == true
                  ? Theme.of(context).colorScheme.errorContainer
                  : Theme.of(context).colorScheme.surfaceContainerHigh,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Text('${model['notice']}'),
          ),
        ),
      if ('${model['loading'] ?? ''}'.isNotEmpty) Text('${model['loading']}'),
      for (final team in (model['teams'] as List? ?? []).whereType<Map>())
        Card(
          key: ValueKey(team['id']),
          margin: const EdgeInsets.only(bottom: 16),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                color: Theme.of(context).brightness == Brightness.dark
                    ? Theme.of(context).colorScheme.surfaceContainerHigh
                    : siteBlue,
                padding: const EdgeInsets.all(16),
                child: Text(
                  '${team['name']}',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(12),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final action
                        in (team['actions'] as List? ?? []).whereType<Map>())
                      OutlinedButton(
                        onPressed: action['disabled'] == true
                            ? null
                            : () => onAction(action),
                        child: Text('${action['label']}'),
                      ),
                  ],
                ),
              ),
              for (final player
                  in (team['players'] as List? ?? []).whereType<Map>())
                _LineupPlayer(
                  key: ValueKey(player['id']),
                  player: player,
                  render: render,
                  onChanged: onChanged,
                ),
            ],
          ),
        ),
    ],
  );
}

class _LineupPlayer extends StatelessWidget {
  const _LineupPlayer({
    super.key,
    required this.player,
    required this.render,
    required this.onChanged,
  });
  final Map player;
  final StaffBlockRenderer render;
  final void Function(Map field, StaffMessage value) onChanged;

  @override
  Widget build(BuildContext context) {
    final fields = (player['fields'] as List? ?? []).whereType<Map>().toList();
    final presence = fields.where((f) => f['stat'] == 'presenza').firstOrNull;
    final present = presence?['checked'] == true;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: present
            ? Theme.of(context).colorScheme.surfaceContainerLow
            : Theme.of(context).colorScheme.surfaceContainerHighest,
        border: Border(
          top: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (presence != null)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(
                  '${player['name']}',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(present ? 'Presente' : 'Non presente'),
                value: present,
                onChanged: presence['disabled'] == true
                    ? null
                    : (value) => onChanged(presence, {'checked': value}),
              ),
            if ((player['badges'] as List? ?? []).isNotEmpty)
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final badge in player['badges'])
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: badge == 'DIFFIDATO'
                            ? (Theme.of(context).brightness == Brightness.dark
                                  ? const Color(0xff574725)
                                  : const Color(0xffffefbe))
                            : Theme.of(context)
                                  .colorScheme
                                  .surfaceContainerHigh,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '$badge',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                ],
              ),
            const SizedBox(height: 12),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns =
                    constraints.maxWidth >= 312 &&
                        MediaQuery.textScalerOf(context).scale(14) < 21
                    ? 3
                    : constraints.maxWidth >= 216
                    ? 2
                    : 1;
                return Wrap(
                  spacing: 8,
                  runSpacing: 12,
                  children: [
                    for (final field in fields.where(
                      (f) => ['goal', 'assist', 'autogol'].contains(f['stat']),
                    ))
                      SizedBox(
                        width:
                            (constraints.maxWidth - 8 * (columns - 1)) /
                            columns,
                        child: StaffStatCounter(
                          key: ValueKey(field['id']),
                          field: field,
                          onChanged: (value) =>
                              onChanged(field, {'value': value}),
                        ),
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final field in fields.where(
                  (f) => [
                    'cartellino_giallo',
                    'cartellino_rosso',
                  ].contains(f['stat']),
                ))
                  FilterChip(
                    avatar: Container(
                      width: 12,
                      height: 17,
                      decoration: BoxDecoration(
                        color: field['stat'] == 'cartellino_giallo'
                            ? const Color(0xffffc928)
                            : const Color(0xffdf2947),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    label: Text(
                      field['stat'] == 'cartellino_giallo' ? 'Giallo' : 'Rosso',
                    ),
                    labelStyle: TextStyle(
                      color: field['checked'] == true
                          ? Theme.of(context).colorScheme.onPrimary
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                    selected: field['checked'] == true,
                    onSelected: field['disabled'] == true
                        ? null
                        : (value) => onChanged(field, {'checked': value}),
                  ),
              ],
            ),
            for (final field in fields.where((f) => f['stat'] == 'voto'))
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: render(field),
              ),
          ],
        ),
      ),
    );
  }
}

class StaffStatCounter extends StatefulWidget {
  const StaffStatCounter({
    super.key,
    required this.field,
    required this.onChanged,
  });
  final Map field;
  final ValueChanged<String> onChanged;
  @override
  State<StaffStatCounter> createState() => _StaffStatCounterState();
}

class _StaffStatCounterState extends State<StaffStatCounter> {
  late final TextEditingController _text;
  final _focus = FocusNode();
  String? _pendingValue;
  @override
  void initState() {
    super.initState();
    _text = TextEditingController(text: '${widget.field['value'] ?? '0'}');
  }

  @override
  void didUpdateWidget(StaffStatCounter oldWidget) {
    super.didUpdateWidget(oldWidget);
    final incoming = '${widget.field['value'] ?? '0'}';
    if (incoming == _pendingValue) _pendingValue = null;
    // An intermediate controller snapshot must not undo rapid taps.
    if (!_focus.hasFocus && _pendingValue == null && _text.text != incoming) {
      _text.text = incoming;
    }
  }

  @override
  void dispose() {
    _text.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _step(int step) {
    final next = (int.tryParse(_text.text) ?? 0) + step;
    final value = next < 0 ? 0 : next;
    setState(() {
      _pendingValue = '$value';
      _text.text = '$value';
    });
    widget.onChanged('$value');
  }

  @override
  Widget build(BuildContext context) {
    final label = '${widget.field['label']}';
    final disabled = widget.field['disabled'] == true;
    return Column(
      children: [
        TextField(
          controller: _text,
          focusNode: _focus,
          enabled: !disabled,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: Theme.of(context).colorScheme.onSurface,
          ),
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: InputDecoration(
            labelText: label,
            isDense: true,
            filled: true,
            fillColor: Theme.of(context).colorScheme.surfaceContainerLowest,
            contentPadding: const EdgeInsets.symmetric(vertical: 10),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onChanged: (value) {
            setState(() => _pendingValue = value);
            widget.onChanged(value);
          },
        ),
        const SizedBox(height: 4),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            IconButton(
              tooltip: 'Diminuisci $label',
              onPressed: disabled || (int.tryParse(_text.text) ?? 0) <= 0
                  ? null
                  : () => _step(-1),
              icon: Icon(Icons.remove_rounded, size: 18),
            ),
            IconButton(
              tooltip: 'Aumenta $label',
              onPressed: disabled ? null : () => _step(1),
              icon: Icon(Icons.add_rounded, size: 18),
            ),
          ],
        ),
      ],
    );
  }
}
