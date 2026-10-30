import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;

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
    alignment: Alignment.center,
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
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            decoration: BoxDecoration(
              border: Border(
                bottom: BorderSide(
                  color: colors.outlineVariant.withValues(alpha: .6),
                ),
              ),
            ),
            child: Text(
              'Record TOS',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: colors.onSurface,
                fontSize: 18,
                fontWeight: FontWeight.w700,
                letterSpacing: -.4,
              ),
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
                    Table(
                      border: TableBorder(
                        horizontalInside: BorderSide(
                          color: colors.outlineVariant.withValues(alpha: .6),
                        ),
                      ),
                      columnWidths: const {0: FlexColumnWidth()},
                      children: [
                        for (final entry in (group['records'] as Map).entries)
                          recordRow(entry.key.toString(), entry.value as Map),
                      ],
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

  TableRow recordRow(String key, Map record) {
    final holders = record['holders'] as List;
    final visible = expanded.contains(key) ? holders : holders.take(1);
    return TableRow(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text(
                '${record['label']}',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: colors.onSurfaceVariant,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 6),
              if (holders.isEmpty)
                Text(
                  'Dati insufficienti',
                  style: TextStyle(
                    color: colors.onSurfaceVariant,
                    fontSize: 11,
                  ),
                ),
              for (final entry in visible.toList().asMap().entries)
                Padding(
                  padding: EdgeInsets.only(top: entry.key == 0 ? 0 : 10),
                  child: holder(entry.value as Map, record['value'], key),
                ),
              if (holders.length > 1)
                TextButton.icon(
                  style: subjectStyle.copyWith(
                    foregroundColor: WidgetStatePropertyAll(
                      colors.onSurfaceVariant,
                    ),
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
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget holder(Map h, dynamic value, String recordKey) {
    final p = h['match'] as Map;
    final individual = h['player_id'] != null;
    final type = individual ? 11.0 : 14.0;
    final shade = individual ? colors.onSurfaceVariant : colors.onSurface;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if (individual)
          Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 12,
            runSpacing: 2,
            children: [
              TextButton(
                style: subjectStyle,
                onPressed: () => openSubject(h),
                child: Text(
                  '${h['subject']}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(
                '$value gol',
                style: TextStyle(
                  color: colors.onSurface,
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        Tooltip(
          message:
              '${p['torneo_nome'] ?? p['torneo']} · ${displayDate('${p['data_partita']}')}',
          child: InkWell(
            borderRadius: BorderRadius.circular(4),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => MatchPage(api: widget.api, id: '${p['id']}'),
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Wrap(
                alignment: WrapAlignment.center,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 10,
                runSpacing: 3,
                children: [
                  Text(
                    '${p['squadra_casa']}',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: shade,
                      fontSize: type,
                      fontWeight: individual
                          ? FontWeight.w400
                          : FontWeight.w500,
                    ),
                  ),
                  Text(
                    '${p['gol_casa']}–${p['gol_ospite']}',
                    style: TextStyle(
                      color: shade,
                      fontSize: individual ? 12 : 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    '${p['squadra_ospite']}',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: shade,
                      fontSize: type,
                      fontWeight: individual
                          ? FontWeight.w400
                          : FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (recordKey == 'scorers')
          Text(
            '$value marcatori diversi',
            style: TextStyle(color: colors.onSurfaceVariant, fontSize: 11),
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
