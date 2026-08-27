# SEEDING — توليد بيانات ضخمة للعرض/المناقشة المحلية

> دليل تشغيلي لتعبئة قاعدة بيانات EDUQUEST ببيانات ضخمة تمثّل **جميع حالات النظام**.
> السيدر الرئيسي: [`database/seeders/MassiveDataSeeder.php`](database/seeders/MassiveDataSeeder.php).
> هذا الملف هو المرجع في كل مرة يُطلب فيها "توليد بيانات ضخمة".

---

## 1. التشغيل السريع

```bash
# من جذر مشروع الباك-إند (EDUQUEST)
php artisan migrate:fresh --seed --force
```

> على هذا الجهاز، أمر artisan يحتاج **PHP 8.4**:
> ```bash
> C:/php/php-8.4/php.exe artisan migrate:fresh --seed --force
> ```
> (الـ `php` في PATH هو 8.2 ويفشل.)

- `migrate:fresh` يمسح القاعدة ويعيد بناءها، ثم `--seed` يشغّل `DatabaseSeeder` الذي يستدعي `MassiveDataSeeder`.
- الزمن التقريبي: **~45–50 ثانية** (معظمه ترميز الفيديو بـ FFmpeg).
- لإعادة الملء دون إعادة الترحيل على قاعدة فارغة: `php artisan db:seed --class=MassiveDataSeeder`.

---

## 2. الأصول (صور + فيديوهات) — أين تُوضع

يقرأ السيدر **كل** الملفات تلقائياً من:

```
database/seeders/images/     ← صور الكورسات (jpg/jpeg/png/gif/webp)
database/seeders/videos/     ← فيديوهات الدروس (mp4/mov/avi/mkv/webm)
```

قواعد الأصول:
- **الصورة الافتراضية:** ملف اسمه `all` (أي امتداد) = يُستخدم كصورة للكورسات التي "بلا صورة". إن لم يوجد، تُستخدم أول صورة.
- **الأبعاد الموصى بها للصور:** نسبة **16:9** (`1280×720`) — الواجهة تعرضها بـ `object-cover` فتُقصّ ما يخرج عن النسبة.
- **الفيديوهات:** يُفضّل قصيرة (10–60 ثانية). تُقرأ مدتها الحقيقية عبر `ffprobe`، وتُولَّد نسخ **144p/360p/720p** بـ `ffmpeg`.
- الأسماء لا تهم (عدا `all`). يُعاد استخدام الأصول دورياً عبر كل الكورسات (خفيف على القرص).

أين تُنسخ بعد التوليد (داخل `storage/app/public`، تُخدَم عبر `public/storage` symlink):
```
storage/app/public/courses/thumbnails/seed_<slug>.<ext>
storage/app/public/course-videos/shared/v<N>_{original,144p,360p,720p}.mp4
```

---

## 3. المتطلّبات قبل التشغيل (تحقّق منها)

| المتطلّب | كيف تتحقّق | ملاحظة |
|---|---|---|
| `APP_URL` = `http://127.0.0.1:8000` | في `.env` | روابط الوسائط تُبنى منه (`asset('storage/...')`). |
| `php artisan storage:link` منفّذ | وجود `public/storage` | بدونه **لن تُعرض** الصور/الفيديوهات. |
| MySQL شغّال + اتصال `.env` صحيح | `DB_DATABASE=EDUQUEST` | السيدر يعطّل FK checks مؤقتاً أثناء الإدراج. |
| FFmpeg + ffprobe في PATH | `ffmpeg -version` | **اختياري.** بدونه: كل الجودات تشير للأصل، والمدة = 10ث. |
| فرونت `VITE_API_URL` | `http://127.0.0.1:8000/api` | في `react-edu-quest/.env`. |

---

## 4. ماذا يُولَّد (تغطية جميع الحالات)

### المستخدمون (~79)
- حسابات معروفة (كلمة المرور للجميع: **`password123`**):
  - `admin@example.com` (admin) · `publisher@example.com` (publisher) · `user@example.com` (student)
  - `disabled@example.com` — **حساب معطّل** (`is_active=false`) لتجربة حالة الإيقاف.
- +3 مدراء، +12 ناشراً، +60 طالباً (قابلة للضبط، انظر §6).

### الكورسات (24) — كل تركيبات الحالة
`status` (pending/approved/rejected) × `completion_status` (ongoing/completed) × `has_certificate` (certified/introductory)، منها:
- معتمد مكتمل بشهادة (يظهر للطلاب + امتحان منشور).
- معتمد مكتمل **تعريفي** (بلا امتحان، بلا شهادة).
- معتمد **قيد التعديل** (مخفي عن الكتالوج).
- **بانتظار** موافقة الأدمن.
- **مرفوض** مع `rejection_reason`.
- بعض الكورسات تستخدم الصورة الافتراضية `all`.

### المحتوى التعليمي
- **دروس (108)** فيديوهات حقيقية قابلة للمشاهدة بجودات متعددة + `duration` حقيقي.
- **أسئلة داخل الفيديو (216)** بتوقيت `time_in_video` ضمن مدة الفيديو، لكل سؤال 4 خيارات (صحيح واحد).

### الامتحانات (16 امتحان، للكورسات بشهادة فقط)
- بنك أسئلة **12..30** (ضمن الحدّ المسموح 10..35) + `questions_to_serve` (10..15).
- امتحانات **منشورة وغير منشورة**.

### محاولات الطلاب + سجل المراقبة (محور ميزة "مراقبة الامتحانات")
- **~110 محاولة** بحالات: `submitted` (~89) · `expired` (~9) · `in_progress` (~12).
- لكل محاولة: **بنك المحاولة** (`exam_attempt_questions`) + **إجابات** (`exam_attempt_answers`).
- **سجل مراقبة ضخم (~2000+ حدث)** — الأنواع التسعة من `proctoring.js → EXAM_EVENT_TYPES` + `auto_submit` + `terminated`، موزّعة زمنياً:
  - محاولات **نظيفة**، **مخالفات خفيفة**، **مخالفات كثيفة**، و**مُنهاة قسرياً** (`terminated` مع `meta.reason`).
  - ~80/110 محاولة فيها مخالفة واحدة على الأقل؛ 24 محاولة مُنهاة.

### النتائج والشهادات
- **نتائج الكورس (273)**: `final_score = 30% دروس + 70% امتحان` (التعريفي = نسبة الدروس فقط).
- **شهادات (~98)** للناجحين (≥60%) بمستويات: `average`/`good`/`excellent`.

### أخرى
- تسجيلات (273) · تقدّم دروس (928) · إجابات أسئلة فيديو (1693).
- إشعارات (~197) مقروءة/غير مقروءة بمحتوى ثنائي اللغة في `data`.
- أسئلة شائعة (12 = 4 أساسية + 8 إضافية) + اقتراحات زوّار (7) بحالاتها (pending/converted/dismissed) + `home_faq_count=5`.
- طلبات حذف كورسات (6) بحالاتها (pending/approved/rejected).

---

## 5. التحقّق بعد التشغيل

يطبع السيدر ملخّص أعداد لكل جدول + حسابات الدخول. للتحقّق اليدوي:

```bash
C:/php/php-8.4/php.exe artisan tinker
>>> App\Models\Course::count();                    // 24
>>> DB::table('exam_attempt_events')->count();     // ~2000+
>>> App\Models\CourseCertificate::count();         // ~98
```

للعرض البصري: شغّل الباك (`composer dev` أو `php artisan serve`) + الفرونت (`npm run dev`)، ثم:
- سجّل دخول ناشر/مدير → **لوحة التحكم → مراقبة الامتحانات** → اختر كورساً → افتح محاولة → شاهد الخط الزمني للأحداث.
- سجّل دخول طالب (`user@example.com`) → تصفّح الكورسات (صور تظهر) → افتح درساً (فيديو يعمل) → شهاداتي.

---

## 6. ضبط الحجم

عدّل الثوابت أعلى [`MassiveDataSeeder.php`](database/seeders/MassiveDataSeeder.php):

```php
private const EXTRA_STUDENTS   = 60;   // عدد الطلاب الإضافيين
private const EXTRA_PUBLISHERS = 12;   // عدد الناشرين الإضافيين
private const EXTRA_ADMINS     = 3;
private const MIN_VIDEOS       = 3;    // دروس لكل كورس
private const MAX_VIDEOS       = 6;
private const EXAM_BANK_MIN    = 12;   // حجم بنك أسئلة الامتحان (10..35)
private const EXAM_BANK_MAX    = 30;
private const PASS_THRESHOLD   = 60.0; // نسبة النجاح للشهادة
```

لزيادة عدد الكورسات: أضِف عناصر إلى مصفوفة `$titles` داخل `seedCoursesAndContent` (تُوزَّع تلقائياً على `$stateMatrix`).

---

## 7. ملاحظات وتحذيرات

- **الاعتماد على الأصول المحلية:** السيدر يقرأ من `database/seeders/{images,videos}`. عند نقل المشروع لجهاز آخر، انقل هذين المجلدين معه.
- **الترميز بطيء نسبياً:** كل فيديو × 3 جودات. لو أردت تسريع التجارب، ضع فيديو واحداً فقط أو احذف FFmpeg (ستشير كل الجودات للأصل).
- **`migrate:fresh` لا يمسح `storage`:** الملفات المنسوخة سابقاً تبقى؛ السيدر يعيد الكتابة فوقها بنفس الأسماء (`seed_*`, `v<N>_*`) فلا تتراكم بشكل ضار.
- **السيدرات القديمة** (`FullDatabaseSeeder`, `TestDataSeeder`) لم تعد مستخدمة في `DatabaseSeeder` — `MassiveDataSeeder` يحلّ محلّها ويغطّي حالات أكثر.
- **البيانات للعرض فقط:** الإجابات والأحداث مولَّدة حتمياً (شبه عشوائية) لتبدو واقعية، وليست ناتجة عن تفاعل حقيقي.

---

## 8. مشكلة استنزاف منافذ TCP على Windows (مهم)

**العَرَض:** بعد كثرة الطلبات (تصفّح مكثّف، أو تشغيل نسختين من `composer dev`) يظهر خطأ:
```
SQLSTATE[HY000] [2002] Only one usage of each socket address (protocol/network address/port)
is normally permitted (Connection: mysql, Host: 127.0.0.1, Port: 3306 ...)
```

**السبب:** `php artisan serve` يفتح اتصال MySQL جديداً عبر TCP لكل طلب ثم يغلقه، وWindows يُبقي كل منفذ في `TIME_WAIT` ~120 ثانية. نطاق المنافذ المؤقتة الافتراضي ~16,384 منفذاً فقط، فتنفد بسرعة تحت الضغط. تحقّق بـ:
```bash
netstat -ano | grep TIME_WAIT | grep -c :3306   # رقم كبير (آلاف) = المشكلة
```

**الإصلاح الدائم المُطبَّق:** فُعِّلت **الاتصالات الدائمة** في [`config/database.php`](config/database.php) لموصّل `mysql`:
```php
PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
```
يُعيد PHP استخدام اتصال MySQL بدل فتح/إغلاق منفذ لكل طلب → لا استنزاف. **يتطلّب إعادة تشغيل الخادم** بعد التغيير (والـ `php artisan config:clear`). لتعطيله عند الحاجة: `DB_PERSISTENT=false` في `.env`.

**قواعد تجنّب المشكلة أثناء العرض:**
1. شغّل **نسخة واحدة فقط** من `composer dev` (لا نسختين — كل نسخة تشغّل serve+queue+reverb، فيتضاعف الضغط على 3306).
2. إذا ظهر الخطأ: أوقف كل عمليات php ثم أعِد التشغيل النظيف:
   ```bash
   powershell -Command "Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force"
   # انتظر تفريغ المنافذ (قد يستغرق دقائق حتى تنقضي مهلة TIME_WAIT)، ثم:
   composer dev
   ```
3. **إصلاح جذري إضافي (اختياري، يحتاج صلاحيات مدير)** — تقصير مهلة TIME_WAIT وتوسيع نطاق المنافذ (PowerShell كمسؤول، ثم إعادة إقلاع):
   ```powershell
   Set-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters' TcpTimedWaitDelay 30 -Type DWord
   netsh int ipv4 set dynamicport tcp start=10000 num=55535
   ```

> للعرض العادي أمام لجنة (مستخدم واحد يتصفّح) لن تظهر المشكلة. تظهر فقط تحت ضغط اتصالات كثيف مثل أدوات الاختبار الآلي.
