import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'auth.dart';
import 'home_dashboard.dart' show SiteImage;
import 'theme.dart';

class AccountPage extends StatefulWidget {
  const AccountPage({super.key, required this.auth});
  final TosAuth auth;
  @override
  State<AccountPage> createState() => _AccountPageState();
}

class _AccountPageState extends State<AccountPage> {
  final _form = GlobalKey<FormState>();
  final _scroll = ScrollController();
  final _name = TextEditingController();
  final _surname = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _current = TextEditingController();
  Map<String, dynamic>? _profile;
  Map<String, dynamic>? _player;
  bool _newsletter = false, _marketing = false, _tracking = false;
  bool _loading = true, _saving = false;
  String? _error, _message;
  ApiUpload? _avatar, _playerPhoto;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _scroll.dispose();
    for (final controller in [_name, _surname, _password, _confirm, _current]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _apply(dynamic response) {
    final profile = Map<String, dynamic>.from(response['profile'] as Map);
    _profile = profile;
    _player = response['player'] == null
        ? null
        : Map<String, dynamic>.from(response['player'] as Map);
    _name.text = '${profile['nome'] ?? ''}';
    _surname.text = '${profile['cognome'] ?? ''}';
    _newsletter = response['consents']['newsletter'] == true;
    _marketing = response['consents']['marketing'] == true;
    _tracking = response['consents']['tracking'] == true;
    widget.auth.updateProfile(profile);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.auth.authorized(
        'api/mobile/v1/account.php',
      );
      if (!mounted) return;
      setState(() => _apply(response));
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is ApiError
              ? error.message
              : 'Impossibile caricare il profilo.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Map<String, dynamic> _fields() => {
    'nome': _name.text.trim(),
    'cognome': _surname.text.trim(),
    'password': _password.text,
    'confirm_password': _confirm.text,
    'current_password': _current.text,
    'consenso_newsletter': _newsletter ? 1 : 0,
    'consenso_marketing': _marketing ? 1 : 0,
    'consenso_tracking': _tracking ? 1 : 0,
  };

  Future<void> _save({bool playerPhoto = false, bool revoke = false}) async {
    if (_saving ||
        (!playerPhoto && !revoke && !_form.currentState!.validate())) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
      _message = null;
    });
    try {
      final upload = playerPhoto
          ? _playerPhoto
          : revoke
          ? null
          : _avatar;
      final response = await widget.auth.authorized(
        'api/mobile/v1/account.php',
        body: playerPhoto
            ? {'upload_foto_giocatore': '1'}
            : revoke
            ? {'revoca_consensi': '1'}
            : _fields(),
        uploads: upload == null ? null : [upload],
      );
      if (!mounted) return;
      setState(() {
        _apply(response);
        _message = '${response['message'] ?? 'Modifiche salvate.'}';
        if (playerPhoto) {
          _playerPhoto = null;
        }
        if (!playerPhoto && !revoke) {
          _avatar = null;
          _password.clear();
          _confirm.clear();
          _current.clear();
        }
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is ApiError
              ? error.message
              : 'Salvataggio non riuscito. Riprova.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
        FocusScope.of(context).unfocus();
        if (_scroll.hasClients) {
          await _scroll.animateTo(
            0,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOut,
          );
        }
      }
    }
  }

  Future<void> _pick({bool player = false}) async {
    try {
      final file = await openFile(
        acceptedTypeGroups: [
          XTypeGroup(
            label: 'Foto',
            extensions: ['jpg', 'jpeg', 'png', 'webp', if (!player) 'gif'],
            uniformTypeIdentifiers: [
              'public.jpeg',
              'public.png',
              'org.webmproject.webp',
              if (!player) 'com.compuserve.gif',
            ],
          ),
        ],
      );
      if (file == null) return;
      if (await file.length() > (player ? 50 : 2) * 1024 * 1024) {
        throw ApiError(
          player
              ? 'La foto deve essere inferiore a 50MB.'
              : 'La foto deve essere inferiore a 2MB.',
        );
      }
      final upload = ApiUpload(
        field: player ? 'foto_giocatore' : 'avatar',
        filename: file.name,
        bytes: await file.readAsBytes(),
      );
      if (!mounted) return;
      setState(() {
        if (player) {
          _playerPhoto = upload;
        } else {
          _avatar = upload;
        }
        _error = null;
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is ApiError
              ? error.message
              : 'Impossibile selezionare la foto.',
        );
      }
    }
  }

  Future<void> _open(String path, {String? fragment}) async {
    try {
      final uri = widget.auth.api.uri(path).replace(fragment: fragment);
      if (!await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
        webOnlyWindowName: '_self',
      )) {
        throw const ApiError('Impossibile aprire questa pagina.');
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Impossibile aprire questa pagina. Riprova.');
      }
    }
  }

  Widget _section(String title, IconData icon, List<Widget> children) =>
      Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: const Color(0xffe5eaf1)),
        ),
        child: Material(
          type: MaterialType.transparency,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(icon, size: 20, color: siteRed),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              ...children,
            ],
          ),
        ),
      );

  Widget _photo(String path, ApiUpload? upload, {double size = 64}) =>
      upload == null
      ? SiteImage(
          api: widget.auth.api,
          path: path,
          width: size,
          height: size,
          fallback: Icons.person_outline,
        )
      : ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Image.memory(
            upload.bytes,
            width: size,
            height: size,
            fit: BoxFit.cover,
          ),
        );

  Widget _field(String label, TextEditingController controller) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: controller,
      decoration: InputDecoration(
        labelText: label,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
      validator: (value) =>
          (value ?? '').trim().isEmpty ? 'Campo obbligatorio.' : null,
    ),
  );

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_profile == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error ?? 'Profilo non disponibile.'),
              const SizedBox(height: 16),
              FilledButton(onPressed: _load, child: const Text('Riprova')),
            ],
          ),
        ),
      );
    }
    return Form(
      key: _form,
      child: ListView(
        controller: _scroll,
        padding: const EdgeInsets.all(16),
        children: [
          _section('Il mio account', Icons.account_circle_outlined, [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _photo('${_profile!['avatar'] ?? ''}', _avatar),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_profile!['nome']} ${_profile!['cognome']}',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${_profile!['email']}',
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xff778496),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _saving ? null : () => _pick(),
              icon: const Icon(Icons.add_photo_alternate_outlined),
              label: const Text('Cambia foto profilo'),
            ),
            if (_avatar != null)
              Text(_avatar!.filename, style: const TextStyle(fontSize: 12)),
            const Text(
              'JPG, PNG, GIF o WEBP · massimo 2MB',
              style: TextStyle(fontSize: 12, color: Color(0xff778496)),
            ),
          ]),
          if (_error != null || _message != null)
            Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: _error != null
                    ? const Color(0xffffe5e5)
                    : const Color(0xffe8f5ee),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Text(
                _error ?? _message!,
                style: TextStyle(
                  color: _error != null ? siteRed : const Color(0xff16734a),
                ),
              ),
            ),
          _section('Dati personali', Icons.badge_outlined, [
            _field('Nome', _name),
            _field('Cognome', _surname),
            TextFormField(
              initialValue: '${_profile!['email']}',
              readOnly: true,
              decoration: InputDecoration(
                labelText: 'Email',
                suffixIcon: const Icon(Icons.lock_outline, size: 18),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ]),
          _section('Sicurezza', Icons.lock_outline, [
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: const Text('Cambia password'),
              children: [
                const Padding(
                  padding: EdgeInsets.only(bottom: 16),
                  child: Text(
                    'Almeno 8 caratteri, una maiuscola, un numero e un simbolo.',
                  ),
                ),
                for (final field in [
                  ('Password attuale', _current),
                  ('Nuova password', _password),
                  ('Conferma password', _confirm),
                ])
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: TextFormField(
                      controller: field.$2,
                      obscureText: true,
                      enableSuggestions: false,
                      autocorrect: false,
                      decoration: InputDecoration(
                        labelText: field.$1,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      validator: (value) {
                        if (_password.text.isEmpty) return null;
                        if (field.$2 == _current && (value ?? '').isEmpty) {
                          return 'Inserisci la password attuale.';
                        }
                        if (field.$2 == _confirm && value != _password.text) {
                          return 'Le password non coincidono.';
                        }
                        return null;
                      },
                    ),
                  ),
              ],
            ),
          ]),
          _section('Consensi e preferenze', Icons.tune_rounded, [
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Newsletter'),
              subtitle: const Text('Novità e calendari dei tornei'),
              value: _newsletter,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _newsletter = value),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Comunicazioni promozionali'),
              value: _marketing,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _marketing = value),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Tracciamento utilizzo'),
              subtitle: const Text('Per migliorare i servizi'),
              value: _tracking,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _tracking = value),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _saving ? null : () => _save(),
              icon: const Icon(Icons.check_rounded),
              label: Text(_saving ? 'Salvataggio…' : 'Salva modifiche'),
            ),
            TextButton(
              onPressed: _saving ? null : () => _save(revoke: true),
              child: const Text('Revoca tutti i consensi facoltativi'),
            ),
          ]),
          _section('Foto giocatore', Icons.sports_soccer_rounded, [
            if (_player == null)
              const Text(
                'Non hai ancora un profilo giocatore collegato. Contatta un amministratore.',
              )
            else ...[
              Row(
                children: [
                  _photo('${_player!['foto'] ?? ''}', _playerPhoto),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      '${_player!['nome']} ${_player!['cognome']}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              OutlinedButton.icon(
                onPressed: _saving ? null : () => _pick(player: true),
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: const Text('Scegli foto giocatore'),
              ),
              const Text(
                'JPG, PNG o WEBP · fino a 50MB, compressa sotto 6MB',
                style: TextStyle(fontSize: 12, color: Color(0xff778496)),
              ),
              if (_playerPhoto != null) ...[
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _saving ? null : () => _save(playerPhoto: true),
                  child: const Text('Aggiorna foto giocatore'),
                ),
              ],
            ],
          ]),
          _section(
            'Notifiche push sul telefono',
            Icons.notifications_outlined,
            [
              const Text(
                'Gestisci le notifiche del sito per risultati e risposte ai commenti.',
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () =>
                    _open('account.php', fragment: 'pushSettingsPanel'),
                child: const Text('Gestisci notifiche sul sito'),
              ),
            ],
          ),
          _section('Fanta Old School', Icons.emoji_events_outlined, [
            const Text(
              'Accedi al referral personale e controlla gli inviti dalla tua area Fanta.',
            ),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => _open('fantaoldschool'),
              child: const Text('Apri Fanta Old School'),
            ),
          ]),
          _section('Gestione account', Icons.manage_accounts_outlined, [
            OutlinedButton.icon(
              onPressed: _saving ? null : widget.auth.logout,
              icon: const Icon(Icons.logout),
              label: const Text('Esci'),
            ),
            const SizedBox(height: 10),
            TextButton(
              onPressed: () => _open('account_delete.php'),
              child: const Text(
                'Vai alla cancellazione account',
                style: TextStyle(color: siteRed),
              ),
            ),
          ]),
        ],
      ),
    );
  }
}
