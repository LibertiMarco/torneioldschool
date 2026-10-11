import 'package:flutter/material.dart';

import 'api.dart';
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';
import 'ranking_surface.dart';

class HomeSectionTitle extends StatelessWidget {
  const HomeSectionTitle({
    super.key,
    required this.title,
    required this.eyebrow,
  });
  final String title, eyebrow;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 30, bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          eyebrow,
          style: const TextStyle(
            color: siteRed,
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 2,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          title,
          style: const TextStyle(
            color: siteBlue,
            fontSize: 26,
            fontWeight: FontWeight.w900,
            letterSpacing: -.8,
          ),
        ),
      ],
    ),
  );
}

class HomeRankingEntry extends StatelessWidget {
  const HomeRankingEntry({
    super.key,
    required this.api,
    required this.row,
    required this.order,
    required this.esport,
    required this.open,
    this.index = 0,
  });
  final TosApi api;
  final Map<String, dynamic> row;
  final String order;
  final bool esport;
  final VoidCallback open;
  final int index;
  @override
  Widget build(BuildContext context) {
    final first = '${row['posizione']}' == '1';
    final statistics = esport
        ? ['punti']
        : order == 'presenze'
        ? ['presenze', 'gol']
        : ['gol', 'presenze'];
    final name = '${row['nome'] ?? ''} ${row['cognome'] ?? ''}'.trim();
    final path = '${row['foto'] ?? ''}';
    final initials = name
        .split(RegExp(r'\s+'))
        .where((s) => s.isNotEmpty)
        .take(2)
        .map((s) => s.characters.first)
        .join()
        .toUpperCase();
    final photo = ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: path.isEmpty || path.toLowerCase().contains('unknown.jpg')
          ? Container(
              width: 42,
              height: 48,
              alignment: Alignment.center,
              color: const Color(0xffedf1f5),
              child: Text(
                initials,
                style: const TextStyle(
                  color: siteBlue,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            )
          : SiteImage(
              api: api,
              path: path,
              width: 42,
              height: 48,
              fallback: Icons.person_outline,
            ),
    );
    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          name,
          style: const TextStyle(
            color: siteBlue,
            fontSize: 15,
            fontWeight: FontWeight.w800,
            letterSpacing: -.3,
          ),
        ),
        if ('${row['ruolo'] ?? ''}'.isNotEmpty) ...[
          const SizedBox(height: 3),
          Text(
            '${row['ruolo']}',
            style: const TextStyle(color: Color(0xff667585), fontSize: 10),
          ),
        ],
        if (esport) ...[
          const SizedBox(height: 3),
          Text(
            '${row['tornei_giocati'] ?? 0} tornei · ${row['best_result'] ?? ''}',
            style: const TextStyle(color: Color(0xff667585), fontSize: 10),
          ),
        ],
        const SizedBox(height: 8),
        Wrap(
          spacing: 14,
          runSpacing: 6,
          children: [
            for (final stat in statistics)
              Semantics(
                label:
                    '${row[stat] ?? 0} ${stat == 'gol'
                        ? 'gol'
                        : stat == 'punti'
                        ? 'punti'
                        : 'presenze'}',
                excludeSemantics: true,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (!esport) ...[
                      Image.asset(
                        stat == 'gol'
                            ? 'assets/emoji/football.png'
                            : 'assets/emoji/clipboard.png',
                        width: 16,
                        height: 16,
                      ),
                      const SizedBox(width: 5),
                    ],
                    Text(
                      esport ? '${row[stat] ?? 0} pt' : '${row[stat] ?? 0}',
                      style: TextStyle(
                        color: stat == statistics.first
                            ? siteBlue
                            : const Color(0xff667585),
                        fontWeight: stat == statistics.first
                            ? FontWeight.w800
                            : FontWeight.w500,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
        if (row['media_voti'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              'Media voto: ${row['media_voti']}',
              style: const TextStyle(fontSize: 10, color: Color(0xff667585)),
            ),
          ),
      ],
    );
    return RankingSurface(
      index: index,
      child: InkWell(
        onTap: open,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: Color(0xffedf0f4))),
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final leading = Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(
                    width: 24,
                    child: Text(
                      '${row['posizione'] ?? '–'}',
                      style: TextStyle(
                        color: first ? siteRed : const Color(0xff667585),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  photo,
                ],
              );
              if (MediaQuery.textScalerOf(context).scale(16) > 24) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [leading, const SizedBox(height: 12), details],
                );
              }
              return Row(
                children: [
                  leading,
                  const SizedBox(width: 12),
                  Expanded(child: details),
                  const SizedBox(width: 8),
                  const Icon(
                    Icons.north_east,
                    size: 15,
                    color: Color(0xff8e9aa6),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class HomeNewsTile extends StatelessWidget {
  const HomeNewsTile({
    super.key,
    required this.api,
    required this.row,
    required this.open,
    this.featured = false,
  });
  final TosApi api;
  final Map<String, dynamic> row;
  final VoidCallback open;
  final bool featured;
  @override
  Widget build(BuildContext context) {
    final path = '${row['cover'] ?? row['immagine'] ?? ''}';
    return Material(
      color: featured ? siteBlue : Colors.transparent,
      borderRadius: BorderRadius.circular(featured ? 20 : 0),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: open,
        child: featured
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (path.isNotEmpty)
                    SiteImage(
                      api: api,
                      path: path,
                      width: double.infinity,
                      height: 164,
                    ),
                  Padding(
                    padding: const EdgeInsets.all(22),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'IN PRIMO PIANO',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 9,
                            letterSpacing: 2,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          '${row['titolo'] ?? ''}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 26,
                            fontWeight: FontWeight.w900,
                            height: 1.12,
                            letterSpacing: -.5,
                          ),
                        ),
                        const SizedBox(height: 20),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${row['data'] ?? ''}',
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 11,
                                ),
                              ),
                            ),
                            const Icon(
                              Icons.arrow_forward,
                              color: Colors.white,
                              size: 18,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              )
            : Container(
                padding: const EdgeInsets.symmetric(vertical: 18),
                decoration: const BoxDecoration(
                  border: Border(bottom: BorderSide(color: Color(0xffdfe5eb))),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${row['data'] ?? ''}',
                            style: const TextStyle(
                              color: Color(0xff667585),
                              fontSize: 10,
                            ),
                          ),
                          const SizedBox(height: 7),
                          Text(
                            '${row['titolo'] ?? ''}',
                            style: const TextStyle(
                              color: siteBlue,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                              letterSpacing: -.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 16),
                    if (path.isNotEmpty)
                      SiteImage(api: api, path: path, width: 76, height: 76)
                    else
                      const Padding(
                        padding: EdgeInsets.only(top: 22),
                        child: Icon(
                          Icons.north_east,
                          color: siteBlue,
                          size: 18,
                        ),
                      ),
                  ],
                ),
              ),
      ),
    );
  }
}
