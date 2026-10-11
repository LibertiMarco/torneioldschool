import 'dart:convert';

import 'package:flutter/widgets.dart';

typedef StaffMessage = Map<String, dynamic>;

String safeStaffFilename(String value) {
  final cleaned = value
      .replaceAll(RegExp(r'[\\/:*?"<>|\x00-\x1f]'), '_')
      .replaceFirst(RegExp(r'^\.+'), '')
      .trim();
  return cleaned.isEmpty || cleaned.length > 160 ? 'grafica.png' : cleaned;
}

abstract class StaffEngine {
  Widget get host;
  Future<void> start(Uri uri, Map<String, String> headers);
  void send(StaffMessage message);
  Future<void> save(StaffMessage file, Rect shareOrigin);
  void dispose();
}

StaffMessage? decodeStaffMessage(String raw, String channel) {
  try {
    final value = jsonDecode(raw);
    if (value is! Map ||
        value['channel'] != channel ||
        value['type'] is! String) {
      return null;
    }
    return Map<String, dynamic>.from(value);
  } catch (_) {
    return null;
  }
}
