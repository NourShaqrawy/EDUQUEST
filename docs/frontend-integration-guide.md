# دليل تكامل الفرونت إند — نظام تقييم الكورسات (EduQuest)

> هذا الملف موجَّه لمبرمج الفرونت إند، ويصلح أيضاً كسياق لـ **Claude Code** على جهة الفرونت إند.
> يشرح **كل** ما أُضيف في الباك إند وما يجب بناؤه في الواجهة: التسجيل في الكورس، متابعة الدروس،
> امتحان الكورس النهائي مع **عدّاد زمني عبر WebSocket (Reverb)**، واحتساب التقييم النهائي.

- **Base URL**: كل المسارات تحت البادئة `/api` (مثال: `https://api.example.com/api/...`).
- **المصادقة**: Laravel Sanctum بنظام التوكن (Bearer).
- **اللغة**: رسائل الأخطاء بالعربية وجاهزة للعرض مباشرة للمستخدم.

---

## 0) ملخّص التغييرات (ماذا أُضيف؟)

| المجال | الجديد |
|--------|--------|
| التسجيل | تسجيل الطالب في الكورس (`enrollments`). غير المسجَّل يرى الفيديو الأول فقط. |
| الدروس | تتبّع المشاهدة لكل فيديو (`is_watched`) + حالة الفتح (`can_watch`) والإكمال (`is_completed`). |
| أسئلة الدروس (30%) | موجودة مسبقاً (`/video-answers`)، وتُحتسب ضمن التقييم النهائي. |
| امتحان الكورس (70%) | امتحان نهائي لكل كورس، مدّة محدّدة، 10–35 سؤالاً، 2–4 خيارات، إجابة صحيحة واحدة. |
| المؤقّت | **عدّاد زمني من الخادم عبر WebSocket** (دقائق ثم ثوانٍ في الدقيقة الأخيرة). |
| التقييم | درجة نهائية = دروس×30% + امتحان×70%، تُعرض فور التسليم، وتُصدَر شهادة عند النجاح. |
| الاستجابات | **صيغة JSON موحّدة** لكل المسارات الجديدة. |

---

## 1) المصادقة والترويسات (Headers)

كل المسارات المحمية تتطلب:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### تسجيل الدخول / الإنشاء (موجود مسبقاً — لاحظ أن شكله مختلف عن الموحّد)

```http
POST /api/register   { "name", "email", "password" }
POST /api/login      { "email", "password" }
```

الرد (شكل قديم، ليس الموحّد):

```json
{ "message": "Logged in successfully", "user": { "id": 1, "role": "user", ... }, "token": "1|abc..." }
```

> احفظ `token` وأرسله في ترويسة `Authorization` لكل طلب لاحق. `user.role` قد يكون `user` (طالب) أو `publisher` أو `admin`.

---

## 2) صيغة الاستجابة الموحّدة + الأخطاء

**كل المسارات الجديدة** تُرجع:

```json
{ "status": "success", "message": "...", "data": { /* أو [] أو null */ } }
```

**الأخطاء** (تُعالَج مركزياً):

```json
{ "status": "error", "message": "رسالة عربية جاهزة للعرض" }
```

أخطاء التحقق من المدخلات تتضمّن `errors`:

```json
{ "status": "error", "message": "البيانات المُدخلة غير صحيحة.", "errors": { "duration_minutes": ["..."] } }
```

### جدول رموز الحالة (تعامَل معها في الواجهة)

| الرمز | المعنى | أمثلة |
|------|--------|-------|
| 200 / 201 | نجاح | عمليات القراءة/الإنشاء |
| 401 | غير مُسجَّل دخول | التوكن مفقود/منتهٍ → أعِد للّوجين |
| 403 | ممنوع | غير مسجَّل بالكورس، الدرس مقفل، الامتحان غير منشور، الدروس غير مكتملة |
| 404 | غير موجود | كورس/مورد غير موجود |
| 409 | تعارض | مسجَّل مسبقاً، قدّم الامتحان مسبقاً، لا توجد محاولة جارية |
| 422 | تحقق/قاعدة عمل | مدخلات خاطئة، انتهى وقت الامتحان، قيود النشر غير مستوفاة |

> القاعدة الذهبية للواجهة: اعرض `message` كما هو عند أي خطأ، وافحص الرمز لتحديد المسار (لوجين/إعادة محاولة/تعطيل زر).

---

## 3) تدفّق الطالب (role = user)

### 3.1 التسجيل في كورس

```http
POST /api/courses/{courseId}/enroll
```

```json
{ "status":"success","message":"تم التسجيل في الكورس بنجاح.",
  "data": { "id":5, "course_id":1, "enrolled_at":"2026-06-08T10:00:00+00:00",
            "course": { "id":1, "title":"...", "thumbnail":"http://.../x.jpg" } } }
```

- مكرّر → `409`. كورساتي: `GET /api/my-enrollments`.

### 3.2 عرض الدروس + حالة التقدّم

```http
GET /api/courses/{courseId}/lessons
```

```json
{ "status":"success","message":"success","data": {
  "course": { "id":1, "title":"...", "thumbnail":"..." },
  "is_enrolled": true,
  "progress": {
    "total_videos": 3, "watched_videos": 1,
    "total_lesson_questions": 6, "answered_lesson_questions": 2,
    "lessons_completed": false
  },
  "videos": [
    { "id":10, "course_id":1, "title":"الدرس 1", "description":"...", "order":1, "duration":300,
      "can_watch": true, "is_watched": true, "is_completed": true,
      "video_url":"http://.../v.mp4", "url_144p":"...", "url_360p":"...", "url_720p":"..." },
    { "id":11, "title":"الدرس 2", "can_watch": false, "is_watched": false, "is_completed": false }
  ]
}}
```

قواعد مهمّة للواجهة:
- **روابط الفيديو (`video_url`, `url_*`) تعامَل كقيم مبهمة (opaque)** — لا تبنِها في العميل. تشير إلى `/api/media/...` عند `STREAM_MEDIA_VIA_PHP=true` (وهو مسار يدعم Range ويعيد `206 Partial Content`، ولازم لعمل التحريك وتبديل الجودة)، وإلى `/storage/...` خلاف ذلك.
- **التحريك وتبديل الجودة يعتمدان على الخادم**: ملفات mp4 تُولَّد بـ `-movflags +faststart`، ولإصلاح الملفات القديمة شغّل `php artisan videos:faststart` (يدعم `--dry-run`، وبلا إعادة ترميز). إن عاد الفيديو للبداية عند تغيير الجودة أو قفز للنهاية عند التحريك فالسبب إعداد الخادم لا المشغّل.
- **روابط الفيديو (`video_url`, `url_*`) تظهر فقط عندما `can_watch = true`**. للدرس المقفل لا تُرسَل الروابط إطلاقاً (حماية على الخادم) → اعرض قفلاً.
- غير المسجَّل: الفيديو الأول فقط `can_watch = true`.
- المسجَّل: فتح تسلسلي — الدرس التالي يُفتح بعد الإجابة على أسئلة كل ما قبله.
- `is_completed` = أُجيبت كل أسئلة الدرس. `is_watched` = سُجّلت المشاهدة. `progress.lessons_completed` = **شرط دخول الامتحان** (مشاهدة الكل + الإجابة على الكل).

### 3.3 تسجيل مشاهدة درس

استدعِها عند انتهاء الطالب من مشاهدة الفيديو (أو بلوغ نسبة كافية حسب قرارك):

```http
POST /api/courses/{courseId}/lessons/{videoId}/watch
```

```json
{ "status":"success","message":"تم تسجيل مشاهدة الدرس." }
```

- الدرس غير المتاح → `403`. العملية idempotent (التكرار آمن).

### 3.4 أسئلة الدروس (30%) — موجودة مسبقاً

```http
GET  /api/videos/{videoId}/questions        // الأسئلة غير المُجابة فقط، بلا is_correct
POST /api/video-answers  { "question_id", "option_id" }
```
- الإجابة **مرة واحدة** لكل سؤال (إعادة الإجابة → `409`). الفيديو المقفل → `403`.
- لإتاحة الامتحان: يجب الإجابة على **كل** أسئلة الدروس + مشاهدة **كل** الفيديوهات.

### 3.5 نظرة عامة على الامتحان (قبل البدء)

```http
GET /api/courses/{courseId}/exam
```

```json
{ "status":"success","message":"success","data": {
  "is_enrolled": true,
  "lessons_completed": true,
  "exam": { "duration_minutes": 30, "questions_count": 15, "is_published": true },
  "attempt": null,
  "can_start": true
}}
```

- استخدم `can_start` لتفعيل زر «ابدأ الامتحان». إن كان `attempt` غير فارغ وحالته `submitted`/`expired` فالطالب قدّم الامتحان مسبقاً.
- إن كانت هناك محاولة `in_progress`، اعرض «متابعة الامتحان» (سيُستأنف بنفس الوقت المتبقّي).

### 3.6 بدء/استئناف الامتحان

```http
POST /api/courses/{courseId}/exam/start
```

```json
{ "status":"success","message":"تم بدء الامتحان.","data": {
  "attempt": {
    "id": 42, "status":"in_progress",
    "started_at":"2026-06-08T10:00:00+00:00",
    "ends_at":"2026-06-08T10:30:00+00:00",
    "submitted_at": null,
    "remaining_seconds": 1800,
    "score": null,
    "timer_channel": "exam-attempt.42"
  },
  "questions": [
    { "id": 100, "question":"...", "options":[ {"id":1,"option_text":"..."}, {"id":2,"option_text":"..."} ] }
  ]
}}
```

نقاط حرجة:
- **`is_correct` غير موجود** في خيارات الطالب (إجابة واحدة صحيحة محجوبة عمداً).
- ابدأ العدّاد محلياً من `remaining_seconds`، لكن **الخادم هو المرجع** — زامِنه مع رسائل WebSocket (القسم 5).
- `timer_channel` هي القناة الخاصة التي تستمع إليها للعدّاد.
- استدعاء `start` ثانيةً أثناء محاولة جارية يُعيد نفس المحاولة (استئناف). بعد التسليم → `409`.
- الشروط غير المستوفاة ترجع `403` (غير مسجَّل / غير منشور / الدروس غير مكتملة).

### 3.7 الحفظ التلقائي التدريجي (Autosave)

احفظ كل إجابة فور اختيار الطالب لها (هذا ما يضمن اعتماد إجاباته إن انقطع الاتصال أو انتهى الوقت):

```http
POST /api/courses/{courseId}/exam/answers
{ "exam_question_id": 100, "exam_question_option_id": 2 }
```

```json
{ "status":"success","message":"تم حفظ الإجابة." }
```

- يُسمح بتغيير الإجابة (upsert) ما دام الوقت لم ينتهِ.
- بعد انتهاء الوقت → `422` (انتهى وقت الامتحان) → عندها انتقل لشاشة النتيجة.

### 3.8 الإرسال النهائي (Bulk) + عرض التقييم فوراً

```http
POST /api/courses/{courseId}/exam/submit
{ "answers": [ { "exam_question_id":100, "exam_question_option_id":2 }, ... ] }
```

`answers` اختيارية (إن كنت تعتمد على الحفظ التلقائي يمكن إرسالها فارغة أو لإكمال الناقص).

```json
{ "status":"success","message":"تم تسليم الامتحان واحتساب التقييم.","data": {
  "attempt": { "id":42, "status":"submitted", "score":"86.67", "remaining_seconds": 0, ... },
  "result": {
    "course_id":1,
    "lessons_percentage":"100.00",
    "exam_percentage":"86.67",
    "final_score":"90.67",
    "weights": { "lessons":30, "exam":70 },
    "passed": true
  }
}}
```

> ملاحظة: قيم النِّسب تأتي كنصوص decimal (مثل `"90.67"`) — حوّلها بـ `parseFloat` عند الحاجة للحساب/العرض.

### 3.9 جلب التقييم لاحقاً

```http
GET /api/courses/{courseId}/exam/result   →  data: CourseResult (نفس شكل result أعلاه)
```

---

## 4) تدفّق الناشر (role = publisher أو admin)

> كل هذه المسارات تتطلب أن يكون المستخدم **مالك الكورس** أو أدمن، وإلا `403`.

### 4.1 إنشاء/تحديث إعداد الامتحان (المدة)

```http
POST /api/courses/{courseId}/exam   { "duration_minutes": 30 }   // 1..600
```

### 4.2 إضافة سؤال (مع خياراته)

```http
POST /api/courses/{courseId}/exam/questions
{
  "question": "نص السؤال",
  "options": [
    { "option_text": "خيار 1", "is_correct": true },
    { "option_text": "خيار 2", "is_correct": false }
  ]
}
```

قواعد التحقق (يفرضها الخادم، طابِقها في الواجهة):
- عدد الخيارات **2–4**.
- **إجابة صحيحة واحدة فقط** لكل سؤال (`is_correct: true` مرّة واحدة).

### 4.3 تعديل/حذف سؤال

```http
PUT    /api/exam-questions/{id}   { "question"?, "options"? }   // عند إرسال options تُستبدل بالكامل
DELETE /api/exam-questions/{id}
```

### 4.4 عرض الامتحان للناشر (مع الإجابات الصحيحة)

```http
GET /api/courses/{courseId}/exam/manage
```

```json
{ "status":"success","data": {
  "id":7, "course_id":1, "duration_minutes":30, "is_published":false, "questions_count":12,
  "questions": [ { "id":100, "question":"...",
    "options":[ {"id":1,"option_text":"...","is_correct":true}, {"id":2,"option_text":"...","is_correct":false} ] } ]
}}
```

> هنا فقط (للناشر) يظهر `is_correct` ضمن الخيارات.

### 4.5 النشر / إلغاء النشر

```http
POST /api/courses/{courseId}/exam/publish     // يتطلب 10..35 سؤالاً صحيح البنية، وإلا 422 برسالة توضّح السبب
POST /api/courses/{courseId}/exam/unpublish
```

- **تعديل الأسئلة ممنوع أثناء النشر** → ألغِ النشر أولاً (وإلا `422`).
- عند فشل النشر تأتي رسالة عربية دقيقة (مثل: «عدد الأسئلة يجب أن يكون بين 10 و35 (الحالي 7).») — اعرضها للناشر.

---

## 5) المؤقّت عبر WebSocket (Reverb + Laravel Echo)

العدّاد يُدار من الخادم على قناة خاصة، والواجهة تستمع وتعرض. **قطع الاتصال لا يوقف الوقت** (الوقت مرتبط بـ `ends_at` في الخادم).

### 5.1 تثبيت وإعداد العميل

```bash
npm install laravel-echo pusher-js
```

متغيّرات البيئة في الفرونت (Vite):

```env
VITE_REVERB_APP_KEY=eduquestkey
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  // مصادقة القناة الخاصة بتوكن Sanctum:
  authEndpoint: '/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
});
```

### 5.2 الاستماع لعدّاد المحاولة

استخدم `timer_channel` العائدة من `start` (مثل `exam-attempt.42`):

```js
const channel = echo.private(`exam-attempt.${attemptId}`);

// نبضة العدّاد: phase=running (دقائق) ثم final_minute (ثوانٍ)
channel.listen('.timer.tick', (e) => {
  // e = { remaining_seconds, phase: 'running'|'final_minute', unit: 'minutes'|'seconds' }
  updateCountdownUI(e.remaining_seconds, e.unit);
});

// انتهاء الوقت: الإجابات المحفوظة تُعتمد تلقائياً
channel.listen('.time.up', (e) => {
  // e = { status: 'expired', message }
  lockExamUI();
  fetchResult(); // GET /api/courses/{courseId}/exam/result
});
```

> لاحظ النقطة `.` قبل اسم الحدث (`.timer.tick`, `.time.up`) لأن الأحداث تستخدم `broadcastAs`.

### 5.3 ما يجب أن تتعامل معه الواجهة (مهم جداً لمكافحة الغش)

- **العدّاد المحلي للعرض فقط**؛ اعتمد دائماً `remaining_seconds` من الخادم (عند `start` ومن نبضات `timer.tick`) لإعادة المزامنة.
- **عند إعادة تحميل الصفحة / إعادة الاتصال**: استدعِ `GET /api/courses/{courseId}/exam` أو `start` ثانيةً → سيُعيد المحاولة الجارية مع `remaining_seconds` الصحيح. أعِد الاشتراك في القناة.
- **لا توقف العدّاد عند فقدان الاتصال**؛ استمر بالعدّ محلياً ثم زامِن عند العودة. إن عاد `remaining_seconds = 0` أو وصلت `time.up` → اعرض النتيجة.
- في الدقيقة الأخيرة ستصل نبضات كل ثانية (`unit:'seconds'`) — بدّل واجهة العدّاد إلى صيغة ثوانٍ.
- لا تثق بأي وقت من جهة العميل لإرسال الإجابات؛ الخادم يرفض الحفظ بعد الانتهاء (`422`).

---

## 6) قائمة ما يجب بناؤه في الفرونت إند (Checklist)

- [ ] تخزين توكن Sanctum وإرفاقه بكل طلب + معالجة `401` (إعادة للّوجين).
- [ ] طبقة استجابة موحّدة: قراءة `data` عند النجاح، وعرض `message` عند الخطأ حسب جدول الرموز.
- [ ] صفحة الكورس: زر «تسجيل» (يختفي/يتعطّل إن `is_enrolled`).
- [ ] قائمة الدروس: قفل الدروس حسب `can_watch`، شارات `is_watched`/`is_completed`، شريط تقدّم من `progress`.
- [ ] مشغّل الفيديو: استخدم `video_url`/`url_*` (موجودة فقط للمتاح) + استدعاء `watch` عند الإكمال.
- [ ] حل أسئلة الدروس (إجابة واحدة لكل سؤال) ومعالجة `409`/`403`.
- [ ] شاشة «جاهزية الامتحان» معتمدة على `can_start` + `lessons_completed`.
- [ ] شاشة الامتحان: عرض الأسئلة، **حفظ تلقائي لكل إجابة**، عدّاد مزامَن مع WebSocket.
- [ ] زر «تسليم» (Bulk) → عرض شاشة النتيجة من `result` فوراً.
- [ ] التعامل مع `time.up` (قفل + جلب النتيجة) واستئناف المحاولة بعد إعادة التحميل.
- [ ] لوحة الناشر: إعداد المدة، CRUD للأسئلة (2–4 خيارات/إجابة صحيحة واحدة)، نشر/إلغاء نشر مع عرض رسائل القيود.

---

## 7) ملاحظات لـ Claude Code (على جهة الفرونت إند)

> انسخ هذا القسم كتعليمات مباشرة للوكيل:

1. **مصدر الحقيقة للعقود**: استخدم الأشكال في هذا الملف حرفياً. الاستجابات الجديدة دائماً `{ status, message, data }`؛ استثناء وحيد: `/api/login` و`/api/register` يعيدان `{ message, user, token }`.
2. **الوقت من الخادم**: لا تبنِ منطق انتهاء الامتحان على ساعة المتصفح. اعتمد `remaining_seconds` (من `start` ونبضات `timer.tick`) وحدث `time.up`. العدّاد المحلي للعرض فقط.
3. **الأمان على الواجهة**: لا تفترض وجود `is_correct` في خيارات الطالب؛ لا تكشف إجابات. روابط الفيديو غير موجودة للدروس المقفلة — لا تخمّنها.
4. **الحفظ التلقائي إلزامي**: أرسل كل إجابة عبر `/exam/answers` فور اختيارها؛ الاعتماد على «تسليم واحد في النهاية» قد يفقد الإجابات عند انتهاء الوقت/انقطاع الاتصال.
5. **WebSocket**: استخدم `laravel-echo` + `pusher-js` مع `broadcaster: 'reverb'`، وصادق القناة الخاصة عبر `/broadcasting/auth` بترويسة `Authorization: Bearer`. استمع للأحداث بنقطة بادئة (`.timer.tick`, `.time.up`).
6. **الاستئناف**: عند فتح شاشة الامتحان أو إعادة التحميل، استعلم أولاً (`GET /exam` أو `start`) لاستعادة الحالة والوقت المتبقّي، ثم أعِد الاشتراك بالقناة.
7. **معالجة الأخطاء**: اعرض `message` العربي مباشرة. عامل `409` كـ«قُدِّم مسبقاً/جارٍ بالفعل»، و`403` كـ«غير متاح بعد»، و`422` كـ«تحقق/انتهاء الوقت».
8. **الأدوار**: أظهِر واجهات الناشر فقط عندما `user.role ∈ {publisher, admin}`، وواجهات الطالب عندما `user.role === 'user'`.

---

## 8) مرجع سريع للمسارات

```
# طالب (Bearer + role:user)
GET    /api/my-enrollments
POST   /api/courses/{course}/enroll
GET    /api/courses/{course}/lessons
POST   /api/courses/{course}/lessons/{video}/watch
GET    /api/courses/{course}/exam
POST   /api/courses/{course}/exam/start
POST   /api/courses/{course}/exam/answers
POST   /api/courses/{course}/exam/submit
GET    /api/courses/{course}/exam/result

# أسئلة الدروس (Bearer)
GET    /api/videos/{video}/questions
POST   /api/video-answers

# ناشر/أدمن (Bearer + role:admin,publisher)
GET    /api/courses/{course}/exam/manage
POST   /api/courses/{course}/exam
POST   /api/courses/{course}/exam/questions
PUT    /api/exam-questions/{examQuestion}
DELETE /api/exam-questions/{examQuestion}
POST   /api/courses/{course}/exam/publish
POST   /api/courses/{course}/exam/unpublish

# WebSocket
POST   /broadcasting/auth           (Bearer)  → مصادقة القناة الخاصة
channel: private  exam-attempt.{attemptId}
events:  .timer.tick { remaining_seconds, phase, unit }
         .time.up    { status, message }
```
