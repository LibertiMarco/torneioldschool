import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;
import 'theme.dart' show siteBlue;

/// Tabella nella classifica generale: nessuna pagina o tab dedicati.
class RecordsTable extends StatefulWidget {
  const RecordsTable({super.key, required this.api});
  final TosApi api;
  @override
  State<RecordsTable> createState() => _RecordsTableState();
}

class _RecordsTableState extends State<RecordsTable> {
  ColorScheme get colors => Theme.of(context).colorScheme;
  ButtonStyle get linkStyle => TextButton.styleFrom(
    padding: const EdgeInsets.symmetric(vertical: 2),
    minimumSize: const Size(0, 26),
    alignment: Alignment.centerLeft,
    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    foregroundColor: colors.onSurface,
  );

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

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 16, bottom: 8),
          child: Text(
            'Record TOS',
            style: TextStyle(
              color: colors.onSurface,
              fontSize: 20,
              fontWeight: FontWeight.w800,
              letterSpacing: -.4,
            ),
          ),
        ),
        FutureBuilder<dynamic>(
          future: data,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return Column(
                children: [
                  const Text('Impossibile calcolare i record.'),
                  TextButton(onPressed: reload, child: const Text('Riprova')),
                ],
              );
            }
            if (!snapshot.hasData) {
              return const LinearProgressIndicator();
            }
            final result = Map<String, dynamic>.from(snapshot.data as Map),
                groups = Map<String, dynamic>.from(result['groups'] as Map);
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (groups.isEmpty)
                  const Text(
                    'Nessuna partita conclusa con risultato disponibile.',
                  ),
                for (final entry in groups.entries) ...[
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final compact = constraints.maxWidth < 560;
                      return Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: colors.outlineVariant),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: Table(
                          border: TableBorder(
                            horizontalInside: BorderSide(
                              color: colors.outlineVariant,
                            ),
                          ),
                          columnWidths: compact
                              ? const {0: FlexColumnWidth()}
                              : const {
                                  0: FlexColumnWidth(3),
                                  1: FixedColumnWidth(80),
                                  2: FlexColumnWidth(5),
                                },
                          defaultVerticalAlignment:
                              TableCellVerticalAlignment.top,
                          children: [
                            TableRow(
                              decoration: BoxDecoration(color: siteBlue),
                              children: [
                                header(compact ? 'Record e partite' : 'Record'),
                                if (!compact) header('Valore'),
                                if (!compact) header('Detentori e partite'),
                              ],
                            ),
                            for (final recordEntry
                                in (entry.value['records'] as Map).entries)
                              row(
                                '${entry.key}-${recordEntry.key}',
                                recordEntry.value as Map,
                                compact,
                                (entry.value['records'] as Map).keys
                                    .toList()
                                    .indexOf(recordEntry.key),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 8),
                ],
                ExpansionTile(
                  dense: true,
                  minTileHeight: 36,
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: const EdgeInsets.only(bottom: 12),
                  shape: const Border(),
                  collapsedShape: const Border(),
                  leading: Icon(
                    Icons.info_outline,
                    size: 18,
                    color: colors.onSurfaceVariant,
                  ),
                  title: Text(
                    'Come vengono calcolati',
                    style: TextStyle(
                      fontSize: 12,
                      color: colors.onSurfaceVariant,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  children: [
                    Text(
                      '${result['coverage']}',
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 11,
                        height: 1.6,
                      ),
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ],
    ),
  );
  Widget cell(Widget child) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
    child: child,
  );
  Widget header(String label) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    child: Text(
      label.toUpperCase(),
      style: const TextStyle(
        color: Colors.white,
        fontSize: 10,
        fontWeight: FontWeight.w700,
        letterSpacing: .6,
      ),
    ),
  );
  Widget valueBadge(dynamic value) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: value == null ? colors.surfaceContainerHigh : colors.primary,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      '${value ?? '—'}',
      style: TextStyle(
        color: value == null ? colors.onSurfaceVariant : colors.onPrimary,
        fontSize: 18,
        fontWeight: FontWeight.w900,
        height: 1.1,
      ),
    ),
  );
  TableRow row(String key, Map record, bool compact, int index) {
    final holders = record['holders'] as List;
    final visible = expanded.contains(key) ? holders : holders.take(1);
    final label = Text(
      '${record['label']}',
      style: TextStyle(
        color: colors.onSurface,
        fontWeight: FontWeight.w700,
        fontSize: 13,
        height: 1.5,
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
              fontSize: 12,
              height: 1.5,
            ),
          ),
        for (final h in visible) holder(h as Map),
        if (holders.length > 1)
          TextButton(
            onPressed: () => setState(() {
              if (!expanded.add(key)) expanded.remove(key);
            }),
            child: Text(
              expanded.contains(key)
                  ? 'Mostra meno'
                  : 'Mostra altri ${holders.length - 1} pari merito',
            ),
          ),
      ],
    );
    return TableRow(
      decoration: BoxDecoration(
        color: index.isEven ? colors.surfaceContainerLow : colors.surface,
      ),
      children: [
        if (compact)
          cell(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: label),
                    const SizedBox(width: 14),
                    valueBadge(record['value']),
                  ],
                ),
                const SizedBox(height: 6),
                details,
              ],
            ),
          )
        else ...[
          cell(label),
          cell(
            Align(
              alignment: Alignment.topLeft,
              child: valueBadge(record['value']),
            ),
          ),
          cell(details),
        ],
      ],
    );
  }

  Widget holder(Map h) {
    final p = h['match'] as Map;
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (h['player_id'] != null)
            TextButton(
              style: linkStyle,
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => PlayerPage(
                    api: widget.api,
                    player: {
                      'id': h['player_id'],
                      'nome': h['subject'],
                      'cognome': '',
                    },
                  ),
                ),
              ),
              child: Text(
                '${h['subject']}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  height: 1.5,
                ),
              ),
            )
          else if ([
            p['squadra_casa'],
            p['squadra_ospite'],
          ].contains(h['subject']))
            TextButton(
              style: linkStyle,
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => TeamPage(
                    api: widget.api,
                    slug: '${p['torneo']}',
                    team: {'nome': h['subject']},
                  ),
                ),
              ),
              child: Text(
                '${h['subject']}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  height: 1.5,
                ),
              ),
            )
          else
            Text(
              '${h['subject']}',
              style: TextStyle(
                color: colors.onSurface,
                fontSize: 12,
                fontWeight: FontWeight.w800,
                height: 1.5,
              ),
            ),
          TextButton(
            style: linkStyle,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => MatchPage(api: widget.api, id: '${p['id']}'),
              ),
            ),
            child: Wrap(
              spacing: 5,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  '${p['squadra_casa']}',
                  style: const TextStyle(fontSize: 11, height: 1.5),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: colors.surfaceContainerHigh,
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Text(
                    '${p['gol_casa']}–${p['gol_ospite']}',
                    style: TextStyle(
                      color: colors.onSurface,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Text(
                  '${p['squadra_ospite']}',
                  style: const TextStyle(fontSize: 11, height: 1.5),
                ),
              ],
            ),
          ),
          Text(
            '${p['torneo_nome'] ?? p['torneo']} · ${displayDate('${p['data_partita']}')}',
            style: TextStyle(
              color: colors.onSurfaceVariant,
              fontSize: 10,
              height: 1.6,
            ),
          ),
        ],
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
