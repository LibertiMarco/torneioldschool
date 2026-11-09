import 'package:flutter/material.dart';

class StaffPlayerList extends StatefulWidget {
  const StaffPlayerList({
    super.key,
    required this.model,
    required this.onChanged,
    required this.onAction,
  });
  final Map model;
  final void Function(Map field, Map<String, dynamic> value) onChanged;
  final void Function(Map action) onAction;
  @override
  State<StaffPlayerList> createState() => _StaffPlayerListState();
}

class _StaffPlayerListState extends State<StaffPlayerList> {
  final _scroll = ScrollController();
  final _search = TextEditingController();
  String _query = '';

  @override
  void didUpdateWidget(StaffPlayerList oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.model['context'] != widget.model['context']) {
      _query = '';
      _search.clear();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _scroll.hasClients) _scroll.jumpTo(0);
      });
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final removal = widget.model['removal'] == true;
    final rows = (widget.model['rows'] as List? ?? [])
        .whereType<Map>()
        .toList();
    final filtered = rows.where((row) {
      final fieldName = (row['field'] as Map?)?['label'];
      return '${removal ? fieldName : row['name']}'.toLowerCase().contains(
        _query,
      );
    }).toList();
    final selected = rows
        .where((row) => (row['field'] as Map?)?['checked'] == true)
        .length;
    final height = (MediaQuery.textScalerOf(context).scale(64) + 28).clamp(
      88.0,
      180.0,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (removal && rows.isNotEmpty) ...[
          TextField(
            controller: _search,
            decoration: const InputDecoration(
              labelText: 'Cerca giocatore',
              prefixIcon: Icon(Icons.search),
            ),
            onChanged: (value) => setState(() {
              _query = value.trim().toLowerCase();
              if (_scroll.hasClients) _scroll.jumpTo(0);
            }),
          ),
          const SizedBox(height: 8),
          Text(
            '$selected di ${rows.length} selezionati',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
        ],
        if (filtered.isEmpty)
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              rows.isEmpty
                  ? '${widget.model['empty'] ?? ''}'
                  : 'Nessun giocatore trovato',
              textAlign: TextAlign.center,
            ),
          )
        else
          SizedBox(
            height: height * (filtered.length < 4 ? filtered.length : 3.6),
            child: Scrollbar(
              controller: _scroll,
              thumbVisibility: filtered.length >= 4,
              child: ListView.builder(
                controller: _scroll,
                primary: false,
                itemExtent: height,
                itemCount: filtered.length,
                itemBuilder: (context, index) {
                  final row = filtered[index];
                  final field = row['field'] as Map?;
                  final action = row['action'] as Map?;
                  final selectedPlayer = field?['checked'] == true;
                  final added = action?['disabled'] == true;
                  final checked = removal ? selectedPlayer : added;
                  return Padding(
                    padding: const EdgeInsets.symmetric(
                      vertical: 3,
                      horizontal: 2,
                    ),
                    child: Material(
                      color: checked
                          ? colors.primaryContainer
                          : colors.surfaceContainerLow,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                        side: BorderSide(
                          color: checked
                              ? colors.primary
                              : colors.outlineVariant,
                        ),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: removal && field != null
                          ? CheckboxListTile(
                              value: checked,
                              title: Text(
                                '${field['label']}',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                              onChanged: field['disabled'] == true
                                  ? null
                                  : (value) {
                                      setState(
                                        () => field['checked'] = value == true,
                                      );
                                      widget.onChanged(field, {
                                        'checked': value == true,
                                      });
                                    },
                            )
                          : InkWell(
                              onTap: action == null || checked
                                  ? null
                                  : () => widget.onAction(action),
                              child: Padding(
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            '${row['name']}',
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          Text(
                                            '${row['detail'] ?? ''}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Icon(
                                      checked
                                          ? Icons.check_circle_rounded
                                          : Icons.add_circle_outline,
                                      color: colors.primary,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                    ),
                  );
                },
              ),
            ),
          ),
      ],
    );
  }
}
