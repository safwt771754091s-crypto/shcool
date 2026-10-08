# منصة المدرسة الرقمية (School Digital Platform)

منصة وطنية لإدارة المدارس: الويب وتطبيق أندرويد يستهلكان واجهة برمجية واحدة.

- **الباك اند:** Laravel (API) + MySQL + Redis + Laravel Sanctum
- **الواجهات:** Flutter (ويب + جوال) تستهلك نفس الـ API
- **العزل:** Multi-tenant عبر `tenant_id` مع Global Scopes — كل مدرسة معزولة تماماً

---

## الهيكل الإداري

```
الوزارة ← المحافظة ← المديرية ← المدرسة ← الفرع
```

النموذج مُوحَّد في جدول `organizations` واحد، و`type` يحدّد المستوى:

| النوع | المستوى | نطاق المستأجر |
|------|---------|----------------|
| `ministry` | 1 | عام (بدون tenant) |
| `governorate` | 2 | عام |
| `directorate` | 3 | عام |
| `school` | 4 | **حدّ المستأجر** (`tenant_id` = معرّف المدرسة) |
| `branch` | 5 | يرث مستأجر مدرسته |

المدرسة هي دائماً حدّ العزل: أي صف تابع لها يحمل `tenant_id` مساوياً لمعرّفها، والفروع تشترك في نفس المستأجر.

---

## المرحلة الأولى (مكتملة)

- الهيكل الإداري وقاعدة البيانات والعزل (`app/Support/Tenancy`).
- نظام الأدوار والصلاحيات (RBAC) مبنيّ على `spatie/laravel-permission` بوضع الفرق (teams).
- المصادقة عبر Sanctum + التحقق بخطوتين (2FA/TOTP) + سجلّ تدقيق (Audit Logs).
- وحدة التنافس والتصنيف (Competition & Ranking) مع تراكم هرمي للنتائج.

### الحزم الأساسية

| الحزمة | الغرض |
|--------|-------|
| `laravel/sanctum` | مصادقة API بالرموز (Tokens) |
| `spatie/laravel-permission` | الأدوار والصلاحيات بوضع الفرق (`teams = true`) |
| `pragmarx/google2fa` | نواة TOTP للتحقق بخطوتين |
| `bacon/bacon-qr-code` | توليد رمز QR لتسجيل 2FA |

اختيار `spatie/laravel-permission`: يدعم وضع **teams** أصلاً عبر `team_foreign_key`، لذا ربطناه بـ `tenant_id` مباشرة عبر `TenantTeamResolver`. بهذا يصبح الدور «مدرّس» في مدرسة أ كياناً مستقلاً عن «مدرّس» في مدرسة ب، دون أي كود إضافي.

---

## الإعداد

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# قاعدة البيانات مستقلة تماماً عن أي مشروع آخر
php artisan migrate:fresh --seed
php artisan serve
```

قاعدة البيانات المستخدمة: `school_platform` بمستخدم `school` مخصّص للمشروع — لا تشارك أي جدول مع مشروع آخر.

### حسابات البذور

| الحساب | كلمة المرور | الدور |
|--------|-------------|-------|
| `owner@school-platform.local` | `password` | مالك المنصة (Owner) |
| `admin@school-platform.local` | `password` | مدير المنصة (Super Admin) |
| `manager@school-platform.local` | `password` | مدير مدرسة |

---

## الاختبارات

```bash
cd backend
php artisan test
```

تُشغَّل على SQLite في الذاكرة (42 اختباراً، 115 تأكيداً)، وتغطّي: العزل بين المستأجرين، المصادقة، 2FA، الأدوار والصلاحيات، سجلّ التدقيق، لوحة التصنيف، كراسة التحضير، الدوري الرياضي، الأنشطة التفاعلية، وسجلّ التطبيقات.

---

## واجهات المرحلة الأولى

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `POST` | `/api/v1/auth/login` | تسجيل الدخول |
| `POST` | `/api/v1/auth/2fa/challenge` | إتمام تحدي التحقق بخطوتين |
| `GET` | `/api/v1/auth/me` | الملف الشخصي |
| `POST` | `/api/v1/auth/logout` | تسجيل الخروج |
| `POST` | `/api/v1/auth/2fa/enable` \| `confirm` \| `disable` | إدارة 2FA |
| `GET` | `/api/v1/organizations` \| `/tree` \| `/{id}` | الهيكل الإداري |
| `POST` | `/api/v1/organizations` | إنشاء جهة |
| `PUT` | `/api/v1/organizations/{id}` | تعديل جهة |
| `GET` | `/api/v1/ranking-periods/{period}/leaderboard` | لوحة التصنيف |
| `GET` | `/api/v1/ranking-periods/{period}/my-position` | ترتيبي |
| `POST` | `/api/v1/ranking-periods/{period}/recompute` | إعادة حساب التصنيف |

---

## وحدات التنافس والتصنيف

يقيس النظام «التميّز» عبر مقاييس موزونة (`competition_metrics`) مثل المعدل الدراسي ونسبة الحضور والسلوك، ثم:

1. تُرصد الدرجات لكل طالب (`competition_scores`).
2. يحسب `ScoreCalculator` النقاط الموزونة.
3. يراكم `LeaderboardService` النتائج **هرمياً**:
   طالب ← شعبة ← مدرسة ← مديرية ← محافظة ← وزارة.
4. تُحفظ الترتيبات في `leaderboard_entries` مع `rank_delta` لإظهار الصعود والهبوط بين الدورات.
5. تُمنح الأوسمة (`achievements`) تلقائياً لأصحاب المراكز الأولى.

بهذا تخلق المنصة تنافساً حقيقياً بين الطلاب والصفوف والمدارس ومكاتب التربية حتى مستوى الوزارة.

---

## المرحلة الثانية (الهيكل الأكاديمي والتدريس والتنافس الموسّع)

### الهيكل الأكاديمي والتدريس

- `academic_years` + `terms` — السنوات والفصول الدراسية، مع `is_current`.
- `subjects` — المواد.
- `school_classes` + `class_sections` — الصفوف والشعب (مع سعة القاعة والمعلّم المسؤول).
- `curriculum_units` + `lessons` — شجرة المنهج (وحدة ← درس).
- `lesson_preparations` — **كراسة تحضير المعلمين** مع دورة حياة كاملة:
  `draft` ← `submitted` ← (`approved` | `returned`)، وسجلّ من راجع التحضير ومتى.
- `assignments` — الواجبات.

### الدوري الرياضي (Sports League)

محرّك بطولات تصفيات منفردة يصعد **سلّم المنافسة**:

```
شعب ← صفوف ← مدارس ← مديريات (مراكز التربية) ← محافظات ← نهائي الوزارة
```

- يُولَّد جدول التصفيات تلقائياً لكل رتبة (`SportsLeagueService::generateBracket`).
- عدد الفرق الفردي يُعالَج بمنح أحد الفريقين «تأهيلاً مباشراً» (bye).
- تسجيل النتيجة (`recordResult`) يدفع الفائز تلقائياً إلى مباراة الجولة التالية.
- `promoteWinners` يرقّي فائزي الرتبة الحالية إلى الرتبة التالية حتى النهائي الوطني.

### الأنشطة التفاعلية (للابتدائي)

- `interactive_activities` + `activity_questions` + `activity_attempts`.
- المجالات: `english` / `math` / `activities`.
- التصحيح يجري **على الخادم** (`ActivityService`)؛ العميل لا يصحّح ولا يعتمد عليه.

### سجلّ التطبيقات المصغّرة (Mini-Apps)

- `mini_apps` — قوالب التطبيقات، و`mini_app_instances` — نسخة كل جهة.
- لكل مدرسة تطبيقها، والمديرية تجمع مدارسها، والمحافظة تجمع مديرياتها، والوزارة تجمع الجميع.
- المدرسة **ترث** تلقائياً تطبيقات أسلافها (`MiniAppService::availableFor`).

### الأدوار الجديدة

- **Owner (مالك المنصة)** — أعلى سلطة على المنتج نفسه، منفصل عن مدير المنصة.
- صلاحيات جديدة: `curriculum.*`, `teaching.*`, `assignments.*`, `sports.*`, `activities.*`, `apps.*`, `platform.*`.

### واجهات المرحلة الثانية

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET/POST` | `/api/v1/academic/years` | السنوات الدراسية |
| `POST` | `/api/v1/academic/years/{year}/terms` | الفصول الدراسية |
| `GET/POST` | `/api/v1/academic/subjects` | المواد |
| `GET/POST` | `/api/v1/academic/classes` | الصفوف |
| `POST` | `/api/v1/academic/classes/{class}/sections` | الشعب |
| `GET/POST` | `/api/v1/academic/units` | وحدات المنهج |
| `POST` | `/api/v1/academic/units/{unit}/lessons` | الدروس |
| `GET/POST` | `/api/v1/teaching/preparations` | كراسة التحضير |
| `POST` | `/api/v1/teaching/preparations/{id}/submit` \| `review` | الإرسال والمراجعة |
| `GET/POST` | `/api/v1/sports/competitions` | الدوريات |
| `POST` | `/api/v1/sports/competitions/{id}/participants` | تسجيل فريق |
| `POST` | `/api/v1/sports/competitions/{id}/bracket` \| `advance` | توليد الجدول والترقية |
| `POST` | `/api/v1/sports/matches/{id}/result` | تسجيل نتيجة |
| `GET/POST` | `/api/v1/activities` | الأنشطة التفاعلية |
| `POST` | `/api/v1/activities/{id}/submit` | إرسال إجابات الطالب |
| `GET` | `/api/v1/activities/my-results` | نتائجي |
| `GET/POST` | `/api/v1/apps` | سجلّ التطبيقات |
| `POST` | `/api/v1/apps/{id}/publish` | نشر تطبيق على جهة |
| `GET` | `/api/v1/apps/available` | تطبيقاتي المتاحة |

---

## الخطوات التالية

- **المرحلة الثالثة:** APIs تطبيق Flutter ومزامنة العمل دون إنترنت (Offline Sync).
- **المرحلة الرابعة:** الإشعارات (SMS/WhatsApp)، التقارير، البوابات الخارجية.
