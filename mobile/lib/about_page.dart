import 'package:flutter/material.dart';

import 'api.dart';
import 'article_content.dart';
import 'details.dart' show DataPage;
import 'external_link_button.dart';
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';

class AboutPage extends StatelessWidget {
  const AboutPage({super.key, required this.api});
  final TosApi api;

  @override
  Widget build(BuildContext context) => DataPage(
    title: 'Chi siamo',
    load: api.about,
    render: (value) {
      final data = Map<String, dynamic>.from(value as Map);
      Widget heading(String text) => Padding(
        padding: const EdgeInsets.only(top: 24, bottom: 12),
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.bold,
            color: siteBlue,
          ),
        ),
      );
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            color: siteBlue,
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                '${data['subtitle'] ?? ''}',
                style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),
          ArticleContent(content: '${data['intro_html'] ?? ''}'),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Text(
                '${data['highlight'] ?? ''}',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: siteBlue,
                ),
              ),
            ),
          ),
          for (final fact in TosApi.rows(data['facts']))
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${fact['title'] ?? ''}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    SelectableText('${fact['text'] ?? ''}'),
                    if (externalLinkUri(fact['link'], allowEmail: true)
                        case final Uri uri) ...[
                      const SizedBox(height: 12),
                      ExternalLinkButton(
                        uri: uri,
                        label: 'Scrivici',
                        icon: Icons.mail_outline,
                        color: siteBlue,
                      ),
                    ],
                  ],
                ),
              ),
            ),
          heading('Organizzatori'),
          for (final organizer in TosApi.rows(data['organizers']))
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${organizer['nome'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: siteBlue,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${organizer['description'] ?? ''}',
                      style: const TextStyle(height: 1.6),
                    ),
                  ],
                ),
              ),
            ),
          heading('Staff'),
          for (final group in TosApi.rows(data['staff'])) ...[
            Text(
              '${group['label'] ?? ''}',
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: siteBlue,
              ),
            ),
            const SizedBox(height: 6),
            Text('${group['description'] ?? ''}'),
            const SizedBox(height: 10),
            for (final member in TosApi.rows(group['members']))
              Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(16),
                  leading: SiteImage(
                    api: api,
                    path: '${member['foto'] ?? ''}',
                    fallback: Icons.person_outline,
                  ),
                  title: Text(
                    '${member['nome'] ?? ''}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Text('${member['ruolo'] ?? ''}'),
                ),
              ),
            const SizedBox(height: 16),
          ],
        ],
      );
    },
  );
}
