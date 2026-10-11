import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

Uri? externalLinkUri(dynamic value, {bool allowEmail = false}) {
  var text = '${value ?? ''}'.trim();
  if (text.isEmpty) return null;
  if (text.startsWith('//')) text = 'https:$text';
  if (!text.contains(':') &&
      RegExp(r'^[\w.-]+\.[a-zA-Z]{2,}/').hasMatch(text)) {
    text = 'https://$text';
  }
  final uri = Uri.tryParse(text);
  if (uri == null || uri.userInfo.isNotEmpty) return null;
  if (allowEmail && uri.scheme == 'mailto' && uri.path.contains('@')) {
    return uri;
  }
  return ['https', 'http'].contains(uri.scheme) && uri.host.isNotEmpty
      ? uri
      : null;
}

class ExternalLinkButton extends StatelessWidget {
  const ExternalLinkButton({
    super.key,
    required this.uri,
    required this.label,
    required this.icon,
    this.color,
  });
  final Uri uri;
  final String label;
  final IconData icon;
  final Color? color;

  @override
  Widget build(BuildContext context) => FilledButton.icon(
    style: FilledButton.styleFrom(
      backgroundColor: color,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    icon: Icon(icon),
    label: Text(label),
    onPressed: () async {
      try {
        if (await launchUrl(
          uri,
          mode: LaunchMode.externalApplication,
          webOnlyWindowName: '_blank',
        )) {
          return;
        }
      } catch (_) {
        // Show the same retryable message if the platform cannot open the URL.
      }
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Impossibile aprire il link. Riprova.')),
        );
      }
    },
  );
}
