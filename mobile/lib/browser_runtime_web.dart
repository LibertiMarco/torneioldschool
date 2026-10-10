import 'package:web/web.dart' as web;

import 'auth.dart';
import 'browser_auth.dart';

TosAuth createBrowserAuth() => BrowserAuth(
  BrowserApi(baseUrl: Uri.parse(Uri.base.origin)),
  navigate: (url) => web.window.location.assign(url),
);
