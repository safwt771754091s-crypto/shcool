import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/pwa/install_prompt.dart'
    if (dart.library.js_interop) '../core/pwa/install_prompt_web.dart';

const _seenKey = 'install_prompt_seen_v1';

/// AppBar action (or login-screen entry) that opens the "install the app"
/// instructions.
///
/// Renders nothing when the app is already installed, or when the current
/// browser cannot install it (e.g. desktop Firefox) — so it never shows a dead
/// button.
class InstallAppButton extends StatelessWidget {
  const InstallAppButton({super.key}) : _asEntry = false;

  /// Full-width labelled button, for the login screen.
  const InstallAppButton.entry({super.key}) : _asEntry = true;

  final bool _asEntry;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: InstallPrompt.instance,
      builder: (context, _) {
        final p = InstallPrompt.instance;
        if (p.isInstalled || !(p.canInstall || p.isIosManual)) {
          return const SizedBox.shrink();
        }
        if (_asEntry) {
          return OutlinedButton.icon(
            onPressed: () => showInstallSheet(context),
            icon: const Icon(Icons.install_mobile_rounded),
            label: const Text('تثبيت التطبيق على هاتفك'),
          );
        }
        return IconButton(
          tooltip: 'تثبيت التطبيق',
          icon: const Icon(Icons.install_mobile_rounded),
          onPressed: () => showInstallSheet(context),
        );
      },
    );
  }
}

/// The install sheet: describes the app and offers the install action.
Future<void> showInstallSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => const _InstallSheet(),
  );
}

/// Shows the install popup once per device, shortly after a visitor arrives.
///
/// Triggered from the login screen. Persists a flag so it never nags twice.
Future<void> maybeShowInstallOnFirstVisit(BuildContext context) async {
  final p = InstallPrompt.instance;
  if (p.isInstalled) return;

  final prefs = await SharedPreferences.getInstance();
  if (prefs.getBool(_seenKey) ?? false) return;

  // Wait briefly for `beforeinstallprompt` (Chromium fires it after load).
  for (var i = 0; i < 8 && !p.canInstall && !p.isIosManual; i++) {
    await Future.delayed(const Duration(milliseconds: 500));
    if (!context.mounted) return;
  }
  if (!context.mounted) return;
  if (!p.canInstall && !p.isIosManual) return;

  await prefs.setBool(_seenKey, true);
  if (!context.mounted) return;
  await showInstallSheet(context);
}

class _InstallSheet extends StatefulWidget {
  const _InstallSheet();

  @override
  State<_InstallSheet> createState() => _InstallSheetState();
}

class _InstallSheetState extends State<_InstallSheet> {
  bool _busy = false;

  Future<void> _install() async {
    setState(() => _busy = true);
    final result = await InstallPrompt.instance.prompt();
    if (!mounted) return;
    setState(() => _busy = false);
    Navigator.of(context).maybePop();
    if (result == 'accepted') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تثبيت التطبيق على جهازك ✅')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = InstallPrompt.instance;
    final theme = Theme.of(context);
    final ios = p.isIosManual;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 4, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(Icons.install_mobile_rounded,
                size: 56, color: theme.colorScheme.primary),
            const SizedBox(height: 12),
            Text('ثبّت «منصة المدرسة الرقمية»',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(
              'يمكنك تنزيل الموقع كتطبيق على هاتفك: أيقونة على الشاشة الرئيسية، '
              'وتشغيل بملء الشاشة دون متصفح، ووصول أسرع في كل مرة.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 20),
            if (ios) ...[
              _Step('١', 'افتح قائمة المشاركة (أيقونة المربّع مع السهم) في Safari.'),
              _Step('٢', 'اختر «إضافة إلى الشاشة الرئيسية».'),
              _Step('٣', 'اضغط «إضافة» ليظهر التطبيق بين تطبيقاتك.'),
            ] else
              FilledButton.icon(
                onPressed: _busy ? null : _install,
                icon: _busy
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.download_rounded),
                label: const Text('تثبيت التطبيق الآن'),
              ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Navigator.of(context).maybePop(),
              child: const Text('لاحقاً'),
            ),
          ],
        ),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step(this.number, this.text);

  final String number;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 13,
            backgroundColor: scheme.primaryContainer,
            child: Text(number,
                style: TextStyle(
                    fontSize: 12, color: scheme.onPrimaryContainer)),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}
