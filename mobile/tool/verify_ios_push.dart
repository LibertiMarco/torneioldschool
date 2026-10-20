import 'dart:convert';
import 'dart:io';

const iosBundleId = 'it.torneioldschool.torneiOldSchool';
const appleTeamId = 'DJX3Z8L84Y';

void validateIosPush({
  required Map<String, dynamic> info,
  required Map<String, dynamic> firebase,
  required Map<String, dynamic> signed,
  required Map<String, dynamic> profile,
}) {
  void require(bool condition, String message) {
    if (!condition) throw StateError(message);
  }

  require(info['CFBundleIdentifier'] == iosBundleId, 'Bundle ID IPA errato');
  require(firebase['BUNDLE_ID'] == iosBundleId, 'Bundle ID Firebase errato');
  require(
    firebase['PROJECT_ID'] == 'tornei-old-school-9e11b',
    'Progetto Firebase errato',
  );
  require(
    firebase['GOOGLE_APP_ID'] == '1:1080279212423:ios:5c4114f569667f22cf7322',
    'App Firebase errata',
  );
  require(
    firebase['GCM_SENDER_ID'] == '1080279212423',
    'Sender Firebase errato',
  );
  require(
    info['FirebaseAppDelegateProxyEnabled'] != false,
    'Swizzling Firebase disabilitato',
  );
  require(
    (info['UIBackgroundModes'] as List?)?.contains('remote-notification') ==
        true,
    'Background remote-notification assente',
  );
  for (final entry in {
    'firma IPA': signed,
    'provisioning profile': profile,
  }.entries) {
    require(
      entry.value['aps-environment'] == 'production',
      '${entry.key}: APNs production assente',
    );
    require(
      entry.value['application-identifier'] == '$appleTeamId.$iosBundleId',
      '${entry.key}: application-identifier errato',
    );
    require(
      entry.value['com.apple.developer.team-identifier'] == appleTeamId,
      '${entry.key}: team errato',
    );
  }
}

Future<String> run(String command, List<String> args) async {
  final result = await Process.run(command, args);
  if (result.exitCode != 0) {
    throw StateError('$command fallito: ${result.stderr}');
  }
  return result.stdout as String;
}

Future<Map<String, dynamic>> plist(String path, {String? key}) async {
  final output = await run('/usr/bin/plutil', [
    if (key == null) ...['-convert', 'json'] else ...['-extract', key, 'json'],
    '-o',
    '-',
    path,
  ]);
  return jsonDecode(output) as Map<String, dynamic>;
}

Future<void> main(List<String> args) async {
  if (!Platform.isMacOS || args.length != 1) {
    throw ArgumentError(
      'Uso su macOS: dart run tool/verify_ios_push.dart percorso.ipa',
    );
  }
  final ipa = File(args.single).absolute;
  if (!ipa.existsSync()) throw StateError('IPA non trovata: ${ipa.path}');
  final temporary = await Directory.systemTemp.createTemp('tos-ios-push-');
  try {
    await run('/usr/bin/unzip', ['-q', ipa.path, '-d', temporary.path]);
    final apps = Directory('${temporary.path}/Payload')
        .listSync()
        .whereType<Directory>()
        .where((d) => d.path.endsWith('.app'))
        .toList();
    if (apps.length != 1) throw StateError('Attesa una sola app nel Payload');
    final app = apps.single.path;
    await run('/usr/bin/codesign', ['--verify', '--deep', '--strict', app]);
    final signedFile = File('${temporary.path}/signed.plist');
    await signedFile.writeAsString(
      await run('/usr/bin/codesign', [
        '--display',
        '--entitlements',
        ':-',
        app,
      ]),
    );
    final profileFile = File('${temporary.path}/profile.plist');
    await profileFile.writeAsString(
      await run('/usr/bin/security', [
        'cms',
        '-D',
        '-i',
        '$app/embedded.mobileprovision',
      ]),
    );
    validateIosPush(
      info: await plist('$app/Info.plist'),
      firebase: await plist('$app/GoogleService-Info.plist'),
      signed: await plist(signedFile.path),
      profile: await plist(profileFile.path, key: 'Entitlements'),
    );
    stdout.writeln(
      'IPA verificata: firma valida, APNs production e Firebase coerenti.',
    );
  } finally {
    await temporary.delete(recursive: true);
  }
}
