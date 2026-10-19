import 'package:flutter/foundation.dart';

/// Mirrors the website's Maps search using its real venue field. Never invents
/// addresses. Coordinates/address, when provided, are preferred to a venue name.
Uri? venueNavigationUri(
  Map<String, dynamic> match, {
  TargetPlatform? platform,
}) {
  final lat = double.tryParse('${match['campo_latitudine'] ?? ''}');
  final lng = double.tryParse('${match['campo_longitudine'] ?? ''}');
  final address = '${match['campo_indirizzo'] ?? ''}'.trim();
  final name = '${match['campo'] ?? ''}'.trim();
  final query =
      lat != null && lng != null && lat.abs() <= 90 && lng.abs() <= 180
      ? '$lat,$lng'
      : address.isNotEmpty
      ? address
      : name;
  if (query.isEmpty ||
      [
        'online',
        'da definire',
        'campo da definire',
      ].contains(query.toLowerCase())) {
    return null;
  }
  if ((platform ?? defaultTargetPlatform) == TargetPlatform.iOS && !kIsWeb) {
    return Uri.https('maps.apple.com', '/', {'daddr': query});
  }
  return Uri.https('www.google.com', '/maps/search/', {
    'api': '1',
    'query': query,
  });
}
