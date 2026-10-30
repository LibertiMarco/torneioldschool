import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;
import 'home_design.dart' show HomeSectionTitle;
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
    padding: const EdgeInsets.symmetric(vertical: 4),
    minimumSize: const Size(0, 32),
    alignment: Alignment.centerLeft,
    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    foregroundColor: colors.onSurface,
  );
  String tournament = '', format = '';
  final expanded = <String>{};
  late Future<dynamic> data = load();
  Future<dynamic> load() => widget.api.request(
    'api/records.php',
    query: {'scope': 'records', 'torneo': tournament, 'formato': format},
  );
  void reload() {
    setState(() {
      data = load();
      expanded.clear();
    });
  }

  Widget filter(
    String label,
    List values,
    String value,
    ValueChanged<String> change,
  ) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: DropdownButtonFormField<String>(
      key: ValueKey('$label-$value'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(
          label == 'Torneo'
              ? Icons.emoji_events_outlined
              : Icons.sports_soccer_outlined,
          size: 19,
        ),
      ),
      items: [
        DropdownMenuItem(
          value: '',
          child: Text(label == 'Torneo' ? 'Storico TOS' : 'Tutti (separati)'),
        ),
        ...values.map(
          (v) => DropdownMenuItem(
            value: '$v',
            child: Text('$v', overflow: TextOverflow.ellipsis),
          ),
        ),
      ],
      onChanged: (v) {
        change(v ?? '');
        reload();
      },
    ),
  );
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 24),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const HomeSectionTitle(
          title: 'Record TOS',
          eyebrow: 'LE MIGLIORI PRESTAZIONI',
        ),
        Text(
          'I risultati e i protagonisti che hanno lasciato il segno.',
          style: TextStyle(
            color: colors.onSurfaceVariant,
            fontSize: 12,
            height: 1.6,
          ),
        ),
        const SizedBox(height: 16),
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
                LayoutBuilder(
                  builder: (context, constraints) {
                    final tournamentFilter = filter(
                      'Torneo',
                      result['tournaments'] as List,
                      tournament,
                      (v) => tournament = v,
                    );
                    final formatFilter = filter(
                      'Formato',
                      result['formats'] as List,
                      format,
                      (v) => format = v,
                    );
                    return constraints.maxWidth < 560
                        ? Column(children: [tournamentFilter, formatFilter])
                        : Row(
                            children: [
                              Expanded(flex: 2, child: tournamentFilter),
                              const SizedBox(width: 14),
                              Expanded(child: formatFilter),
                            ],
                          );
                  },
                ),
                if (groups.isEmpty)
                  const Text(
                    'Nessuna partita conclusa con risultato e formato disponibili per questi filtri.',
                  ),
                for (final entry in groups.entries) ...[
                  Container(
                    margin: const EdgeInsets.only(top: 18, bottom: 10),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 9,
                    ),
                    decoration: BoxDecoration(
                      color: colors.surfaceContainerHigh,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.sports_soccer_outlined,
                          size: 15,
                          color: colors.primary,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          entry.key,
                          style: TextStyle(
                            color: colors.onSurface,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
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
                  const SizedBox(height: 16),
                ],
                ExpansionTile(
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
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
    child: child,
  );
  Widget header(String label) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
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
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: value == null ? colors.surfaceContainerHigh : colors.primary,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      '${value ?? '—'}',
      style: TextStyle(
        color: value == null ? colors.onSurfaceVariant : colors.onPrimary,
        fontSize: 22,
        fontWeight: FontWeight.w900,
        height: 1.1,
      ),
    ),
  );
  TableRow row(String key, Map record, bool compact, int index) {
    final holders = record['holders'] as List;
    final visible = expanded.contains(key) ? holders : holders.take(3);
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
        if (holders.length > 3)
          TextButton(
            onPressed: () => setState(() {
              if (!expanded.add(key)) expanded.remove(key);
            }),
            child: Text(
              expanded.contains(key)
                  ? 'Mostra meno'
                  : 'Mostra altri ${holders.length - 3} pari merito',
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
                const SizedBox(height: 12),
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
      padding: const EdgeInsets.only(bottom: 14),
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
                    color: colors.secondaryContainer,
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Text(
                    '${p['gol_casa']}–${p['gol_ospite']}',
                    style: TextStyle(
                      color: colors.onSecondaryContainer,
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
            '${p['torneo_nome'] ?? p['torneo']}\n${displayDate('${p['data_partita']}')}',
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
