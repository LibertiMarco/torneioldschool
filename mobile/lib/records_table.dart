import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;
import 'theme.dart' show siteBlue;

/// Un unico prospetto generale, sotto le classifiche dei giocatori.
class RecordsTable extends StatefulWidget {
  const RecordsTable({super.key, required this.api});
  final TosApi api;
  @override
  State<RecordsTable> createState() => _RecordsTableState();
}

class _RecordsTableState extends State<RecordsTable> {
  ColorScheme get colors => Theme.of(context).colorScheme;
  final expanded = <String>{};
  late Future<dynamic> data = load();
  Future<dynamic> load() =>
      widget.api.request('api/records.php', query: {'scope': 'records'});
  void reload() {
    setState(() {
      data = load();
      expanded.clear();
    });
  }

  ButtonStyle get subjectStyle => TextButton.styleFrom(
    padding: EdgeInsets.zero,
    minimumSize: const Size(0, 22),
    alignment: Alignment.centerLeft,
    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    foregroundColor: colors.onSurface,
  );

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Container(
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: colors.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: siteBlue,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            child: const Row(
              children: [
                Expanded(
                  child: Text(
                    'Record TOS',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      letterSpacing: -.4,
                    ),
                  ),
                ),
                Text(
                  'GENERALI',
                  style: TextStyle(
                    color: Color(0xffc0cedd),
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1.3,
                  ),
                ),
              ],
            ),
          ),
          FutureBuilder<dynamic>(
            future: data,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Impossibile calcolare i record.'),
                      TextButton(
                        onPressed: reload,
                        child: const Text('Riprova'),
                      ),
                    ],
                  ),
                );
              }
              if (!snapshot.hasData) {
                return const Padding(
                  padding: EdgeInsets.all(14),
                  child: LinearProgressIndicator(),
                );
              }
              final result = Map<String, dynamic>.from(snapshot.data as Map);
              final groups = Map<String, dynamic>.from(result['groups'] as Map);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (groups.isEmpty)
                    Padding(
                      padding: const EdgeInsets.all(14),
                      child: Text(
                        'Nessuna partita conclusa con risultato disponibile.',
                        style: TextStyle(
                          color: colors.onSurfaceVariant,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  for (final group in groups.values)
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final compact = constraints.maxWidth < 560;
                        return Table(
                          border: TableBorder(
                            horizontalInside: BorderSide(
                              color: colors.outlineVariant.withValues(
                                alpha: .6,
                              ),
                            ),
                          ),
                          columnWidths: compact
                              ? const {
                                  0: FlexColumnWidth(),
                                  1: FixedColumnWidth(58),
                                }
                              : const {
                                  0: FlexColumnWidth(3),
                                  1: FixedColumnWidth(74),
                                  2: FlexColumnWidth(5),
                                },
                          defaultVerticalAlignment:
                              TableCellVerticalAlignment.top,
                          children: [
                            if (!compact)
                              TableRow(
                                decoration: BoxDecoration(
                                  color: colors.surfaceContainerLow,
                                ),
                                children: [
                                  columnHeading('Record'),
                                  columnHeading('Valore'),
                                  columnHeading('Detentori e partite'),
                                ],
                              ),
                            for (final entry
                                in (group['records'] as Map).entries)
                              recordRow(
                                entry.key.toString(),
                                entry.value as Map,
                                compact,
                              ),
                          ],
                        );
                      },
                    ),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      border: Border(
                        top: BorderSide(
                          color: colors.outlineVariant.withValues(alpha: .6),
                        ),
                      ),
                    ),
                    child: ExpansionTile(
                      dense: true,
                      minTileHeight: 32,
                      tilePadding: const EdgeInsets.symmetric(horizontal: 14),
                      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
                      shape: const Border(),
                      collapsedShape: const Border(),
                      title: Text(
                        'Come vengono calcolati',
                        style: TextStyle(
                          color: colors.onSurfaceVariant,
                          fontSize: 10.5,
                        ),
                      ),
                      trailing: Icon(
                        Icons.expand_more,
                        size: 15,
                        color: colors.onSurfaceVariant,
                      ),
                      children: [
                        Text(
                          '${result['coverage']}',
                          style: TextStyle(
                            color: colors.onSurfaceVariant,
                            fontSize: 11,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    ),
  );

  Widget columnHeading(String text) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
    child: Text(
      text.toUpperCase(),
      style: TextStyle(
        color: colors.onSurfaceVariant,
        fontSize: 9,
        letterSpacing: 1,
        fontWeight: FontWeight.w600,
      ),
    ),
  );
  Widget cell(Widget child) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    child: child,
  );
  TableRow recordRow(String key, Map record, bool compact) {
    final holders = record['holders'] as List;
    final visible = expanded.contains(key) ? holders : holders.take(1);
    final label = Text(
      '${record['label']}',
      style: TextStyle(
        color: colors.onSurfaceVariant,
        fontSize: compact ? 11.5 : 13,
        fontWeight: compact ? FontWeight.w500 : FontWeight.w600,
        height: 1.4,
      ),
    );
    final value = Text(
      '${record['value'] ?? '—'}',
      style: TextStyle(
        color: record['value'] == null
            ? colors.onSurfaceVariant.withValues(alpha: .6)
            : colors.onSurface,
        fontSize: record['value'] == null ? 20 : 27,
        fontWeight: record['value'] == null ? FontWeight.w400 : FontWeight.w800,
        letterSpacing: -1,
        height: 1.1,
      ),
    );
    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (holders.isEmpty)
          Text(
            'Dati insufficienti',
            style: TextStyle(
              color: colors.onSurfaceVariant,
              fontSize: 11,
              height: 1.5,
            ),
          ),
        for (final entry in visible.toList().asMap().entries)
          Padding(
            padding: EdgeInsets.only(top: entry.key == 0 ? 0 : 8),
            child: holder(entry.value as Map),
          ),
        if (holders.length > 1)
          TextButton.icon(
            style: subjectStyle.copyWith(
              foregroundColor: WidgetStatePropertyAll(colors.onSurfaceVariant),
            ),
            onPressed: () => setState(() {
              if (!expanded.add(key)) expanded.remove(key);
            }),
            icon: Icon(
              expanded.contains(key)
                  ? Icons.keyboard_arrow_up
                  : Icons.keyboard_arrow_down,
              size: 14,
            ),
            label: Text(
              expanded.contains(key)
                  ? 'Mostra meno'
                  : 'Altri detentori · ${holders.length - 1}',
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
      ],
    );
    return TableRow(
      children: compact
          ? [
              cell(
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [label, const SizedBox(height: 6), details],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(0, 12, 14, 12),
                child: Align(alignment: Alignment.topRight, child: value),
              ),
            ]
          : [
              cell(label),
              cell(Align(alignment: Alignment.topCenter, child: value)),
              cell(details),
            ],
    );
  }

  Widget holder(Map h) {
    final p = h['match'] as Map;
    final showSubject =
        h['player_id'] != null ||
        ![
          '${p['squadra_casa']} / ${p['squadra_ospite']}',
          'Pareggio',
        ].contains(h['subject']);
    final profileAvailable =
        h['player_id'] != null ||
        [p['squadra_casa'], p['squadra_ospite']].contains(h['subject']);
    final subject = Text(
      '${h['subject']}',
      style: TextStyle(
        color: colors.onSurface,
        fontSize: 12.5,
        fontWeight: FontWeight.w700,
        height: 1.4,
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (showSubject) ...[
          if (profileAvailable)
            TextButton(
              style: subjectStyle,
              onPressed: () => openSubject(h),
              child: subject,
            )
          else
            subject,
          const SizedBox(height: 2),
        ],
        InkWell(
          borderRadius: BorderRadius.circular(4),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => MatchPage(api: widget.api, id: '${p['id']}'),
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${p['squadra_casa']}',
                    style: TextStyle(
                      color: colors.onSurface,
                      fontSize: 11.5,
                      height: 1.4,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text(
                    '${p['gol_casa']}–${p['gol_ospite']}',
                    style: TextStyle(
                      color: colors.onSurface,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Expanded(
                  child: Text(
                    '${p['squadra_ospite']}',
                    textAlign: TextAlign.right,
                    style: TextStyle(
                      color: colors.onSurface,
                      fontSize: 11.5,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 3),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                '${p['torneo_nome'] ?? p['torneo']}',
                style: TextStyle(
                  color: colors.onSurfaceVariant,
                  fontSize: 10,
                  height: 1.5,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              displayDate('${p['data_partita']}'),
              style: TextStyle(
                color: colors.onSurfaceVariant,
                fontSize: 10,
                height: 1.5,
              ),
            ),
          ],
        ),
      ],
    );
  }

  void openSubject(Map h) {
    final p = h['match'] as Map;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => h['player_id'] != null
            ? PlayerPage(
                api: widget.api,
                player: {
                  'id': h['player_id'],
                  'nome': h['subject'],
                  'cognome': '',
                },
              )
            : TeamPage(
                api: widget.api,
                slug: '${p['torneo']}',
                team: {'nome': h['subject']},
              ),
      ),
    );
  }

  String displayDate(String value) {
    final date = DateTime.tryParse(value);
    return date == null
        ? value
        : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}
