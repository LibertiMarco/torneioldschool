import 'package:flutter/material.dart';

import 'api.dart';
import 'details.dart' show MatchPage, PlayerPage, TeamPage;

/// Tabella nella classifica generale: nessuna pagina o tab dedicati.
class RecordsTable extends StatefulWidget {
  const RecordsTable({super.key, required this.api});
  final TosApi api;
  @override
  State<RecordsTable> createState() => _RecordsTableState();
}

class _RecordsTableState extends State<RecordsTable> {
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
      decoration: InputDecoration(labelText: label),
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
    padding: const EdgeInsets.symmetric(vertical: 24),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Record TOS', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
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
                filter(
                  'Torneo',
                  result['tournaments'] as List,
                  tournament,
                  (v) => tournament = v,
                ),
                filter(
                  'Formato',
                  result['formats'] as List,
                  format,
                  (v) => format = v,
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text('${result['coverage']}'),
                ),
                if (groups.isEmpty)
                  const Text(
                    'Nessuna partita conclusa con risultato e formato disponibili per questi filtri.',
                  ),
                for (final entry in groups.entries) ...[
                  Text(
                    entry.key,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const Text(
                    'Scorri la tabella orizzontalmente per tutti i dettagli.',
                  ),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: SizedBox(
                      width: 700,
                      child: Table(
                        border: TableBorder.all(
                          color: Theme.of(context).colorScheme.outlineVariant,
                        ),
                        columnWidths: const {
                          0: FixedColumnWidth(210),
                          1: FixedColumnWidth(65),
                          2: FlexColumnWidth(),
                        },
                        defaultVerticalAlignment:
                            TableCellVerticalAlignment.top,
                        children: [
                          TableRow(
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .surfaceContainerHighest,
                            ),
                            children: [
                              cell(const Text('Record')),
                              cell(const Text('Valore')),
                              cell(const Text('Detentori e partite')),
                            ],
                          ),
                          for (final recordEntry
                              in (entry.value['records'] as Map).entries)
                            row(
                              '${entry.key}-${recordEntry.key}',
                              recordEntry.value as Map,
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ],
            );
          },
        ),
      ],
    ),
  );
  Widget cell(Widget child) =>
      Padding(padding: const EdgeInsets.all(12), child: child);
  TableRow row(String key, Map record) {
    final holders = record['holders'] as List;
    final visible = expanded.contains(key) ? holders : holders.take(3);
    return TableRow(
      children: [
        cell(Text('${record['label']}')),
        cell(
          Text(
            '${record['value'] ?? '—'}',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        cell(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (holders.isEmpty) const Text('Dati insufficienti'),
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
          ),
        ),
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
              child: Text('${h['subject']}'),
            )
          else if ([
            p['squadra_casa'],
            p['squadra_ospite'],
          ].contains(h['subject']))
            TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => TeamPage(
                    api: widget.api,
                    slug: '${p['torneo']}',
                    team: {'nome': h['subject']},
                  ),
                ),
              ),
              child: Text('${h['subject']}'),
            )
          else
            Text(
              '${h['subject']}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          TextButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => MatchPage(api: widget.api, id: '${p['id']}'),
              ),
            ),
            child: Text(
              '${p['squadra_casa']} ${p['gol_casa']}–${p['gol_ospite']} ${p['squadra_ospite']}',
            ),
          ),
          Text('${p['torneo_nome'] ?? p['torneo']} · ${p['data_partita']}'),
        ],
      ),
    );
  }
}
