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
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
    );
    const label = TextStyle(fontSize: 14, fontWeight: FontWeight.w700);
    final primary = FilledButton.styleFrom(
      backgroundColor: siteBlue,
      foregroundColor: Colors.white,
      disabledBackgroundColor: const Color(0xffe2e8ef),
      disabledForegroundColor: const Color(0xff758496),
      minimumSize: const Size(48, 50),
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      shape: shape,
      textStyle: label,
    );
    final secondary = OutlinedButton.styleFrom(
      backgroundColor: staffPale,
      foregroundColor: siteBlue,
      disabledForegroundColor: const Color(0xff758496),
      minimumSize: const Size(48, 50),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      shape: shape,
      side: const BorderSide(color: staffBorder),
      textStyle: label,
    );
    return Theme(
      data: base.copyWith(
        colorScheme: base.colorScheme.copyWith(
          primary: siteBlue,
          secondary: staffAccent,
          secondaryContainer: staffPale,
          onSecondaryContainer: siteBlue,
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
            foregroundColor: staffAccent,
            backgroundColor: staffPale,
            disabledForegroundColor: const Color(0xff8c99a8),
            minimumSize: const Size(48, 48),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
        chipTheme: base.chipTheme.copyWith(
          backgroundColor: Colors.white,
          selectedColor: siteBlue,
          disabledColor: const Color(0xffe2e8ef),
          checkmarkColor: Colors.white,
          labelStyle: label,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          shape: const StadiumBorder(),
          side: const BorderSide(color: staffBorder),
          showCheckmark: false,
        ),
        segmentedButtonTheme: SegmentedButtonThemeData(
          style: secondary.copyWith(
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? siteBlue
                  : Colors.white,
            ),
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? Colors.white
                  : siteBlue,
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
    labelStyle: TextStyle(
      color: onSelected == null
          ? const Color(0xff758496)
          : selected
          ? Colors.white
          : siteBlue,
      fontWeight: FontWeight.w700,
    ),
    side: BorderSide(color: selected ? siteBlue : staffBorder),
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
          foregroundColor: const Color(0xffb71f39),
          backgroundColor: const Color(0xfffff1f3),
          side: const BorderSide(color: Color(0xffedc6ce)),
        ),
        child: content,
      );
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(
          colors: onPressed == null
              ? [const Color(0xffe2e8ef), const Color(0xffe2e8ef)]
              : [siteBlue, const Color(0xff245c78)],
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
          gradient: const LinearGradient(
            colors: [Colors.white, Color(0xfff0f6fa)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: staffBorder),
        ),
        child: InkWell(onTap: onTap, child: child),
      ),
    ),
  );
}
