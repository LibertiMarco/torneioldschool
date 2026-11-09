import 'package:flutter_test/flutter_test.dart';
import 'package:tornei_old_school/api.dart';
import 'package:tornei_old_school/demo.dart';
import 'package:tornei_old_school/request_notification_actions.dart';

class ReviewAuth extends DemoAuth {
  ReviewAuth() : super(DemoApi()) {
    user = {
      'id': 1,
      'permissions': {'admin': true},
    };
  }
  final calls = <Map<String, dynamic>>[];
  bool conflict = false;
  @override
  Future<void> ensureRestored() async {}
  @override
  Future<dynamic> authorized(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    List<ApiUpload>? uploads,
  }) async {
    calls.add({'path': path, ...?body});
    if (conflict) {
      throw const ApiError('La richiesta è già stata gestita.', status: 409);
    }
    return {'message': 'Richiesta gestita.'};
  }
}

void main() {
  test(
    'Action reviews exactly the notified request; body tap only navigates',
    () async {
      final auth = ReviewAuth(), messages = <String>[];
      var opened = 0;
      final actions = RequestNotificationActions(
        auth: auth,
        openRequests: () => opened++,
        showMessage: messages.add,
      );
      await actions.handle({
        'request_id': '12',
        'action': 'accept',
        'recipient_id': '1',
      });
      expect(auth.calls.single, {
        'path': 'api/mobile/v1/player_link_requests.php',
        'action': 'review',
        'request_id': 12,
        'accept': true,
      });
      expect(opened, 0);
      expect(messages.single, 'Richiesta gestita.');
      await actions.handle({'request_id': '12', 'action': 'open'});
      expect(opened, 1);
      expect(auth.calls.length, 1);
      await actions.handle({
        'request_id': '13',
        'action': 'reject',
        'recipient_id': '1',
      });
      expect(auth.calls.last['accept'], false);
    },
  );
  test('Wrong account, non-admin, malformed action and stale request never silently succeed', () async {
    final auth = ReviewAuth(), messages = <String>[];
    var opened = 0;
    final actions = RequestNotificationActions(
      auth: auth,
      openRequests: () => opened++,
      showMessage: messages.add,
    );
    await actions.handle({
      'request_id': '12',
      'action': 'accept',
      'recipient_id': '99',
    });
    expect(auth.calls, isEmpty);
    expect(opened, 1);
    auth.user = {
      'id': 1,
      'permissions': {'admin': false},
    };
    await actions.handle({
      'request_id': '12',
      'action': 'accept',
      'recipient_id': '1',
    });
    expect(auth.calls, isEmpty);
    await actions.handle({'request_id': 'no', 'action': 'accept'});
    await actions.handle({'request_id': '12', 'action': 'delete'});
    expect(auth.calls, isEmpty);
    auth.user = {
      'id': 1,
      'permissions': {'admin': true},
    };
    auth.conflict = true;
    await actions.handle({
      'request_id': '12',
      'action': 'accept',
      'recipient_id': '1',
    });
    expect(messages.last, contains('già stata gestita'));
  });
}
