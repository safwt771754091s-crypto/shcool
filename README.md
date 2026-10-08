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
| `admin@school-platform.local` | `password` | مدير المنصة (Super Admin) |
| `manager@school-platform.local` | `password` | مدير مدرسة |

---

## الاختبارات

```bash
cd backend
php artisan test
```

تُشغَّل على SQLite في الذاكرة، وتغطّي: العزل بين المستأجرين، المصادقة، 2FA، الأدوار والصلاحيات، سجلّ التدقيق، ولوحة التصنيف.

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

## الخطوات التالية

- **المرحلة الثانية:** الطلاب، المعلمون، الفصول، الحضور، الدرجات.
- **المرحلة الثالثة:** APIs تطبيق Flutter ومزامنة العمل دون إنترنت.
- **المرحلة الرابعة:** الإشعارات (SMS/WhatsApp)، التقارير، البوابات الخارجية.
