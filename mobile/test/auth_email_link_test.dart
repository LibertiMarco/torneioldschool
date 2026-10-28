import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/auth_web_page.dart';

void main() {
  test('Email actions accept only the configured origin and backend path', () {
    final base = Uri.parse('https://example.test/tos');
    for (final page in ['verify_email.php', 'reset_password.php']) {
      expect(
        accountEmailLink(base, 'https://example.test/tos/$page?token=abc'),
        isNotNull,
      );
    }
    for (final value in [
      'javascript:alert(1)',
      'mailto:user@example.test',
      'not a link',
      'https://evil.test/tos/verify_email.php?token=abc',
      'https://user@example.test/tos/verify_email.php',
      'http://example.test/tos/verify_email.php',
      'https://example.test/verify_email.php',
      'https://example.test/tos/login.php',
    ]) {
      expect(accountEmailLink(base, value), isNull, reason: value);
    }
  });
}
