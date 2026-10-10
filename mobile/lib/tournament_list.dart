import 'package:flutter/material.dart';

import 'api.dart';
import 'main.dart' show ErrorPanel, TournamentPage;
import 'home_dashboard.dart' show SiteImage;

const tournamentStates = {
  'in corso': 'In corso',
  'programmato': 'Tornei programmati',
  'terminato': 'Tornei terminati',
};

String normalizedSearch(String value) {
  var result = value.trim().toLowerCase();
  const accents = {
    'à': 'a',
    'á': 'a',
    'â': 'a',
    'ä': 'a',
    'è': 'e',
    'é': 'e',
    'ê': 'e',
    'ë': 'e',
    'ì': 'i',
    'í': 'i',
    'î': 'i',
    'ï': 'i',
    'ò': 'o',
    'ó': 'o',
    'ô': 'o',
    'ö': 'o',
    'ù': 'u',
    'ú': 'u',
    'û': 'u',
    'ü': 'u',
    'ñ': 'n',
    'ç': 'c',
  };
  for (final entry in accents.entries) {
    result = result.replaceAll(entry.key, entry.value);
  }
  return result.replaceAll(RegExp(r'[\u0300-\u036f]'), '');
}

DateTime? tournamentDate(dynamic value) {
  final raw = (value ?? '').toString();
  if (raw.length < 10 || raw.startsWith('0000')) return null;
  final parsed = DateTime.tryParse(raw);
  // Dart normalizes overflowing dates; the site treats invalid dates as missing.
  if (parsed == null ||
      parsed.toIso8601String().substring(0, 10) != raw.substring(0, 10)) {
    return null;
  }
  return parsed;
}

int naturalCategoryOrder(String left, String right) {
  final parts = RegExp(r'\d+|\D+');
  final a = parts
      .allMatches(left.toLowerCase())
      .map((m) => m.group(0)!)
      .toList();
  final b = parts
      .allMatches(right.toLowerCase())
      .map((m) => m.group(0)!)
      .toList();
  for (var i = 0; i < a.length && i < b.length; i++) {
    final numberA = int.tryParse(a[i]);
    final numberB = int.tryParse(b[i]);
    final diff = numberA != null && numberB != null
        ? numberA.compareTo(numberB)
        : a[i].compareTo(b[i]);
    if (diff != 0) return diff;
  }
  return a.length.compareTo(b.length);
}

String tournamentPeriod(Map<String, dynamic> row) {
  String label(dynamic value) {
    final date = tournamentDate(value);
    return date == null
        ? ''
        : '${date.month.toString().padLeft(2, '0')}/${(date.year % 100).toString().padLeft(2, '0')}';
  }

  return [
    label(row['data_inizio']),
    label(row['data_fine']),
  ].where((v) => v.isNotEmpty).join(' - ');
}

List<Map<String, dynamic>> tournamentGroup(
  List<Map<String, dynamic>> rows,
  String state, {
  String search = '',
  String category = '',
}) {
  final filtered = rows.where((row) {
    if ('${row['stato']}'.trim().toLowerCase() != state) return false;
    if (category.isNotEmpty &&
        normalizedSearch('${row['categoria'] ?? ''}') !=
            normalizedSearch(category)) {
      return false;
    }
    final text =
        '${row['nome']} ${row['categoria']} ${tournamentPeriod(row)} $state ${tournamentStates[state]}';
    return normalizedSearch(text).contains(normalizedSearch(search));
  }).toList();
  int date(Map<String, dynamic> row, String key, int fallback) =>
      tournamentDate(row[key])?.millisecondsSinceEpoch ?? fallback;
  const last = 8640000000000000;
  filtered.sort((a, b) {
    if (state == 'programmato') {
      return date(
        a,
        'data_inizio',
        last,
      ).compareTo(date(b, 'data_inizio', last));
    }
    if (state == 'terminato') {
      return date(b, 'data_fine', 0).compareTo(date(a, 'data_fine', 0));
    }
    final end = date(
      a,
      'data_fine',
      last,
    ).compareTo(date(b, 'data_fine', last));
    return end != 0
        ? end
        : date(b, 'data_inizio', 0).compareTo(date(a, 'data_inizio', 0));
  });
  return filtered;
}

class TournamentList extends StatefulWidget {
  const TournamentList({super.key, required this.api, required this.section});
  final TosApi api;
  final String section;
  @override
  State<TournamentList> createState() => _TournamentListState();
}

class _TournamentListState extends State<TournamentList> {
  late Future<List<Map<String, dynamic>>> data;
  String state = 'in corso', search = '', category = '';
  int archiveLimit = 12;
  @override
  void initState() {
    super.initState();
    data = widget.api.tournaments(widget.section);
  }

  Future<void> reload() async {
    final next = widget.api.tournaments(widget.section);
    setState(() => data = next);
    try {
      await next;
    } catch (_) {
      /* FutureBuilder displays the error. */
    }
  }

  @override
  Widget build(
    BuildContext context,
  ) => FutureBuilder<List<Map<String, dynamic>>>(
    future: data,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Center(child: CircularProgressIndicator());
      }
      if (snapshot.hasError) {
        return ErrorPanel(error: snapshot.error!, retry: reload);
      }
      final rows = snapshot.data ?? [];
      final categories =
          rows
              .map((r) => '${r['categoria'] ?? ''}'.trim())
              .where((v) => v.isNotEmpty)
              .toSet()
              .toList()
            ..sort(naturalCategoryOrder);
      final filtered = tournamentGroup(
        rows,
        state,
        search: search,
        category: category,
      );
      final limit =
          state == 'terminato' && search.trim().isEmpty && category.isEmpty
          ? archiveLimit
          : filtered.length;
      return Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              decoration: const InputDecoration(
                labelText: 'Cerca torneo',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (value) => setState(() {
                search = value;
                archiveLimit = 12;
              }),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: DropdownButtonFormField<String>(
              initialValue: category,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Categoria'),
              items: [
                const DropdownMenuItem(
                  value: '',
                  child: Text('Tutte le categorie'),
                ),
                ...categories.map(
                  (c) => DropdownMenuItem(value: c, child: Text(c)),
                ),
              ],
              onChanged: (value) => setState(() {
                category = value ?? '';
                archiveLimit = 12;
              }),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: tournamentStates.entries
                  .map(
                    (entry) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(
                          '${entry.value} (${tournamentGroup(rows, entry.key, search: search, category: category).length})',
                        ),
                        selected: state == entry.key,
                        onSelected: (_) => setState(() {
                          state = entry.key;
                          archiveLimit = 12;
                        }),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text(
                        'Nessun torneo disponibile con questi filtri.',
                      ),
                    ),
                  ...filtered
                      .take(limit)
                      .map(
                        (row) => Card(
                          child: ListTile(
                            contentPadding: const EdgeInsets.all(12),
                            leading: SiteImage(
                              api: widget.api,
                              path: '${row['img'] ?? ''}',
                              width: 52,
                              height: 52,
                              fallback: Icons.emoji_events_outlined,
                            ),
                            title: Text(
                              '${row['nome']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            subtitle: Text(
                              [
                                '${row['categoria'] ?? ''}',
                                tournamentPeriod(row),
                              ].where((s) => s.isNotEmpty).join(' · '),
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => TournamentPage(
                                  api: widget.api,
                                  tournament: row,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                  if (filtered.length > limit)
                    OutlinedButton(
                      onPressed: () => setState(() => archiveLimit += 12),
                      child: const Text('Carica altri tornei'),
                    ),
                ],
              ),
            ),
          ),
        ],
      );
    },
  );
}
