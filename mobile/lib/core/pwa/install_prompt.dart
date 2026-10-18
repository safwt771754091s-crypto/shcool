import 'package:flutter/foundation.dart';

/// "Install this site as an app" affordance.
///
/// Installing a website as an app is a browser (PWA) capability. On the web
/// build this class is swapped for `install_prompt_web.dart` (see the
/// conditional import in `install_button.dart`). On every other platform the
/// app is already a native install, so this stub reports "nothing to install"
/// and does nothing.
class InstallPrompt extends ChangeNotifier {
  InstallPrompt._();

  static final InstallPrompt instance = InstallPrompt._();

  /// No PWA install flow off the web.
  bool get canInstall => false;

  /// Native builds are already installed.
  bool get isInstalled => true;

  /// iOS manual add-to-home-screen guidance: never applies off the web.
  bool get isIosManual => false;

  /// Begin listening for browser install events (no-op here).
  void start() {}

  /// Trigger the browser install prompt. Returns `accepted`, `dismissed` or
  /// `unavailable`.
  Future<String> prompt() async => 'unavailable';
}
