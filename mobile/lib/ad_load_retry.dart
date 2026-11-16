import 'dart:async';

/// Retries failed requests only; never refreshes an ad that has loaded.
class AdLoadRetry {
  static const delays = [
    Duration(seconds: 5),
    Duration(seconds: 15),
    Duration(seconds: 45),
  ];
  Timer? _timer;
  int _attempt = 0;

  void schedule(void Function() load) {
    if (_timer != null || _attempt >= delays.length) return;
    _timer = Timer(delays[_attempt++], () {
      _timer = null;
      load();
    });
  }

  void reset() {
    _timer?.cancel();
    _timer = null;
    _attempt = 0;
  }

  void dispose() => reset();
}
