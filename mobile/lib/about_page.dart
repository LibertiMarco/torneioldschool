import 'package:flutter/material.dart';

import 'api.dart';
import 'article_content.dart';
import 'details.dart' show DataPage;
import 'external_link_button.dart';
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
      final organizers = TosApi.rows(data['organizers']);
      final staff = TosApi.rows(data['staff']);
      return ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        children: [
          _hero(data),
          _heading('La nostra storia', 'LO SPIRITO OLD SCHOOL'),
          ArticleContent(content: '${data['intro_html'] ?? ''}'),
          if ('${data['highlight'] ?? ''}'.trim().isNotEmpty)
            Container(
              margin: const EdgeInsets.symmetric(vertical: 22),
              padding: const EdgeInsets.only(left: 20, top: 4, bottom: 4),
              decoration: const BoxDecoration(
                border: Border(left: BorderSide(color: siteRed, width: 3)),
              ),
              child: Text(
                '${data['highlight']}',
                style: const TextStyle(
                  color: siteBlue,
                  fontSize: 23,
                  height: 1.25,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.5,
                ),
              ),
            ),
          if (TosApi.rows(data['facts']).isNotEmpty) ...[
            _heading('Vicini, in campo e fuori', 'LA NOSTRA COMMUNITY'),
            for (final fact in TosApi.rows(data['facts'])) _fact(fact),
          ],
          if (organizers.isNotEmpty) ...[
            _heading('Organizzatori', 'LE PERSONE DIETRO OLD SCHOOL'),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns =
                    constraints.maxWidth >= 350 &&
                    MediaQuery.textScalerOf(context).scale(16) <= 24;
                final width = columns
                    ? (constraints.maxWidth - 14) / 2
                    : constraints.maxWidth;
                return Wrap(
                  spacing: 14,
                  runSpacing: 14,
                  children: [
                    for (final organizer in organizers)
                      SizedBox(width: width, child: _organizer(organizer)),
                  ],
                );
              },
            ),
          ],
          if (staff.isNotEmpty) ...[
            _heading('Staff', 'UNA SQUADRA, TANTE PASSIONI'),
            for (final group in staff) ...[
              _staffHeading(group),
              const SizedBox(height: 12),
              Container(
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Column(
                  children: [
                    for (final member in TosApi.rows(group['members']))
                      _member(member),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],
          ],
        ],
      );
    },
  );

  Widget _heading(String title, String eyebrow) => Padding(
    padding: const EdgeInsets.only(top: 28, bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          eyebrow,
          style: const TextStyle(
            color: siteRed,
            fontSize: 9,
            letterSpacing: 1.8,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          title,
          style: const TextStyle(
            color: siteBlue,
            fontSize: 27,
            fontWeight: FontWeight.w900,
            letterSpacing: -.7,
            height: 1.1,
          ),
        ),
      ],
    ),
  );

  Widget _hero(Map<String, dynamic> data) => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      color: siteBlue,
      borderRadius: BorderRadius.circular(24),
    ),
    child: Stack(
      children: [
        const Positioned.fill(child: CustomPaint(painter: _AboutArtwork())),
        Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Image.asset(
                    'assets/logo_old_school.png',
                    width: 58,
                    height: 58,
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Text(
                      'TORNEI\nOLD SCHOOL',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        letterSpacing: 2,
                        height: 1.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 34),
              const SizedBox(
                width: 32,
                height: 3,
                child: ColoredBox(color: siteRed),
              ),
              const SizedBox(height: 16),
              Text(
                '${data['subtitle'] ?? ''}',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  height: 1.08,
                  letterSpacing: -.9,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
      ],
    ),
  );

  IconData _factIcon(String title) => switch (title) {
    'Dove operiamo' => Icons.location_on_outlined,
    'Contatto diretto' => Icons.alternate_email,
    'Sponsor e partnership' => Icons.handshake_outlined,
    'Community' => Icons.groups_outlined,
    _ => Icons.info_outline,
  };

  Widget _fact(Map<String, dynamic> fact) => Container(
    padding: const EdgeInsets.symmetric(vertical: 18),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: Color(0xffdce3e9))),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(_factIcon('${fact['title']}'), color: siteRed, size: 19),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${fact['title'] ?? ''}',
                style: const TextStyle(
                  color: siteBlue,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              SelectableText(
                '${fact['text'] ?? ''}',
                style: const TextStyle(
                  color: Color(0xff586879),
                  fontSize: 13,
                  height: 1.5,
                ),
              ),
              if (externalLinkUri(fact['link'], allowEmail: true)
                  case final Uri uri) ...[
                const SizedBox(height: 10),
                ExternalLinkButton(
                  uri: uri,
                  label: 'Scrivici',
                  icon: Icons.north_east,
                  color: siteBlue,
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );

  Widget _organizer(Map<String, dynamic> organizer) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: siteBlue,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 46,
          height: 46,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .1),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(
            _initials('${organizer['nome'] ?? ''}'),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          '${organizer['nome'] ?? ''}',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 24,
            fontWeight: FontWeight.w900,
            letterSpacing: -.5,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          '${organizer['description'] ?? ''}',
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 13,
            height: 1.6,
          ),
        ),
      ],
    ),
  );

  Widget _staffHeading(Map<String, dynamic> group) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        '${group['label'] ?? ''}',
        style: const TextStyle(
          color: siteBlue,
          fontSize: 19,
          fontWeight: FontWeight.w800,
        ),
      ),
      const SizedBox(height: 5),
      Text(
        '${group['description'] ?? ''}',
        style: const TextStyle(
          color: Color(0xff586879),
          fontSize: 12,
          height: 1.5,
        ),
      ),
    ],
  );

  String _initials(String name) => name
      .split(RegExp(r'\s+'))
      .where((s) => s.isNotEmpty)
      .take(2)
      .map((s) => s.characters.first.toUpperCase())
      .join();

  Widget _member(Map<String, dynamic> member) => Container(
    padding: const EdgeInsets.all(16),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: Color(0xffedf0f4))),
    ),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final name = '${member['nome'] ?? ''}';
        final path = '${member['foto'] ?? ''}'.trim();
        final parsed = Uri.tryParse(path);
        final uri = parsed == null ? null : api.baseUrl.resolveUri(parsed);
        final placeholder = Container(
          color: const Color(0xffe8edf2),
          alignment: Alignment.center,
          child: Text(
            _initials(name),
            style: const TextStyle(
              color: siteBlue,
              fontWeight: FontWeight.w900,
              fontSize: 20,
            ),
          ),
        );
        final photo = ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            width: 56,
            height: 64,
            child:
                path.isEmpty ||
                    path.toLowerCase().contains('unknown.jpg') ||
                    uri == null ||
                    uri.scheme != 'https' ||
                    uri.userInfo.isNotEmpty
                ? placeholder
                : Image.network(
                    uri.toString(),
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => placeholder,
                  ),
          ),
        );
        final identity = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              name,
              style: const TextStyle(
                color: siteBlue,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              '${member['ruolo'] ?? ''}',
              style: const TextStyle(color: Color(0xff586879), fontSize: 12),
            ),
          ],
        );
        if (MediaQuery.textScalerOf(context).scale(16) > 24) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [photo, const SizedBox(height: 12), identity],
          );
        }
        return Row(
          children: [
            photo,
            const SizedBox(width: 14),
            Expanded(child: identity),
          ],
        );
      },
    ),
  );
}

class _AboutArtwork extends CustomPainter {
  const _AboutArtwork();
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white.withValues(alpha: .04);
    canvas.drawPath(
      Path()
        ..moveTo(size.width * .8, 0)
        ..lineTo(size.width, 0)
        ..lineTo(size.width * .3, size.height)
        ..lineTo(0, size.height)
        ..close(),
      paint,
    );
    final line = Paint()
      ..color = Colors.white.withValues(alpha: .08)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    canvas.drawCircle(Offset(size.width, size.height * .1), 105, line);
    canvas.drawCircle(Offset(size.width, size.height * .1), 140, line);
  }

  @override
  bool shouldRepaint(covariant _AboutArtwork oldDelegate) => false;
}
