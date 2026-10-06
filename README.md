# حقيبة المعلم الرقمية المتميزة

منصة مستقلة لإدارة المدارس والتعليم، مبنية على Laravel، وتجمع إدارة الطلاب والمعلمين والفصول والحضور والاختبارات والرسوم والتقارير والإشعارات في نظام واحد.

## الهوية
**الاسم:** حقيبة المعلم الرقمية المتميزة  
**المستودع:** safwt771754091s-crypto/shcool

## المتطلبات
- Linux (RHEL 9 compatible)
- MySQL 8
- PHP 8.2+
- Composer 2.7+
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension

## التشغيل المحلي
```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan passport:install
php artisan storage:link
php artisan serve --port=8080
```

## الوظائف الأساسية
- إدارة الطلاب والقبول
- إدارة المعلمين
- الفصول والشُعب والمواد
- الحضور اليومي وتقاريره
- الاختبارات والدرجات وكشوف النتائج
- الرسوم والفواتير
- السنوات والجلسات الدراسية
- إدارة الفروع
- التقارير والإشعارات
- لوحة المعلم
- الصلاحيات وإدارة النظام

## قاعدة البيانات
يعتمد التطبيق على MySQL 8 في بيئة الإنتاج. لا تُحفظ بيانات اعتماد قاعدة البيانات داخل المستودع.

## الجدولة
```cron
* * * * * cd /path/to/shcool && php artisan schedule:run >> /dev/null 2>&1
```

## الاستقلالية
هذا هو المستودع المستقل المعتمد للتطبيق باسم **حقيبة المعلم الرقمية المتميزة**.
