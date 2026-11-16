import 'package:flutter/material.dart';

class RankingPagination extends StatelessWidget {
  const RankingPagination({
    super.key,
    required this.page,
    required this.totalPages,
    required this.players,
    required this.onChanged,
  });

  final int page, totalPages, players;
  final ValueChanged<int> onChanged;

  Future<void> _choosePage(BuildContext context) async {
    final selected = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) => SizedBox(
        height: MediaQuery.sizeOf(context).height * .6,
        child: Column(
          children: [
            Text(
              'Scegli la pagina',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            Expanded(
              child: ListView.builder(
                itemCount: totalPages,
                itemBuilder: (context, index) => ListTile(
                  selected: index + 1 == page,
                  leading: const Icon(Icons.format_list_numbered_rounded),
                  title: Text('Pagina ${index + 1}'),
                  trailing: index + 1 == page
                      ? const Icon(Icons.check_circle_rounded)
                      : null,
                  onTap: () => Navigator.pop(context, index + 1),
                ),
              ),
            ),
          ],
        ),
      ),
    );
    if (selected != null && selected != page && context.mounted) {
      onChanged(selected);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(top: 16, bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Pagina $page di $totalPages · $players giocatori',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.labelLarge
                ?.copyWith(color: colors.onSurfaceVariant),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              IconButton.filledTonal(
                tooltip: 'Pagina precedente',
                onPressed: page > 1 ? () => onChanged(page - 1) : null,
                icon: const Icon(Icons.arrow_back_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Tooltip(
                  message: 'Seleziona pagina',
                  child: FilledButton(
                    onPressed: totalPages > 1
                        ? () => _choosePage(context)
                        : null,
                    style: FilledButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    child: Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 6,
                      children: [
                        Text('$page / $totalPages'),
                        const Icon(Icons.unfold_more_rounded, size: 18),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              IconButton.filledTonal(
                tooltip: 'Pagina successiva',
                onPressed: page < totalPages ? () => onChanged(page + 1) : null,
                icon: const Icon(Icons.arrow_forward_rounded),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
