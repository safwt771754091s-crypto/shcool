# School Digital Platform — Flutter Client (تطبيق المدرسة الرقمية)

Flutter client for the School Digital Platform, targeting **Android** and **Web**.
It consumes the Laravel API under `../backend` (same endpoints, single source of
truth) and adapts its landing screen to the signed-in user's role.

## Requirements

- Flutter 3.47+ / Dart 3.13+
- The backend running and reachable (see `../backend/README.md`).

## Configuration

The API base URL is provided at build/run time:

| Target | Command |
| --- | --- |
| Android emulator | `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` |
| Web / desktop | `flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8000/api/v1` |
| Production | `flutter build web --dart-define=API_BASE_URL=https://api.example.com/api/v1` |

Default (when unset): `http://10.0.2.2:8000/api/v1` (Android emulator loopback).

## Running

```bash
flutter pub get
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8000/api/v1
```

Android build: `flutter build apk` (needs the Android SDK / Java 21).
Web build: `flutter build web`.

## Test & analyze

```bash
flutter analyze
flutter test
```

## Structure

```
lib/
  core/
    config/app_config.dart      # base URL, timeouts, app name
    network/api_client.dart     # Dio wrapper: bearer token, error envelope
    storage/token_store.dart    # Sanctum token + user (flutter_secure_storage)
    storage/offline_store.dart  # durable offline mutation queue
    theme/app_theme.dart        # RTL-friendly Material 3 theme
  models/                       # AppUser, Portal/ChildSummary, Persona
  services/                     # auth, portal, teacher, sync
  state/auth_provider.dart      # ChangeNotifier auth state
  router/app_router.dart        # go_router config
  screens/                      # splash, login, 2FA, home + role dashboards
  widgets/                      # StatCard, AsyncView
```

## Authentication

- `POST auth/login` → full token, or `two_factor_required` with a challenge
  token (2FA). Challenge is completed via `POST auth/2fa/challenge`.
- The token is stored with `flutter_secure_storage` and injected as a bearer
  header on every request.

## Role-based landing

`personaFor(AppUser)` maps the first API role to a screen:

| Role | Screen |
| --- | --- |
| `teacher`, `teacher_assistant` | لوحة المعلم (`TeacherHomeScreen`) |
| `parent` / `guardian` | بوابة ولي الأمر (`ParentPortalScreen`) |
| `student` | بوابة الطالب (`StudentPortalScreen`) |
| anything else | لوحة التحكم (`StaffOverviewScreen`) |

## Offline mode (العمل دون إنترنت)

`SyncService` queues attendance mutations into `OfflineStore`
(`shared_preferences`). `flush()` posts them to `sync/push` as one
`client_batch_id`-keyed batch; the queue is cleared on success. `SyncBanner`
surfaces the pending count and offers a manual sync.

> Deep management screens (students, teachers, finance, reports) are delivered
> on the web dashboard; the mobile app focuses on teacher, parent, student and
> offline flows.
