import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

final homeRouteObserver = RouteObserver<ModalRoute<dynamic>>();

/// A bounded snapshot per account. Network failures never replace good data.
class HomeCache {
  HomeCache({this.read, this.write, this.remove});
  final Future<String?> Function()? read;
  final Future<void> Function(String)? write;
  final Future<void> Function()? remove;
  final _values = <String, dynamic>{};
  final _pending = <String, Future<dynamic>>{};
  Future<void>? _hydrating;
  Future<void> _writing = Future.value();
  int _generation = 0;
  dynamic peek(String key) => _values[key];

  Future<void> hydrate() => _hydrating ??= _hydrate();
  Future<void> _hydrate() async {
    final generation = _generation;
    try {
      final saved = await read?.call();
      if (saved == null || generation != _generation) return;
      final data = jsonDecode(saved) as Map;
      final time = DateTime.tryParse('${data['saved']}');
      if (time == null ||
          DateTime.now().difference(time) > const Duration(days: 1)) {
        return;
      }
      for (final entry in (data['values'] as Map).entries) {
        _values.putIfAbsent('${entry.key}', () => entry.value);
      }
    } catch (_) {
      /* Cache is optional, including on unsupported platforms. */
    }
  }

  Future<dynamic> refresh(String key, Future<dynamic> Function() load) {
    if (_pending.containsKey(key)) return _pending[key]!;
    final generation = _generation;
    late Future<dynamic> future;
    future = Future.sync(load)
        .then((value) async {
          if (generation == _generation) {
            _values.remove(key);
            _values[key] = value;
            while (_values.length > 40) {
              _values.remove(_values.keys.first);
            }
            final encoded = jsonEncode({
              'saved': DateTime.now().toIso8601String(),
              'values': _values,
            });
            _writing = _writing.then((_) async {
              if (generation == _generation) {
                try {
                  await write?.call(encoded);
                } catch (_) {}
              }
            });
          }
          return value;
        })
        .whenComplete(() {
          if (identical(_pending[key], future)) _pending.remove(key);
        });
    _pending[key] = future;
    return future;
  }

  Future<void> clear() async {
    _generation++;
    _values.clear();
    _pending.clear();
    await _writing;
    try {
      await remove?.call();
    } catch (_) {}
  }
}

class CachedHomeData extends StatefulWidget {
  const CachedHomeData({
    super.key,
    required this.cache,
    required this.cacheKey,
    required this.load,
    required this.builder,
    this.revision = 0,
  });
  final HomeCache cache;
  final String cacheKey;
  final Object revision;
  final Future<dynamic> Function() load;
  final Widget Function(BuildContext, AsyncSnapshot<dynamic>) builder;
  @override
  State<CachedHomeData> createState() => _CachedHomeDataState();
}

class _CachedHomeDataState extends State<CachedHomeData>
    with WidgetsBindingObserver, RouteAware {
  dynamic _value;
  Object? _error;
  bool _loading = true;
  int _request = 0;
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _value = widget.cache.peek(widget.cacheKey);
    WidgetsBinding.instance.addObserver(this);
    _refresh(hydrate: true);
    _timer = Timer.periodic(const Duration(minutes: 1), (_) {
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed &&
          (ModalRoute.of(context)?.isCurrent ?? true)) {
        _refresh();
      }
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final route = ModalRoute.of(context);
    if (route != null) homeRouteObserver.subscribe(this, route);
  }

  @override
  void didPopNext() {
    _refresh();
  }

  @override
  void didUpdateWidget(CachedHomeData oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.cache != widget.cache ||
        oldWidget.cacheKey != widget.cacheKey) {
      _value = widget.cache.peek(widget.cacheKey);
      _error = null;
      _refresh(hydrate: true);
    } else if (oldWidget.revision != widget.revision) {
      _refresh();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        (ModalRoute.of(context)?.isCurrent ?? true)) {
      _refresh();
    }
  }

  Future<void> _refresh({bool hydrate = false}) async {
    final request = ++_request, cache = widget.cache, key = widget.cacheKey;
    if (hydrate) {
      await cache.hydrate();
      if (!mounted || request != _request) return;
      setState(() {
        _value = cache.peek(key);
      });
    }
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final value = await cache.refresh(key, widget.load);
      if (!mounted || request != _request) return;
      setState(() {
        _value = value;
        _loading = false;
      });
    } catch (error) {
      if (!mounted || request != _request) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _request++;
    _timer?.cancel();
    homeRouteObserver.unsubscribe(this);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final snapshot = _value != null
        ? AsyncSnapshot<dynamic>.withData(
            _loading ? ConnectionState.waiting : ConnectionState.done,
            _value,
          )
        : _error != null
        ? AsyncSnapshot<dynamic>.withError(ConnectionState.done, _error!)
        : const AsyncSnapshot<dynamic>.waiting();
    final content = widget.builder(context, snapshot);
    return _error != null && _value != null
        ? Tooltip(
            message: 'Dati salvati · aggiornamento non disponibile',
            child: content,
          )
        : content;
  }
}
