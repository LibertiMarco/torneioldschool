import 'package:flutter/material.dart';

import 'theme.dart';

const staffAccent = Color(0xff177e96);
const staffPale = Color(0xffedf5f9);
const staffBorder = Color(0xffd6e3ec);

class StaffBackButton extends StatelessWidget {
  const StaffBackButton({super.key});
  @override
  Widget build(BuildContext context) => BackButton(
    style: IconButton.styleFrom(
      foregroundColor: Colors.white,
      backgroundColor: const Color(0xff29445d),
    ),
  );
}

// Keep management controls consistent without changing the public app theme.
class StaffTheme extends StatelessWidget {
  const StaffTheme({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final base = Theme.of(context);
    final dark = base.brightness == Brightness.dark;
    final colors = dark
        ? base.colorScheme.copyWith(
            secondary: const Color(0xff80cedb),
            onSecondary: const Color(0xff123b44),
            secondaryContainer: const Color(0xff294952),
            onSecondaryContainer: const Color(0xffc3edf1),
          )
        : base.colorScheme.copyWith(
            primary: siteBlue,
            secondary: staffAccent,
            secondaryContainer: staffPale,
            onSecondaryContainer: siteBlue,
          );
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
    );
    final label = base.textTheme.labelLarge!.copyWith(
      fontSize: 14,
      fontWeight: FontWeight.w700,
    );
    final primary = FilledButton.styleFrom(
      backgroundColor: colors.primary,
      foregroundColor: colors.onPrimary,
      disabledBackgroundColor: colors.surfaceContainerHigh,
      disabledForegroundColor: colors.onSurfaceVariant,
      minimumSize: const Size(48, 50),
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      shape: shape,
      textStyle: label,
    );
    final secondary = OutlinedButton.styleFrom(
      backgroundColor: colors.secondaryContainer,
      foregroundColor: colors.onSurface,
      disabledForegroundColor: colors.onSurfaceVariant,
      minimumSize: const Size(48, 50),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      shape: shape,
      side: BorderSide(color: colors.outlineVariant),
      textStyle: label,
    );
    return Theme(
      data: base.copyWith(
        colorScheme: colors,
        inputDecorationTheme: base.inputDecorationTheme.copyWith(
          filled: true,
          fillColor: colors.surfaceContainerLowest,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: colors.outlineVariant),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(style: primary),
        elevatedButtonTheme: ElevatedButtonThemeData(style: primary),
        outlinedButtonTheme: OutlinedButtonThemeData(style: secondary),
        textButtonTheme: TextButtonThemeData(
          style: secondary.copyWith(
            side: const WidgetStatePropertyAll(BorderSide.none),
          ),
        ),
        iconButtonTheme: IconButtonThemeData(
          style: IconButton.styleFrom(
            foregroundColor: colors.secondary,
            backgroundColor: colors.secondaryContainer,
            disabledForegroundColor: colors.onSurfaceVariant,
            minimumSize: const Size(48, 48),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
        chipTheme: base.chipTheme.copyWith(
          backgroundColor: colors.surfaceContainerLow,
          selectedColor: colors.primary,
          disabledColor: colors.surfaceContainerHigh,
          checkmarkColor: colors.onPrimary,
          labelStyle: label,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          shape: const StadiumBorder(),
          side: BorderSide(color: colors.outlineVariant),
          showCheckmark: false,
        ),
        segmentedButtonTheme: SegmentedButtonThemeData(
          style: secondary.copyWith(
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? colors.primary
                  : colors.surfaceContainerLow,
            ),
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? colors.onPrimary
                  : colors.onSurface,
            ),
          ),
        ),
      ),
      child: child,
    );
  }
}

class StaffFilter extends StatelessWidget {
  const StaffFilter({
    super.key,
    required this.label,
    required this.selected,
    required this.onSelected,
  });
  final String label;
  final bool selected;
  final ValueChanged<bool>? onSelected;

  @override
  Widget build(BuildContext context) => ChoiceChip(
    label: Text(label),
    selected: selected,
    onSelected: onSelected,
    labelStyle: Theme.of(context).textTheme.labelLarge!.copyWith(
      color: onSelected == null
          ? const Color(0xff758496)
          : selected
          ? Theme.of(context).colorScheme.onPrimary
          : Theme.of(context).colorScheme.onSurface,
      fontWeight: FontWeight.w700,
    ),
    side: BorderSide(
      color: selected
          ? Theme.of(context).colorScheme.primary
          : Theme.of(context).colorScheme.outlineVariant,
    ),
  );
}

class StaffActionButton extends StatelessWidget {
  const StaffActionButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon = Icons.arrow_forward_rounded,
    this.danger = false,
  });
  final String label;
  final VoidCallback? onPressed;
  final IconData icon;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final content = Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, size: 20),
        const SizedBox(width: 10),
        Flexible(child: Text(label, textAlign: TextAlign.center)),
      ],
    );
    if (danger) {
      return OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: Theme.of(context).colorScheme.onErrorContainer,
          backgroundColor: Theme.of(context).colorScheme.errorContainer,
          side: BorderSide(color: Theme.of(context).colorScheme.error),
        ),
        child: content,
      );
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(
          colors: onPressed == null
              ? [
                  Theme.of(context).colorScheme.surfaceContainerHigh,
                  Theme.of(context).colorScheme.surfaceContainerHigh,
                ]
              : [
                  Theme.of(context).colorScheme.primary,
                  Theme.of(context).colorScheme.primary,
                ],
        ),
        boxShadow: onPressed == null
            ? []
            : const [
                BoxShadow(
                  color: Color(0x2015293e),
                  blurRadius: 12,
                  offset: Offset(0, 4),
                ),
              ],
      ),
      child: FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: Colors.transparent,
          disabledBackgroundColor: Colors.transparent,
        ),
        child: content,
      ),
    );
  }
}

IconData staffActionIcon(String label) {
  final text = label.toLowerCase();
  if (text.contains('elimina') || text.contains('rimuovi')) {
    return Icons.delete_outline_rounded;
  }
  if (text.contains('salva') ||
      text.contains('conferma') ||
      text.contains('applica')) {
    return Icons.check_circle_outline_rounded;
  }
  if (text.contains('scarica') || text.contains('esporta')) {
    return Icons.download_rounded;
  }
  if (text.contains('crea') ||
      text.contains('aggiungi') ||
      text.contains('nuov')) {
    return Icons.add_rounded;
  }
  if (text.contains('modifica')) return Icons.edit_outlined;
  if (text.contains('genera')) return Icons.auto_awesome_outlined;
  if (text.contains('cerca') || text.contains('filtra')) {
    return Icons.search_rounded;
  }
  if (text.contains('sincron') ||
      text.contains('ricarica') ||
      text.contains('riprova')) {
    return Icons.refresh_rounded;
  }
  return Icons.arrow_forward_rounded;
}

class StaffTapSurface extends StatelessWidget {
  const StaffTapSurface({super.key, required this.child, required this.onTap});
  final Widget child;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(22),
      boxShadow: const [
        BoxShadow(
          color: Color(0x0d15293e),
          blurRadius: 16,
          offset: Offset(0, 5),
        ),
      ],
    ),
    child: Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(22),
      clipBehavior: Clip.antiAlias,
      child: Ink(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              Theme.of(context).colorScheme.surfaceContainerLow,
              Theme.of(context).colorScheme.surfaceContainer,
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: Theme.of(context).colorScheme.outlineVariant,
          ),
        ),
        child: InkWell(onTap: onTap, child: child),
      ),
    ),
  );
}
