import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'staff_engine.dart';
import 'lineup_ad_slot.dart';
import 'theme.dart';

typedef StaffBlockRenderer = Widget Function(Map block);

/// Calendar data and navigation come from the same controller as the website.
class StaffMatchCalendar extends StatelessWidget {
  const StaffMatchCalendar({
    super.key,
    required this.model,
    required this.render,
  });
  final Map model;
  final StaffBlockRenderer render;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final days = (model['days'] as List? ?? []).whereType<Map>().toList();
    final first = (model['firstHour'] as num?)?.toInt() ?? 16;
    final last = (model['lastHour'] as num?)?.toInt() ?? 22;
    Widget cell(Widget child) =>
        Padding(padding: const EdgeInsets.all(6), child: child);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              '${model['title'] ?? 'Calendario del campo'}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final action
                    in (model['actions'] as List? ?? []).whereType<Map>())
                  render(action),
              ],
            ),
            Text(
              '${model['note'] ?? ''}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 12),
            if (days.isNotEmpty)
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 520),
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: SizedBox(
                    width: 52 + days.length * 160.0,
                    child: SingleChildScrollView(
                      child: Table(
                        columnWidths: {0: const FixedColumnWidth(52)},
                        border: TableBorder.all(
                          color: colors.outlineVariant,
                          width: 0.5,
                        ),
                        defaultVerticalAlignment:
                            TableCellVerticalAlignment.top,
                        children: [
                          TableRow(
                            decoration: BoxDecoration(
                              color: colors.surfaceContainerLow,
                            ),
                            children: [
                              const SizedBox(),
                              for (final day in days)
                                cell(
                                  Text(
                                    '${day['label']}',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      color: day['today'] == true
                                          ? siteRed
                                          : colors.onSurface,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          for (var hour = first; hour <= last; hour++)
                            TableRow(
                              children: [
                                cell(
                                  Text(
                                    '${hour.toString().padLeft(2, '0')}:00',
                                    style: const TextStyle(fontSize: 11),
                                  ),
                                ),
                                for (final day in days)
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      minHeight: 70,
                                    ),
                                    child: cell(
                                      Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.stretch,
                                        children: [
                                          for (final event
                                              in (day['events'] as List? ?? [])
                                                  .whereType<Map>()
                                                  .where(
                                                    (event) =>
                                                        int.tryParse(
                                                          '${event['time']}'
                                                              .split(':')
                                                              .first,
                                                        ) ==
                                                        hour,
                                                  ))
                                            Container(
                                              margin: const EdgeInsets.only(
                                                bottom: 4,
                                              ),
                                              padding: const EdgeInsets.all(8),
                                              decoration: BoxDecoration(
                                                color: event['draft'] == true
                                                    ? const Color(0xfffff1df)
                                                    : colors.primaryContainer,
                                                borderRadius:
                                                    BorderRadius.circular(8),
                                                border: Border(
                                                  left: BorderSide(
                                                    width: 3,
                                                    color:
                                                        event['draft'] == true
                                                        ? const Color(
                                                            0xffcf7200,
                                                          )
                                                        : colors.primary,
                                                  ),
                                                ),
                                              ),
                                              child: DefaultTextStyle(
                                                style: TextStyle(
                                                  fontSize: 12,
                                                  color: event['draft'] == true
                                                      ? colors
                                                            .onTertiaryContainer
                                                      : colors
                                                            .onPrimaryContainer,
                                                ),
                                                child: Column(
                                                  crossAxisAlignment:
                                                      CrossAxisAlignment.start,
                                                  children: [
                                                    Text(
                                                      '${event['time']}',
                                                      style: const TextStyle(
                                                        fontWeight:
                                                            FontWeight.w800,
                                                      ),
                                                    ),
                                                    Text('${event['title']}'),
                                                    Text(
                                                      '${event['subtitle']}',
                                                      style: const TextStyle(
                                                        fontSize: 10,
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

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
        padding: const EdgeInsets.all(12),
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
                SizedBox(
                  width: 80,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Semantics(
                      liveRegion: true,
                      child: Text(
                        '${model['score']}',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 26,
                        ),
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
        padding: EdgeInsets.symmetric(vertical: 10),
        child: Text(
          'Il risultato include i presenti. Gli autogol vanno agli avversari.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 12),
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
      for (final (teamIndex, team)
          in (model['teams'] as List? ?? []).whereType<Map>().indexed) ...[
        if (teamIndex == 1)
          const LineupAdSlot(key: ValueKey('lineup-between-teams')),
        Card(
          key: ValueKey(team['id']),
          margin: const EdgeInsets.only(bottom: 10),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                color: Theme.of(context).brightness == Brightness.dark
                    ? Theme.of(context).colorScheme.surfaceContainerHigh
                    : siteBlue,
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                child: Text(
                  '${team['name']}',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if ((team['actions'] as List? ?? []).isNotEmpty)
                Padding(
                  padding: const EdgeInsets.all(8),
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
              for (final (index, player)
                  in (team['players'] as List? ?? []).whereType<Map>().indexed)
                _LineupPlayer(
                  key: ValueKey(player['id']),
                  player: player,
                  alternate: index.isOdd,
                  render: render,
                  onChanged: onChanged,
                ),
            ],
          ),
        ),
        if (teamIndex == 1)
          const LineupAdSlot(key: ValueKey('lineup-after-away')),
      ],
    ],
  );
}

class StaffFinalizeControl extends StatelessWidget {
  const StaffFinalizeControl({
    super.key,
    required this.field,
    required this.played,
    required this.onChanged,
  });
  final Map field;
  final bool played;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final selected = field['checked'] == true;
    return Material(
      animationDuration: const Duration(milliseconds: 180),
      color: selected ? colors.primaryContainer : colors.surfaceContainerHigh,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: selected ? colors.primary : colors.outlineVariant,
        ),
      ),
      child: SwitchListTile.adaptive(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
        title: const Text(
          'Finalizza partita',
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
        ),
        subtitle: Text(
          played
              ? 'Partita finalizzata. Le modifiche non inviano nuove notifiche.'
              : selected
              ? 'Al salvataggio: partita giocata e notifiche inviate.'
              : 'Attiva per segnare la partita come giocata al salvataggio.',
          style: const TextStyle(fontSize: 11),
        ),
        value: selected,
        onChanged: field['disabled'] == true ? null : onChanged,
      ),
    );
  }
}

class _LineupPlayer extends StatelessWidget {
  const _LineupPlayer({
    super.key,
    required this.player,
    required this.alternate,
    required this.render,
    required this.onChanged,
  });
  final Map player;
  final bool alternate;
  final StaffBlockRenderer render;
  final void Function(Map field, StaffMessage value) onChanged;

  @override
  Widget build(BuildContext context) {
    final fields = (player['fields'] as List? ?? []).whereType<Map>().toList();
    final presence = fields.where((f) => f['stat'] == 'presenza').firstOrNull;
    final present = presence?['checked'] == true;
    final badges = player['badges'] as List? ?? [];
    final roleBadges = badges
        .where((badge) => badge == 'GK' || badge == 'C')
        .toList();
    final otherBadges = badges
        .where((badge) => badge != 'GK' && badge != 'C')
        .toList();
    Widget badgeChip(dynamic badge) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: badge == 'DIFFIDATO'
            ? (Theme.of(context).brightness == Brightness.dark
                  ? const Color(0xff574725)
                  : const Color(0xffffefbe))
            : Theme.of(context).colorScheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        '$badge',
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
      ),
    );
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Theme.of(context).brightness == Brightness.dark
            ? (alternate
                  ? Theme.of(context).colorScheme.surfaceContainer
                  : Theme.of(context).colorScheme.surfaceContainerLow)
            : (alternate ? const Color(0xffe8eef5) : const Color(0xfff7f9fc)),
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
                dense: true,
                visualDensity: VisualDensity.compact,
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Row(
                  children: [
                    Flexible(
                      child: Text(
                        '${player['name']}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    for (final badge in roleBadges) ...[
                      const SizedBox(width: 6),
                      badgeChip(badge),
                    ],
                  ],
                ),
                value: present,
                onChanged: presence['disabled'] == true
                    ? null
                    : (value) => onChanged(presence, {'checked': value}),
              ),
            if (otherBadges.isNotEmpty)
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [for (final badge in otherBadges) badgeChip(badge)],
              ),
            const SizedBox(height: 6),
            LayoutBuilder(
              builder: (context, constraints) {
                final minCounterWidth =
                    64 + MediaQuery.textScalerOf(context).scale(28);
                final columns =
                    ((constraints.maxWidth + 6) / (minCounterWidth + 6))
                        .floor()
                        .clamp(1, 3);
                return Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final field in fields.where(
                      (f) => ['goal', 'assist', 'autogol'].contains(f['stat']),
                    ))
                      SizedBox(
                        width:
                            (constraints.maxWidth - 6 * (columns - 1)) /
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
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final field in fields.where(
                  (f) => [
                    'cartellino_giallo',
                    'cartellino_rosso',
                  ].contains(f['stat']),
                ))
                  FilterChip(
                    showCheckmark: false,
                    padding: const EdgeInsets.all(4),
                    labelPadding: const EdgeInsets.symmetric(horizontal: 4),
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
                      fontSize: 12,
                      color: field['checked'] == true
                          ? Theme.of(context).colorScheme.onPrimary
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                    selected: field['checked'] == true,
                    onSelected: field['disabled'] == true
                        ? null
                        : (value) => onChanged(field, {'checked': value}),
                  ),
                for (final field in fields.where((f) => f['stat'] == 'voto'))
                  SizedBox(width: 104, child: render(field)),
              ],
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
    final buttonStyle = IconButton.styleFrom(
      minimumSize: const Size(32, 40),
      maximumSize: const Size(32, double.infinity),
      padding: const EdgeInsets.all(4),
      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surfaceContainerLowest,
            border: Border.all(color: Theme.of(context).colorScheme.outline),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              IconButton(
                tooltip: 'Diminuisci $label',
                style: buttonStyle,
                onPressed: disabled || (int.tryParse(_text.text) ?? 0) <= 0
                    ? null
                    : () => _step(-1),
                icon: const Icon(Icons.remove_rounded, size: 18),
              ),
              Expanded(
                child: Semantics(
                  label: label,
                  child: TextField(
                    controller: _text,
                    focusNode: _focus,
                    enabled: !disabled,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: Theme.of(context).colorScheme.onSurface,
                    ),
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(
                      isDense: true,
                      filled: false,
                      contentPadding: EdgeInsets.symmetric(vertical: 8),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      disabledBorder: InputBorder.none,
                    ),
                    onChanged: (value) {
                      setState(() => _pendingValue = value);
                      widget.onChanged(value);
                    },
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Aumenta $label',
                style: buttonStyle,
                onPressed: disabled ? null : () => _step(1),
                icon: const Icon(Icons.add_rounded, size: 18),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
