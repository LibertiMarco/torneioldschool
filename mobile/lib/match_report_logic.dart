import 'player_role_badges.dart';

bool matchWasPlayed(Map<String, dynamic> match) =>
    match['giocata'] == true || '${match['giocata']}' == '1';

int playerReportPriority(Map<String, dynamic> player) {
  int count(String key) => int.tryParse('${player[key] ?? 0}') ?? 0;
  if (isGoalkeeper(player)) return 0;
  if (player['is_captain'] == true ||
      player['captain'] == true ||
      count('is_captain') > 0 ||
      count('captain') > 0) {
    return 1;
  }
  if (count('goal') > 0) return 2;
  if (count('cartellino_giallo') > 0 || count('cartellino_rosso') > 0) return 3;
  return 4;
}

/// Bucket partition preserves backend order for equal priorities and all events.
List<Map<String, dynamic>> orderedMatchPlayers(
  List<Map<String, dynamic>> players,
) {
  final buckets = List.generate(5, (_) => <Map<String, dynamic>>[]);
  for (final player in players) {
    buckets[playerReportPriority(player)].add(player);
  }
  return buckets.expand((bucket) => bucket).toList();
}
