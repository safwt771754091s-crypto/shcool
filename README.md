# منصة المدرسة الرقمية (School Digital Platform)

منصة وطنية لإدارة المدارس: الويب وتطبيق أندرويد يستهلكان واجهة برمجية واحدة.

- **الباك اند:** Laravel (API) + MySQL + Redis + Laravel Sanctum — في `backend/`
- **الواجهات:** Flutter (ويب + جوال) تستهلك نفس الـ API — في `mobile/`
- **العزل:** Multi-tenant عبر `tenant_id` مع Global Scopes — كل مدرسة معزولة تماماً

> تطبيق Flutter (Android + Web): انظر [`mobile/README.md`](mobile/README.md) — يشمل تسجيل الدخول،
> التحقق بخطوتين، لوحة المعلم، بوابة ولي الأمر، بوابة الطالب، والعمل دون إنترنت (Offline Sync).

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

## المرحلة الثالثة (الطلاب والمعلمون والحضور والدرجات ومزامنة العمل دون إنترنت)

### الطلاب والقبول

- `students` — الملف الشخصي، الشعبة، الرقم الأكاديمي (فريد داخل المدرسة)، والحالة
  (`applicant` / `enrolled` / `graduated` / `withdrawn` / `transferred`).
- `admissions` — طلبات القبول بدورة حياة: `submitted` ← (`accepted` | `rejected`) ← `enrolled`.
- `StudentService` يدير التسجيل، القبول، النقل بين الشعب، وربط أولياء الأمور.

### أولياء الأمور

- `guardians` — بيانات ولي الأمر وربطه بحساب مستخدم.
- جدول ربط `guardian_student` مع نوع العلاقة و`is_primary`.
- بوابة ولي الأمر: `GET /students/my-children` يعيد أبناءه فقط.

### المعلمون والتوزيع

- `teachers` — الملف الوظيفي (الرقم الوظيفي، التخصص، المؤهل، الحالة).
- `teaching_assignments` — توزيع المعلم على (مادة + شعبة + سنة) مع عدد الحصص الأسبوعية.
- بوابة المعلم: `GET /teachers/my-assignments`.

### الحضور والغياب

- `attendance_sessions` (شعبة + تاريخ + فترة) و`attendances` (صف لكل طالب).
- `AttendanceService`:
  - `takeRegister` — أخذ الحضور (upsert، لا تكرار عند إعادة التسجيل).
  - `sectionReport` — ملخّص لكل طالب (حضور/غياب/تأخير/إذن + النسبة).
  - `absenceAlerts` — الطلاب الذين تجاوز غيابهم حدّاً معيّناً.

### الاختبارات والدرجات وكشوف النتائج

- `exams` — الاختبارات (يومي/شهري/نصفي/نهائي/قصير) مع الدرجة العظمى والنجاح والوزن.
- `grades` — الدرجات (صف لكل طالب/اختبار) مع دعم الغياب.
- `GradeService`:
  - `record` / `recordMany` — رصد فردي أو جماعي مع التحقق من الدرجة العظمى.
  - `resultSheet` — كشف نتائج الطالب (معدل موزون لكل مادة + المعدل العام).
  - `sectionResultSheet` — كشف الشعبة مرتّباً بالرتبة.

### مزامنة العمل دون إنترنت (Offline Sync)

- `sync_batches` — دفعة تغييرات من الجهاز (`client_batch_id` فريد).
- `POST /api/v1/sync/push` يعيد تشغيل دفعة تغييرات حصلت دون اتصال.
- **متكرّر الأمان (Idempotent):** إعادة إرسال نفس `client_batch_id` تُرجع النتيجة المخزّنة دون تطبيق مرّتين.
- كل عنصر يُطبَّق داخل نقطة حفظ مستقلة؛ فشل عنصر لا يُلغي بقية الدفعة (تُصنَّف الدفعة `partial`).
- الأنواع المدعومة حالياً: `attendance.register`, `grade.record`.

### واجهات المرحلة الثالثة

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET/POST` | `/api/v1/students` | الطلاب |
| `GET/PUT` | `/api/v1/students/{student}` | ملف الطالب |
| `POST` | `/api/v1/students/{student}/transfer` \| `status` | النقل وتغيير الحالة |
| `GET` | `/api/v1/students/my-children` | أبناء ولي الأمر |
| `GET/POST` | `/api/v1/admissions` | طلبات القبول |
| `POST` | `/api/v1/admissions/{id}/decide` \| `enrol` | القرار والتسجيل |
| `GET/POST` | `/api/v1/guardians` | أولياء الأمور |
| `POST` | `/api/v1/guardians/{id}/link` | ربط ولي الأمر بطالب |
| `GET/POST` | `/api/v1/teachers` | المعلمون |
| `POST` | `/api/v1/teachers/{id}/assign` | التوزيع على مادة/شعبة |
| `GET` | `/api/v1/teachers/my-assignments` | مهام المعلم |
| `POST` | `/api/v1/attendance/register` | أخذ الحضور |
| `GET` | `/api/v1/attendance/session` | سجلّ الحضور ليوم |
| `GET` | `/api/v1/attendance/report` | تقرير الحضور |
| `GET` | `/api/v1/attendance/absence-alerts` | تنبيهات الغياب |
| `GET/POST` | `/api/v1/exams` | الاختبارات |
| `POST` | `/api/v1/exams/{id}/grades` \| `publish` | رصد ونشر الدرجات |
| `GET` | `/api/v1/exams/result-sheet` | كشف نتائج الشعبة |
| `GET` | `/api/v1/students/{id}/result-sheet` | كشف نتائج طالب |
| `GET` | `/api/v1/exams/my-result` | كشف نتائج الطالب الحالي |
| `POST` | `/api/v1/sync/push` | مزامنة تغييرات العمل دون إنترنت |

---

## المرحلة الرابعة (الإشعارات والتقارير والتصدير والبوابات الخارجية)

### الإشعارات (SMS / WhatsApp / داخل التطبيق)

- قنوات قابلة للتوسعة عبر عقد `NotificationChannel`: `sms`، `whatsapp`، `in_app`.
- `NotificationChannelManager` يكتشف القناة **متاحة** فقط عند تهيئة إعداداتها؛
  غياب المفاتيح لا يُعطّل المنصة بل يُخفي الخيار (نفس نمط بقية الوحدات).
- `NotificationTemplate` — قوالب قابلة للتخصيص لكل مدرسة (المتغيّرات `:name`).
- `NotificationPreference` — تفضيلات المستخدم لكل قناة (تمكين/تعطيل).
- `NotificationLog` — سجل تسليم لكل رسالة (الحالة، عدد المحاولات، معرّف المزوّد).
- `NotificationService` — `sendToUser` / `sendToRecipients` / `render` / `deliver`.
- `AlertDispatcher` — تنبيهات الغياب لأولياء الأمور بالهاتف عبر قناة SMS.
- `SendNotificationJob` — إرسال عبر الطوابير (Queues) دون حجب الطلب.
- أمر مجدول: `php artisan notifications:absence-alerts` (يومياً 07:30).

### التقارير والتصدير

- `ReportService` — تقارير: الطلاب، المعلمين، الحضور، تنبيهات الغياب، النتائج
  (النتائج مرتّبة بالرتبة، والحضور مُلخّص لكل شعبة).
- `ExcelExporter` (OpenSpout) — تصدير `.xlsx` بعناوين بارزة.
- `PdfExporter` (DomPDF) — تصدير `.pdf` بترويسة عربية.
- `GET /api/v1/reports/export/{format}` حيث `format` ∈ `xlsx|csv|pdf`.
- `GET /api/v1/reports/schools-overview` — نظرة الوزارة/المديرية على المدارس.

### البوابات الخارجية

- بوابة ولي الأمر: `GET /api/v1/portal/parent` — الأبناء، حضورهم، نتائجهم، رسومهم.
- بوابة الطالب: `GET /api/v1/portal/student` — الجدول، الدرجات، الرتبة، الإشعارات.

### نظام التنافس والترتيب من البيانات الأكاديمية

- `StudentRankingService` يقيس كل طالب على كل معيار (المعدل، الحضور، الغياب،
  المشاركة)، ثم **يُطبّع** القيم داخل الدفعة (الأفضل = 100 والضعف = 0)،
  ويكتب صف `competition_scores` لكل طالب/معيار.
- يُجمّع الطلاب إلى صفوف على مستوى الشعبة (`scope=class`) مع تفصيل لكل معيار.
- `LeaderboardService::recomputeFromAcademics` يدير السلسلة كاملة:
  طالب ← شعبة ← مدرسة ← مديرية ← محافظة ← وزارة.

### واجهات المرحلة الرابعة

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `/api/v1/notifications/inbox` | صندوق الإشعارات |
| `GET` | `/api/v1/notifications/channels` | القنوات المتاحة |
| `GET/PUT` | `/api/v1/notifications/preferences` | تفضيلات القنوات |
| `GET/POST` | `/api/v1/notifications/templates` | القوالب |
| `POST` | `/api/v1/notifications/send` | إرسال إشعار |
| `GET` | `/api/v1/notifications/logs` | سجل التسليم |
| `GET` | `/api/v1/reports` | التقارير المتاحة |
| `GET` | `/api/v1/reports/export/{format}` | تصدير `xlsx\|csv\|pdf` |
| `GET` | `/api/v1/reports/schools-overview` | نظرة عامة على المدارس |
| `GET` | `/api/v1/portal/parent` | بوابة ولي الأمر |
| `GET` | `/api/v1/portal/student` | بوابة الطالب |
| `GET` | `/api/v1/ranking-periods/{period}/classes` | ترتيب الشعب |
| `GET` | `/api/v1/ranking-periods/{period}/my-student-position` | رتبة الطالب الحالي |
| `POST` | `/api/v1/ranking-periods/{period}/recompute-academics` | إعادة احتساب الترتيب من البيانات الأكاديمية |

---

## وحدة الرسوم والفواتير والمدفوعات (الرسوم المالية)

- `fee_structures` — كتالوج الرسوم (دراسية/تسجيل/نقل/نشاط/أخرى) مع المبلغ والعملة
  والاستحقاق، وربط اختياري بالسنة الدراسية وصف دراسي.
- `invoices` — الفواتير الصادرة للطالب، مع بنود `invoice_items`.
- `payments` — الدفعات المُحصّلة، مع رقم إيصال وتسلسل الزمن.
- **القاعدة الذهبية:** `paid_amount` و`balance` و`status` تُحتسب **دائماً** من صفوف
  الدفعات، فلا يمكن أبداً أن تختلف حالة الفاتورة عن النقد المُحصّل فعلاً.
- `FinanceService`:
  - `issueInvoice` — إصدار فاتورة ببنود وخصم.
  - `issueTermInvoices` — إصدار جماعي لكل طالب مُسجَّل (لكل المدرسة أو صف معيّن).
  - `recordPayment` — تحصيل دفعة وإعادة احتساب الرصيد (`unpaid` ← `partial` ← `paid`).
  - `cancel` — إلغاء فاتورة مع استثنائها من الأرصدة، ومَنع الدفع عليها.
  - `studentBalance` / `collectionSummary` — أرصدة الطالب وملخّص التحصيل حسب طريقة الدفع.
- الأرصدة المالية تظهر أيضاً في بوابة ولي الأمر (`/api/v1/portal/parent`).

### واجهات الوحدة المالية

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET/POST` | `/api/v1/fees` | كتالوج الرسوم |
| `GET/POST` | `/api/v1/invoices` | الفواتير |
| `POST` | `/api/v1/invoices/issue-term` | إصدار جماعي لرسوم فصل |
| `GET` | `/api/v1/invoices/{invoice}` | تفاصيل فاتورة ودفعاتها |
| `POST` | `/api/v1/invoices/{invoice}/cancel` | إلغاء فاتورة |
| `GET/POST` | `/api/v1/payments` | الدفعات |
| `GET` | `/api/v1/finance/summary` | ملخّص التحصيل |
| `GET` | `/api/v1/students/{student}/balance` | رصيد الطالب |

---

## مساعد خدمة العملاء (Support Assistant + Support Desk)

مساعد دعم عربي-أولاً يجيب من **قاعدة معرفة معتمدة**، ويُصنّف كل رسالة، ويحوّل
الطلب إلى **تذكرة دعم بشرية** عند الشك أو عند طلب المستخدم صراحةً. مصمَّم ليعمل
بلا مفاتيح: في غياب `JEV_API_KEY`/`ANTHROPIC_API_KEY` يعمل بمصنّف كلمات مفتاحية
حتمي ويردّ بأفضل مقطع من قاعدة المعرفة، فلا تتوقف الميزة عن العمل.

### البنية

- `KbArticle` — مقالات قاعدة المعرفة (سؤال/جواب/كلمات مفتاحية/تصنيف). المقالات
  ذات `tenant_id = null` تكون **عامة على مستوى المنصة** وتظهر لكل مدرسة.
- `SupportConversation` / `SupportMessage` — محادثات المساعد ورسائلها، مع تخزين
  `intent` و`confidence` و`meta` و`pii_redacted` لكل ردّ.
- `SupportTicket` / `SupportTicketMessage` — مكتب الدعم: تذاكر بمصدر `assistant`
  أو `user`، حالات `open/pending/resolved/closed`، أولويات، وإسناد لموظف.
- `KbRetriever` — استرجاع مقاطع بمعادلة TF-IDF خفيفة (تطبيع عربي: تشكيل، همزات،
  تاء مربوطة) مع تصفية التصنيف وإظهار المقالات العامة.
- `PiiRedactor` — حجب الهاتف/البريد/الهوية/الآيبان **قبل** مغادرة الرسالة إلى أي
  مزوّد خارجي، ويُطبَّق على سجلّ المحادثة أيضاً لا على الرسالة الأخيرة فقط.
- `JevClient` — تصنيف النية عبر TypeSafe/Jev (قرار مُصنَّف بثقة مُعايَرة).
- `ClaudeClient` — صياغة الرد العربي من المقاطع المسترجَعة فقط (لا اختلاق أرقام).
- `SupportTicketService` — فتح/إسناد/رد/حل/إغلاق التذاكر، مع فصل الملاحظات الداخلية.

### قواعد التصعيد

- طلب صريح لموظف (`handoff`) → تذكرة دائماً.
- موضوع حسّاس (تنمر، تحرش، عنف، شكوى قانونية، تسريب بيانات...) → تذكرة دائماً.
- لا يوجد مقطع معرفي معتمد → تذكرة.
- تصنيف غير واثق ومقطع ضعيف → عرض التحويل مع الإجابة الأقرب.

### واجهات مساعد خدمة العملاء

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `/api/v1/support/assistant` | التوفّر، النوايا المتاحة، ومحادثاتي |
| `POST` | `/api/v1/support/assistant/conversations` | فتح محادثة |
| `GET` | `/api/v1/support/assistant/conversations/{id}` | رسائل محادثة |
| `DELETE` | `/api/v1/support/assistant/conversations/{id}` | حذف محادثة |
| `POST` | `/api/v1/support/assistant/conversations/{id}/messages` | إرسال رسالة (يردّ: نية، ثقة، مصادر، تصعيد، تذكرة) |
| `GET/POST` | `/api/v1/support/tickets` | قائمة/فتح تذكرة (الطابور الكامل لمن يملك `support.manage`) |
| `GET` | `/api/v1/support/tickets/{id}` | تفاصيل تذكرة ورسائلها |
| `POST` | `/api/v1/support/tickets/{id}/messages` | ردّ (ملاحظة داخلية للموظفين فقط) |
| `PATCH` | `/api/v1/support/tickets/{id}` | تحديث الحالة/الأولوية/الإسناد (`support.manage`) |

الصلاحيات: `support.view` و`support.chat` و`support.manage` (تُمنح للمدير ووكيل
الشؤون الطلابية وكل الأدوار القادرة على الذكاء الاصطناعي). العميل يرى محادثاته
وتذاكره فقط؛ الطابور الكامل لمن يملك `support.manage`، والملاحظات الداخلية لا
تظهر لصاحب التذكرة أبداً.

في تطبيق الجوال: **مساعد خدمة العملاء** متاح من لوحات المدير، ولي الأمر، الطالب،
والكوادر (شاشة `support_assistant_screen.dart` + خدمة `support_service.dart`).

---

## ترحيل البيانات عبر قوالب Excel (الاستيراد الجماعي)

- `GET /api/v1/imports/templates` — قائمة القوالب المتاحة (طلاب، معلمون) بأعمدتها العربية.
- `GET /api/v1/imports/templates/{type}` — تنزيل قالب XLSX جاهز للتعبئة.
- `POST /api/v1/imports` — رفع ملف XLSX/CSV لاستيراده.
- كل صف يُطبّق داخل Transaction مستقل، فيُسجَّل الصف الفاسد ويُتجاوَز دون فقدان الصفوف السليمة.
- الاستيراد **متكرر الآمن** على المفتاح الطبيعي (الرقم الأكاديمي/الوظيفي): إعادة رفع الملف
  تصحّح السجلات بدل تكرارها، ويعيد المُلخّص عدد المُنشأ/المُحدَّث/الفاشل مع رسالة كل خطأ.

## لوحة المعلم (لوحة موحّدة)

- `GET /api/v1/teachers/dashboard` — تجمع في طلب واحد: نصاب المعلم (عدد الحصص، المواد،
  الشعب، الفصول المكلّف بها)، حالة مذكرة التحضير (مسودة/مرسل/معتمد/معاد)، جلسات الحضور
  المنفّذة، والاختبارات القادمة. تُعيد `null` لمن لا يملك ملف معلم.

---

## تشغيل عرض حيّ (Demo)

سكربت واحد يجهّز كل شيء (قاعدة البيانات + الباك اند + واجهة Flutter الويب):

```bash
# بذر بيانات جديدة من الصفر (اختياري)
FRESH=1 bash scripts/run_demo.sh
# أو بدون إعادة البذر
bash scripts/run_demo.sh
```

- **واجهة الويب (المنصة):** منفذ `12001`
  - الرابط العام: `https://work-2-qgkkdtekkcsslwlv.prod-runtime.all-hands.dev/`
  - الرابط المختصر للمراقبة الشاملة: `.../#/monitor`
- **واجهة الـ API:** منفذ `12000` (البادئة `/api/v1`)
  - الرابط العام: `https://work-1-qgkkdtekkcsslwlv.prod-runtime.all-hands.dev/api/v1`

> ملاحظة: يجب فتح رابط الواجهة (`work-2`) لتظهر المنصة. فتح رابط الـ API (`work-1`)
> مباشرةً يعرض رد JSON فقط، لأن هذا منفذ الخدمة الخلفية.

### حسابات العرض (كلمة المرور للجميع: `password`)

| الدور | البريد |
|------|--------|
| مالك المنصة (المهندس صفوت البريهي) | `owner@school-platform.local` |
| وزير التربية (مراقبة للقراءة فقط) | `minister@school-platform.local` |
| مدير مدرسة | `manager@school-platform.local` |
| معلم | `teacher1@school-platform.local` |
| ولي أمر | `parent@school-platform.local` |
| طالب | `student@school-platform.local` |

## النشر (Build & Publish)

### واجهة الويب
يُبنى الويب بملف تعريفي واحد لمضيف الـ API العام:

```bash
cd mobile
flutter build web --release \
  --dart-define=API_BASE_URL="https://<api-host>/api/v1"
# الناتج: mobile/build/web  (يُقدَّم عبر tool/serve_web.py أو أي خادم ثابت)
```

### تطبيق أندرويد (APK)
سكربت واحد يجهّز السلسلة (JDK 21 + Android SDK + NDK) ويبني APK الإصدار:

```bash
API_PUBLIC=https://<api-host> bash scripts/build_android.sh
# الناتج: mobile/build/app/outputs/flutter-apk/app-release.apk
```

> حزمة التطبيق `com.schoolplatform.school_app`، والحد الأدنى `minSdk=24`.
> البناء الحالي موقّع بمفاتيح التصحيح (debug)؛ للنشر على Google Play أنشئ
> `keystore` خاصاً ومرّر `key.properties` إلى `mobile/android/app/build.gradle.kts`.

### ملاحظة تشغيلية
- مضيفا `work-1`/`work-2` العامّان يتغيّران عند كل إعادة تهيئة للبيئة، لذا يشتق
  `scripts/run_demo.sh` و`scripts/build_android.sh` مضيف الـ API من متغيّر
  `RUNTIME_URL` تلقائياً (ويمكن تجاوزه عبر `API_PUBLIC`).

## النسخ الاحتياطية واللقطات (Snapshots)

المستودع يحفظ **لقطة (snapshot) مؤرّخة قابلة للاستعادة** عند أي تغيير، للعودة إليها إذا
حدث تعديل غير مرغوب فيه. كل لقطة هي وسم (tag) مؤرّخ `snap-YYYY-MM-DD-HHMM`، ووسم
الشرح (annotation) يوضّح التغيير وتاريخه.

```bash
scripts/snapshot.sh "وصف التغيير"   # لقطة يدوية: commit + tag + bundle + فهرس
scripts/snapshot.sh --list          # عرض فهرس اللقطات
scripts/snapshot.sh --push          # رفع الوسم إلى origin (نسخة بعيدة)
git checkout <tag>                  # العودة إلى لقطة سابقة
```

- **تلقائياً:** خطّاف `post-commit` (مُثبَّت عبر `scripts/setup_hooks.sh`) ينشئ لقطة بعد
  **كل** commit، ويُدفع الوسم للبعيد كل 20 لقطة.
- **الفهرس المؤرّخ:** `.snapshots/SNAPSHOTS.md` (مُتتبَّع في Git) يسرد كل لقطة بتاريخها ووصفها.
- **النسخة الكاملة:** `.snapshots/<tag>.bundle` نسخة مستقلة كاملة (غير مُتتبَّعة).
  الاستعادة: `git clone .snapshots/<tag>.bundle /tmp/restored`.

## الخطوات التالية

- توسيع لوحة الوزارة للترتيب الوطني وتقارير المقارنة بين المديريات.
- ربط مزوّدي الإشعارات (SMS / WhatsApp Business) بمفاتيح الإنتاج وتشغيل الطوابير (Queues) في الإنتاج.

