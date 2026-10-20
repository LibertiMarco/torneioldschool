/// Calendar dates are compared with today on the device, ignoring kickoff time.
/// Keep undated games after scheduled games and before overdue, unplayed games.
List<Map<String, dynamic>> orderPersonalMatches(
  List<Map<String, dynamic>> matches, {
  DateTime? now,
}) {
  final current = now ?? DateTime.now();
  final today = DateTime(current.year, current.month, current.day);
  DateTime? date(Map<String, dynamic> match) {
    final parsed = DateTime.tryParse('${match['data_partita'] ?? ''}');
    return parsed == null
        ? null
        : DateTime(parsed.year, parsed.month, parsed.day);
  }

  int group(DateTime? day) => day == null
      ? 1
      : day.isBefore(today)
      ? 2
      : 0;
  final entries = matches.asMap().entries.toList()
    ..sort((a, b) {
      final first = date(a.value), second = date(b.value);
      var result = group(first).compareTo(group(second));
      if (result == 0 && first != null && second != null) {
        result = first.compareTo(second);
        if (result == 0) {
          result = '${a.value['ora_partita'] ?? ''}'.compareTo(
            '${b.value['ora_partita'] ?? ''}',
          );
        }
      }
      return result == 0 ? a.key.compareTo(b.key) : result;
    });
  return entries.map((entry) => entry.value).toList();
}
