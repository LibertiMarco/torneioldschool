import 'package:flutter_test/flutter_test.dart';

import '../tool/verify_ios_push.dart';

void main() {
  late Map<String, dynamic> info, firebase, signed, profile;
  void verify() => validateIosPush(
    info: info,
    firebase: firebase,
    signed: signed,
    profile: profile,
  );
  setUp(() {
    info = {
      'CFBundleIdentifier': iosBundleId,
      'UIBackgroundModes': ['remote-notification'],
    };
    firebase = {
      'BUNDLE_ID': iosBundleId,
      'PROJECT_ID': 'tornei-old-school-9e11b',
      'GOOGLE_APP_ID': '1:1080279212423:ios:5c4114f569667f22cf7322',
      'GCM_SENDER_ID': '1080279212423',
    };
    signed = {
      'aps-environment': 'production',
      'application-identifier': '$appleTeamId.$iosBundleId',
      'com.apple.developer.team-identifier': appleTeamId,
    };
    profile = Map.of(signed);
  });
  test(
    'accepts production signing and matching Firebase config',
    () => verify(),
  );
  test('rejects a signed app without APNs', () {
    signed.remove('aps-environment');
    expect(verify, throwsStateError);
  });
  test('rejects a sandbox provisioning profile', () {
    profile['aps-environment'] = 'development';
    expect(verify, throwsStateError);
  });
  test('rejects a different Firebase app', () {
    firebase['BUNDLE_ID'] = 'other.app';
    expect(verify, throwsStateError);
  });
  test('rejects a different signing team', () {
    signed['com.apple.developer.team-identifier'] = 'OTHER';
    expect(verify, throwsStateError);
  });
  test('rejects disabled Firebase delegate forwarding', () {
    info['FirebaseAppDelegateProxyEnabled'] = false;
    expect(verify, throwsStateError);
  });
}
