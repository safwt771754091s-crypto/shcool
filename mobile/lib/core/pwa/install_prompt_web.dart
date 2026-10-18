import 'dart:async';
import 'dart:js_interop';
import 'dart:js_interop_unsafe';

import 'package:flutter/foundation.dart';
import 'package:web/web.dart' as web;

/// Web implementation of the install affordance.
///
/// Chromium fires `beforeinstallprompt` when the app is installable; we capture
/// it, expose it, and call `prompt()` from the UI. iOS/Safari never fires that
/// event, so there we fall back to telling the user the manual Share ▸ "Add to
/// Home Screen" flow.
class InstallPrompt extends ChangeNotifier {
  InstallPrompt._();

  static final InstallPrompt instance = InstallPrompt._();

  JSObject? _deferred;

  /// True once the browser offers an install prompt (Chromium + installable).
  bool get canInstall => _deferred != null;

  /// True when already running as an installed app (standalone window).
  bool get isInstalled {
    try {
      return web.window.matchMedia('(display-mode: standalone)').matches ||
          (web.window.navigator as JSObject)
                  .getProperty('standalone'.toJS)
                  .dartify() ==
              true;
    } catch (_) {
      return false;
    }
  }

  /// iOS/iPadOS Safari: no `beforeinstallprompt`, manual add-to-home-screen.
  bool get isIosManual {
    if (canInstall || isInstalled) return false;
    try {
      final ua = web.window.navigator.userAgent.toLowerCase();
      final touch = web.window.navigator.maxTouchPoints > 1;
      return ua.contains('iphone') ||
          ua.contains('ipad') ||
          ua.contains('ipod') ||
          (ua.contains('macintosh') && touch);
    } catch (_) {
      return false;
    }
  }

  bool _started = false;

  /// Subscribe to the browser install events (idempotent).
  void start() {
    if (_started) return;
    _started = true;
    try {
      web.window.addEventListener(
        'beforeinstallprompt',
        ((web.Event e) {
          e.preventDefault();
          _deferred = e as JSObject;
          notifyListeners();
        }).toJS,
      );
      web.window.addEventListener(
        'appinstalled',
        ((web.Event _) {
          _deferred = null;
          notifyListeners();
        }).toJS,
      );
    } catch (_) {
      // Older browsers: stay silent, the UI simply offers no install button.
    }
  }

  /// Trigger the browser install prompt. Returns `accepted`, `dismissed` or
  /// `unavailable`.
  Future<String> prompt() async {
    final deferred = _deferred;
    if (deferred == null) return 'unavailable';
    _deferred = null;
    notifyListeners();
    try {
      final result =
          await deferred.callMethod<JSPromise<JSObject>>('prompt'.toJS).toDart;
      final choice = result.getProperty('outcome'.toJS).dartify();
      return choice == 'accepted' ? 'accepted' : 'dismissed';
    } catch (_) {
      return 'dismissed';
    }
  }
}
