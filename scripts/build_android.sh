#!/usr/bin/env bash
#
# Build a release Android APK for the School Digital Platform.
#
# Installs the toolchain on first run (idempotent):
#   * OpenJDK 21 (apt)
#   * Android command-line tools + platform/build-tools/NDK (into $ANDROID_HOME)
#
# Usage:
#   API_PUBLIC=https://host ./scripts/build_android.sh
#
# Output: mobile/build/app/outputs/flutter-apk/app-release.apk
set -eu

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE="$REPO_DIR/mobile"
FLUTTER_BIN="${FLUTTER_BIN:-/workspace/tools/flutter/bin}"
ANDROID_HOME="${ANDROID_HOME:-/workspace/tools/android-sdk}"
CMDLINE_VERSION="${CMDLINE_VERSION:-13114758}"

# Derive the public API host from the runtime URL when not given.
if [ -z "${API_PUBLIC:-}" ] && [ -n "${RUNTIME_URL:-}" ]; then
  RUNTIME_ID="$(printf '%s' "$RUNTIME_URL" | sed -E 's#^https?://##; s#\.prod-runtime\.all-hands\.dev.*$##')"
  [ -n "$RUNTIME_ID" ] && API_PUBLIC="https://work-1-${RUNTIME_ID}.prod-runtime.all-hands.dev"
fi
API_PUBLIC="${API_PUBLIC:-https://work-1-qgkkdtekkcsslwlv.prod-runtime.all-hands.dev}"

echo "==> Ensuring JDK 21"
if ! command -v java >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  $SUDO apt-get update -qq
  $SUDO apt-get install -y -qq openjdk-21-jdk-headless unzip
fi
export JAVA_HOME="${JAVA_HOME:-/usr/lib/jvm/java-21-openjdk-amd64}"

echo "==> Ensuring Android SDK at $ANDROID_HOME"
SDKMANAGER="$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager"
if [ ! -x "$SDKMANAGER" ]; then
  mkdir -p "$ANDROID_HOME/cmdline-tools"
  curl -fsSL -o /tmp/cmdline-tools.zip \
    "https://dl.google.com/android/repository/commandlinetools-linux-${CMDLINE_VERSION}_latest.zip"
  unzip -q -o /tmp/cmdline-tools.zip -d "$ANDROID_HOME/cmdline-tools"
  rm -rf "$ANDROID_HOME/cmdline-tools/latest"
  mv "$ANDROID_HOME/cmdline-tools/cmdline-tools" "$ANDROID_HOME/cmdline-tools/latest"
fi
export ANDROID_HOME ANDROID_SDK_ROOT="$ANDROID_HOME"
yes | "$SDKMANAGER" --licenses >/dev/null 2>&1 || true
"$SDKMANAGER" --install "platform-tools" "platforms;android-36" "build-tools;36.0.0" >/dev/null

export PATH="$FLUTTER_BIN:$PATH"
flutter config --android-sdk "$ANDROID_HOME" >/dev/null 2>&1 || true

echo "==> Building release APK (API_BASE_URL=$API_PUBLIC/api/v1)"
(cd "$MOBILE" && flutter pub get >/dev/null && \
  flutter build apk --release --dart-define=API_BASE_URL="$API_PUBLIC/api/v1")

APK="$MOBILE/build/app/outputs/flutter-apk/app-release.apk"
echo
echo "APK: $APK"
ls -lh "$APK"
