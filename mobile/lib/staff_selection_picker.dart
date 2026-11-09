import 'package:flutter/material.dart';

class StaffSelectionPicker extends StatelessWidget {
  const StaffSelectionPicker({
    super.key,
    required this.model,
    required this.onChanged,
  });
  final Map model;
  final ValueChanged<List<String>> onChanged;
  Future<void> _open(BuildContext context) async {
    final options = (model['options'] as List? ?? []).whereType<Map>().toList();
    final selected = options
        .where((row) => row['checked'] == true)
        .map((row) => '${row['id']}')
        .toSet();
    var query = '';
    final result = await showModalBottomSheet<List<String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => SizedBox(
          height: MediaQuery.sizeOf(context).height * .92,
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              16,
              16,
              16 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (MediaQuery.viewInsetsOf(context).bottom == 0)
                  Text(
                    '${model['title']}',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                const SizedBox(height: 12),
                TextField(
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    labelText: 'Cerca',
                  ),
                  onChanged: (value) =>
                      setModalState(() => query = value.toLowerCase()),
                ),
                if (MediaQuery.viewInsetsOf(context).bottom == 0)
                  Wrap(
                    spacing: 8,
                    children: [
                      TextButton(
                        onPressed: () => setModalState(
                          () => selected.addAll(
                            options
                                .where((row) => row['disabled'] != true)
                                .map((row) => '${row['id']}'),
                          ),
                        ),
                        child: const Text('Seleziona tutte'),
                      ),
                      TextButton(
                        onPressed: () => setModalState(() => selected.clear()),
                        child: const Text('Deseleziona tutte'),
                      ),
                    ],
                  ),
                Text('${selected.length} selezionate'),
                if ('${model['hint'] ?? ''}'.isNotEmpty &&
                    MediaQuery.viewInsetsOf(context).bottom == 0)
                  Text(
                    '${model['hint']}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                const SizedBox(height: 8),
                Expanded(
                  child: ListView(
                    children: [
                      for (final row in options.where(
                        (row) =>
                            '${row['label']}'.toLowerCase().contains(query),
                      ))
                        SwitchListTile.adaptive(
                          title: Text('${row['label']}'),
                          value: selected.contains('${row['id']}'),
                          onChanged: row['disabled'] == true
                              ? null
                              : (value) => setModalState(
                                  () => value
                                      ? selected.add('${row['id']}')
                                      : selected.remove('${row['id']}'),
                                ),
                        ),
                    ],
                  ),
                ),
                FilledButton(
                  onPressed: () => Navigator.pop(context, selected.toList()),
                  child: const Text('Conferma selezione'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (result != null) onChanged(result);
  }

  @override
  Widget build(BuildContext context) {
    final options = (model['options'] as List? ?? []).whereType<Map>().toList();
    final count = options.where((row) => row['checked'] == true).length;
    return Card(
      child: ListTile(
        title: Text('${model['title']}'),
        subtitle: Text(
          count == 0
              ? '${model['emptyLabel'] ?? 'Nessuna selezione'}'
              : '$count di ${options.length} selezionate',
        ),
        leading: const Icon(Icons.checklist_rounded),
        trailing: const Icon(Icons.expand_more),
        onTap: () => _open(context),
      ),
    );
  }
}
