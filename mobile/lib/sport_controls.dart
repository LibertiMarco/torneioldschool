import 'package:flutter/material.dart';

/// Shared compact controls; colors always come from the active theme.
class SportSegments<T> extends StatelessWidget {
  const SportSegments({
    super.key,
    required this.value,
    required this.options,
    required this.onChanged,
  });
  final T value;
  final Map<T, String> options;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          for (final option in options.entries)
            Expanded(
              child: Semantics(
                selected: value == option.key,
                button: true,
                child: AnimatedContainer(
                  duration: MediaQuery.disableAnimationsOf(context)
                      ? Duration.zero
                      : const Duration(milliseconds: 160),
                  decoration: BoxDecoration(
                    color: value == option.key
                        ? colors.surface
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => onChanged(option.key),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 13,
                      ),
                      child: Text(
                        option.value,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontWeight: value == option.key
                              ? FontWeight.w800
                              : FontWeight.w500,
                          color: value == option.key
                              ? colors.onSurface
                              : colors.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class SportPagination extends StatelessWidget {
  const SportPagination({
    super.key,
    required this.page,
    this.total,
    this.previous,
    this.next,
  });
  final int page;
  final int? total;
  final VoidCallback? previous, next;
  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      margin: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Pagina precedente',
            onPressed: previous,
            icon: const Icon(Icons.chevron_left_rounded),
          ),
          Flexible(
            child: Semantics(
              liveRegion: true,
              child: Text(
                'Pagina $page${total == null ? '' : ' di $total'}',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Pagina successiva',
            onPressed: next,
            icon: const Icon(Icons.chevron_right_rounded),
          ),
        ],
      ),
    ),
  );
}

Future<String?> selectSportCategory(
  BuildContext context,
  List<String> categories,
  String selected,
) => showModalBottomSheet<String>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (context) => SafeArea(
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * .65,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              'Categoria',
              style: Theme.of(context).textTheme.titleLarge,
            ),
          ),
          Expanded(
            child: ListView(
              children: [
                for (final category in ['', ...categories])
                  ListTile(
                    title: Text(
                      category.isEmpty ? 'Tutte le categorie' : category,
                    ),
                    selected: selected == category,
                    trailing: selected == category
                        ? const Icon(Icons.check_circle_outline)
                        : null,
                    onTap: () => Navigator.pop(context, category),
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  ),
);
