import 'package:flutter/material.dart';

import 'access_gate.dart';
import 'api.dart';
import 'sport_controls.dart';

class CommentsSection extends StatefulWidget {
  const CommentsSection({super.key, required this.api, required this.postId});
  final TosApi api;
  final String postId;
  @override
  State<CommentsSection> createState() => _CommentsSectionState();
}

class _CommentsSectionState extends State<CommentsSection> {
  final _text = TextEditingController();
  List<Map<String, dynamic>> _rows = [];
  int _page = 1, _revision = 0;
  bool _loading = true, _saving = false, _more = false;
  String? _error;
  Object? _identity;
  static const path = 'api/mobile/v1/comments.php';
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final identity = AppAuthScope.of(context)?.user?['id'];
    if (_loading || identity != _identity) {
      _identity = identity;
      _load(1);
    }
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  String message(Object error) =>
      error is ApiError ? error.message : 'Commenti non disponibili. Riprova.';
  Future<void> _load(int page) async {
    final revision = ++_revision;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final auth = AppAuthScope.of(context);
      final query = {'id': widget.postId, 'page': '$page'};
      final result = auth?.user == null
          ? await widget.api.request(path, query: query)
          : await auth!.authorized(path, query: query);
      if (!mounted || revision != _revision) return;
      setState(() {
        _rows = TosApi.rows(result['data']);
        _page = page;
        _more = (result['pagination'] as Map?)?['has_more'] == true;
      });
    } catch (error) {
      if (mounted && revision == _revision) {
        setState(() => _error = message(error));
      }
    } finally {
      if (mounted && revision == _revision) setState(() => _loading = false);
    }
  }

  Future<void> _write(String operation, Map<String, dynamic> body) async {
    if (_saving) return;
    final auth = AppAuthScope.of(context);
    if (auth?.user == null) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await auth!.authorized(path, query: {'operation': operation}, body: body);
      if (!mounted) return;
      if (operation == 'create') _text.clear();
      await _load(operation == 'create' ? 1 : _page);
    } catch (error) {
      if (mounted) setState(() => _error = message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final approved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Elimina commento'),
        content: const Text('Confermi l’eliminazione del commento?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annulla'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Elimina'),
          ),
        ],
      ),
    );
    if (approved == true) {
      await _write('delete', {'id': int.parse('${row['id']}')});
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = AppAuthScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 24),
        Text('Commenti', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
        if (_loading) const LinearProgressIndicator(),
        if (!_loading && _rows.isEmpty && _error == null)
          const Text('Nessun commento. Inizia la conversazione.'),
        for (final row in _rows)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${row['autore'] ?? 'Utente'}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                      if (row['can_delete'] == true)
                        IconButton(
                          tooltip: 'Elimina commento',
                          onPressed: _saving ? null : () => _delete(row),
                          icon: const Icon(Icons.delete_outline),
                        ),
                    ],
                  ),
                  Text(
                    '${row['data'] ?? ''}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (row['parent_id'] != null)
                    const Text(
                      'Risposta a un commento',
                      style: TextStyle(fontSize: 11),
                    ),
                  const SizedBox(height: 8),
                  SelectableText('${row['commento'] ?? ''}'),
                ],
              ),
            ),
          ),
        if (_page > 1 || _more)
          SportPagination(
            page: _page,
            previous: _loading || _page <= 1 ? null : () => _load(_page - 1),
            next: _loading || !_more ? null : () => _load(_page + 1),
          ),
        if (_error != null) ...[
          Semantics(liveRegion: true, child: Text(_error!)),
          TextButton(
            onPressed: _loading ? null : () => _load(_page),
            child: const Text('Riprova'),
          ),
        ],
        const SizedBox(height: 12),
        if (auth?.user == null)
          TextButton.icon(
            onPressed: auth == null
                ? null
                : () async {
                    await auth.login();
                    if (mounted) _load(_page);
                  },
            icon: const Icon(Icons.login),
            label: const Text('Accedi per commentare'),
          )
        else ...[
          TextField(
            controller: _text,
            enabled: !_saving,
            maxLength: 1200,
            minLines: 2,
            maxLines: 5,
            decoration: const InputDecoration(labelText: 'Scrivi un commento'),
          ),
          FilledButton.icon(
            onPressed: _saving
                ? null
                : () {
                    if (_text.text.trim().isEmpty) return;
                    _write('create', {
                      'post_id': int.parse(widget.postId),
                      'commento': _text.text.trim(),
                    });
                  },
            icon: const Icon(Icons.send_outlined),
            label: Text(_saving ? 'Invio…' : 'Pubblica commento'),
          ),
        ],
      ],
    );
  }
}
