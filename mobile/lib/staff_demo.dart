import 'staff_engine.dart';

// Local fixtures only. Never call the production controller in demo mode.
StaffMessage staffDemoSnapshot(String path, String title) {
  Map<String, dynamic> field(String label, String type, [String value = '']) =>
      {
        'kind': 'field',
        'id': label,
        'label': label,
        'type': type,
        'value': value,
        'options': [
          {'value': '', 'label': 'Seleziona…'},
          {'value': '1', 'label': 'Torneo Old School · Demo'},
          {'value': '2', 'label': 'Coppa Silver · Demo'},
        ],
      };
  Map<String, dynamic> action(String label) => {
    'kind': 'action',
    'id': label,
    'label': label,
  };
  final graphics = path.contains('grafiche_') || path.contains('copertina_');
  final fields = <Map<String, dynamic>>[];
  if (graphics) {
    fields.addAll([field('Torneo', 'select'), field('Formato', 'select')]);
    if (path.contains('settimana')) {
      fields.add(field('Giorno delle partite', 'date', '2026-10-11'));
    }
    if (path.contains('copertina') || path.contains('post_partita')) {
      fields.addAll([field('Partita', 'select'), field('Foto', 'file')]);
    }
  } else if (path.contains('blog')) {
    fields.addAll([
      field('Titolo', 'text'),
      field('Sottotitolo', 'text'),
      field('Paragrafo', 'rich', '<p>Testo <strong>in grassetto</strong>.</p>'),
      field('Media', 'file'),
    ]);
  } else if (path.contains('partite')) {
    fields.addAll([
      field('Torneo', 'select'),
      field('Squadra casa', 'select'),
      field('Squadra ospite', 'select'),
      field('Data', 'date'),
      field('Gol casa', 'number', '0'),
      field('Gol ospite', 'number', '0'),
    ]);
  } else if (path.contains('giocatori') ||
      path.contains('staff') ||
      path.contains('account_giocatore')) {
    fields.addAll([
      field('Nome', 'text'),
      field('Cognome', 'text'),
      field('Squadra', 'select'),
      field('Foto', 'file'),
    ]);
  } else if (path.contains('admin_mobile')) {
    fields.add({
      'kind': 'notice',
      'text': 'Accesso mobile: disattivato · dati dimostrativi',
    });
  } else if (path.contains('fantacalcio')) {
    return {
      'type': 'snapshot',
      'document': 'demo',
      'blocks': [
        {'kind': 'heading', 'text': 'Fantacalcio'},
        {
          'kind': 'notice',
          'text': 'Sezione in preparazione. Le funzioni di gestione delle leghe non sono ancora disponibili.',
        },
      ],
    };
  } else {
    fields.addAll([
      field(
        path.contains('giornata') ||
                path.contains('video') ||
                path.contains('albo')
            ? 'Torneo'
            : 'Nome',
        path.contains('giornata') ||
                path.contains('video') ||
                path.contains('albo')
            ? 'select'
            : 'text',
      ),
      field('Descrizione', 'textarea'),
      field('Immagine', 'file'),
    ]);
  }
  return {
    'type': 'snapshot',
    'document': 'demo',
    'blocks': [
      {
        'kind': 'notice',
        'text': 'Demo: le modifiche restano in questa schermata.',
      },
      if (!graphics && !path.contains('admin_mobile'))
        {
          'kind': 'tabs',
          'blocks': [
            for (final name in ['Crea', 'Modifica', 'Elimina'])
              {...action(name), 'tab': true, 'selected': name == 'Crea'},
          ],
        },
      {
        'kind': 'form',
        'id': 'demo-form',
        'blocks': [
          ...fields,
          action(
            graphics
                ? 'Genera grafica'
                : path.contains('admin_mobile')
                ? 'Prepara e attiva accesso mobile'
                : 'Salva',
          ),
        ],
      },
    ],
  };
}
