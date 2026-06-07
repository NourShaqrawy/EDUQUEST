# دليل الفرونت إند — الفيديوهات وتمارينها (EDUQUEST)

توثيق شامل لكل ما يخص **فيديوهات الكورس** و**تمارينها** (الأسئلة، الخيارات، الإجابات) ونظام **القفل والتقدّم**.

- **قاعدة الرابط (Base URL):** `http://localhost:8000/api`
- **صيغة التبادل:** JSON (إلا رفع الفيديوهات فهو `multipart/form-data`).
- جميع الأمثلة الزمنية بصيغة ISO 8601 UTC.

---

## جدول المحتويات

1. [المصادقة (Auth)](#1-المصادقة-auth)
2. [الأدوار (Roles)](#2-الأدوار-roles)
3. [نظام القفل والتقدّم — اقرأه أولاً](#3-نظام-القفل-والتقدم--اقرأه-أولاً)
4. [التدفّق المقترح للفرونت (السيناريو الكامل)](#4-التدفق-المقترح-للفرونت-السيناريو-الكامل)
5. [فيديوهات الكورس](#5-فيديوهات-الكورس)
6. [أسئلة الفيديو (التمارين)](#6-أسئلة-الفيديو-التمارين)
7. [خيارات الأسئلة](#7-خيارات-الأسئلة)
8. [الإجابات](#8-الإجابات)
9. [مرجع الأخطاء](#9-مرجع-الأخطاء)
10. [مرجع حقول الكائنات](#10-مرجع-حقول-الكائنات)
11. [ملخّص كل الروابط](#11-ملخص-كل-الروابط)

---

## 1. المصادقة (Auth)

كل الروابط المحمية تحتاج التوكن في الـ Header:

```
Authorization: Bearer <token>
Accept: application/json
```

> أرسل دائماً `Accept: application/json` حتى تعود أخطاء التحقّق بصيغة JSON (وليس صفحة HTML أو إعادة توجيه).

### تسجيل الدخول

```
POST /api/login
```

**Body:**
```json
{ "email": "user@example.com", "password": "password123" }
```

**Response — `200`:**
```json
{
  "message": "Logged in successfully",
  "user": {
    "id": 3,
    "name": "Normal User",
    "email": "user@example.com",
    "role": "user",
    "is_active": true,
    "created_at": "2026-06-01T09:00:00.000000Z",
    "updated_at": "2026-06-01T09:00:00.000000Z"
  },
  "token": "12|aBcD3f...XYZ"
}
```

**Response — بيانات خاطئة `401`:**
```json
{ "message": "Invalid credentials" }
```

### التسجيل

```
POST /api/register
```

**Body:** `name`, `email`, `password` (min: 6). الدور الافتراضي للمستخدم الجديد هو `user`.
الاستجابة بنفس شكل تسجيل الدخول (`message`, `user`, `token`).

### تسجيل الخروج

```
POST /api/logout      (محمي)
```
يحذف التوكن الحالي. Response: `{ "message": "Logged out successfully" }`.

> حسابات تجريبية جاهزة بعد `php artisan db:seed`: `admin@example.com` / `publisher@example.com` / `user@example.com` — كلها بكلمة المرور `password123`.

---

## 2. الأدوار (Roles)

| الدور | ما يخصّ الفيديوهات والتمارين |
|---|---|
| `user` | مشاهدة الفيديوهات (مع القفل)، عرض التمارين غير المُجابة، إرسال الإجابات، عرض إجاباته. |
| `publisher` | كل ما سبق + إنشاء/تعديل/حذف الفيديوهات والأسئلة والخيارات لكورساته، وحذف الإجابات. **بدون قفل ولا إخفاء.** |
| `admin` | كل الصلاحيات على كل الكورسات. **بدون قفل ولا إخفاء.** |

> القفل والإخفاء يُطبّقان على دور `user` فقط. أما `admin`/`publisher` فيرون كل المحتوى وكل الحقول دائماً.

---

## 3. نظام القفل والتقدّم — اقرأه أولاً

تُشاهَد فيديوهات الكورس **بالترتيب** (حسب الحقل `order` ثم `id`). القواعد للمستخدم (`user`):

1. **الفيديو الأول دائماً مفتوح.**
2. **لا يُفتح فيديو إلا بعد إكمال كل الفيديوهات السابقة.**
   الإكمال = الإجابة على **كل** أسئلة الفيديو. الفيديو الذي **لا يحوي أسئلة** يُعتبر مكتملاً تلقائياً.
3. **كل تمرين يظهر مرة واحدة فقط.** بمجرد أن يجيب المستخدم على سؤال، يختفي من قوائمه، **ولا يمكن تغيير الإجابة** (حتى لو كانت خاطئة).

هذه القواعد **مفروضة على المخدم**، وليست مجرد أعلام تجميلية:

| المحاولة | النتيجة |
|---|---|
| جلب روابط فيديو مقفل | الروابط **محذوفة** من الاستجابة |
| جلب أسئلة/خيارات فيديو مقفل | `403` |
| إرسال إجابة لفيديو مقفل | `403` |
| إعادة الإجابة على سؤال مُجاب | `409` |
| جلب سؤال/خيارات سؤال سبق أن أُجيب | `403` |

> اعتمد على الحقلين `can_watch` و `is_completed` من رابط دروس الطالب لرسم الواجهة (قفل/فتح/علامة إكمال)، ولا تعتمد على إخفائها فقط — المخدم سيرفض أي تجاوز.

---

## 4. التدفّق المقترح للفرونت (السيناريو الكامل)

```
1) GET /api/courses                         → عرض الكورسات (عام)
2) GET /api/courses/{courseId}/lessons       → قائمة الدروس + can_watch / is_completed
3) المستخدم يفتح درساً can_watch = true
   └─ شغّل الفيديو من video_url / url_360p ...
   └─ GET /api/videos/{videoId}/questions    → الأسئلة غير المُجابة فقط (مع وقت ظهور كل سؤال)
4) عند بلوغ time_in_video لسؤال:
   └─ أظهر السؤال وخياراته (options ضمن السؤال نفسه)
   └─ POST /api/video-answers { question_id, option_id }
        ← 201 + is_correct (true/false) → أظهر التغذية الراجعة
        ← السؤال لن يظهر مجدداً (لو أعدت الجلب)
5) بعد الإجابة على كل أسئلة الدرس:
   └─ أعد GET /api/courses/{courseId}/lessons
        ← الدرس الحالي is_completed = true، والدرس التالي can_watch = true
6) كرّر حتى نهاية الكورس.
```

**نقاط مهمة:**
- لا حاجة لإرسال `is_correct` عند الإجابة؛ المخدم يحسب الصحّة بنفسه ويعيدها لك.
- إعادة جلب `/lessons` بعد كل إجابة (أو بعد إكمال الدرس) هي الطريقة الموثوقة لمعرفة فتح الدرس التالي.
- الأسئلة تعود مرتّبة حسب `time_in_video` تصاعدياً.

---

## 5. فيديوهات الكورس

### 5.1 عرض دروس الكورس للطالب ⭐ (الأهم للفرونت)

```
GET /api/courses/{courseId}/lessons
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

يرجّع كل فيديوهات الكورس **مرتّبة**، ولكل فيديو حقلان إضافيان:

| الحقل | النوع | الوصف |
|---|---|---|
| `can_watch` | boolean | هل يُسمح بمشاهدة هذا الفيديو الآن؟ |
| `is_completed` | boolean | هل أكمل المستخدم هذا الفيديو (أجاب على كل أسئلته)؟ |

> للفيديو المقفل (`can_watch = false`) تُحذف الحقول: `video_url`, `url_144p`, `url_360p`, `url_720p`, `video_path`, `video_144p`, `video_360p`, `video_720p`.

**مثال:** `GET /api/courses/1/lessons`

**Response — `200`:**
```json
{
  "status": "success",
  "data": {
    "course": {
      "id": 1,
      "title": "Laravel API Development",
      "description": "Learn how to build REST APIs with Laravel.",
      "thumbnail": "http://localhost:8000/storage/courses/thumbnails/abc.jpg",
      "category_id": 1,
      "publisher_id": 2
    },
    "videos": [
      {
        "id": 1,
        "course_id": 1,
        "title": "Introduction to Routes",
        "description": "Understand how routing works in Laravel.",
        "duration": 300,
        "order": 1,
        "created_at": "2026-06-02T10:00:00.000000Z",
        "updated_at": "2026-06-02T10:00:00.000000Z",
        "video_url": "http://localhost:8000/storage/course-videos/1/lesson_x/original.mp4",
        "url_144p": "http://localhost:8000/storage/course-videos/1/lesson_x/video_144p.mp4",
        "url_360p": "http://localhost:8000/storage/course-videos/1/lesson_x/video_360p.mp4",
        "url_720p": "http://localhost:8000/storage/course-videos/1/lesson_x/video_720p.mp4",
        "can_watch": true,
        "is_completed": false
      },
      {
        "id": 2,
        "course_id": 1,
        "title": "Controllers",
        "description": "Building controllers.",
        "duration": 420,
        "order": 2,
        "created_at": "2026-06-02T10:05:00.000000Z",
        "updated_at": "2026-06-02T10:05:00.000000Z",
        "can_watch": false,
        "is_completed": false
      }
    ]
  }
}
```

> لاحظ أن الفيديو الثاني مقفل، فلا روابط له في الاستجابة.

---

### 5.2 إدارة الفيديوهات (admin / publisher)

هذه الروابط لإدارة محتوى الكورس، وليست لشاشة المشاهدة. لا تطبّق قفلاً ولا تخفي حقولاً.

#### عرض فيديوهات الكورس (إدارة)

```
GET /api/courses/{courseId}/videos
```
| **الصلاحية** | `admin` أو `publisher` (مالك الكورس أو أدمن) |
|---|---|

**Response — `200`:**
```json
{
  "course": { "id": 1, "title": "...", "...": "..." },
  "videos": [ { "id": 1, "title": "...", "order": 1, "video_url": "...", "...": "..." } ]
}
```
> ⚠️ شكل الاستجابة هنا **بدون** غلاف `status/data` (يختلف عن رابط الطالب).

#### رفع فيديو جديد

```
POST /api/courses/{courseId}/videos
Content-Type: multipart/form-data
```
| **الصلاحية** | `admin` أو `publisher` |
|---|---|

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `title` | string | ✅ | عنوان الدرس (max: 255) |
| `description` | string | ➖ | وصف الدرس |
| `video` | file | ✅ | ملف الفيديو. الأنواع: mp4, mov, avi, mkv, webm. الحد: ~500MB |
| `order` | integer | ➖ | ترتيب الدرس (min: 1). إن لم يُرسل، يُوضع في النهاية تلقائياً |

> عند الرفع يولّد المخدم تلقائياً نسخاً بجودات 144p/360p/720p ويستخرج مدة الفيديو. العملية قد تستغرق وقتاً (معالجة ffmpeg) — راعِ ذلك في الواجهة (مؤشّر تحميل).

**Response — `201`:**
```json
{
  "message": "تم رفع الدرس بكافة الجودات",
  "video": { "id": 5, "course_id": 1, "title": "...", "duration": 300, "order": 3, "video_url": "...", "url_360p": "..." }
}
```
**Response — فشل المعالجة `500`:** `{ "message": "فشل في معالجة الفيديو: ..." }`

#### تعديل فيديو

```
POST /api/courses/{courseId}/videos/{videoId}
Content-Type: multipart/form-data
```
> يُستخدم **POST** للتعديل (وليس PUT) بسبب مشاكل قراءة الملفات مع PUT. كل الحقول اختيارية (`title`, `description`, `video`, `order`). إرسال `video` جديد يحذف الملفات القديمة ويعيد المعالجة.

**Response — `200`:** `{ "message": "تم التحديث وحذف الملفات القديمة", "video": { ... } }`

#### حذف فيديو

```
DELETE /api/courses/{courseId}/videos/{videoId}
```
يحذف الدرس وكل ملفاته من المخدم (وبسبب cascade تُحذف أسئلته وخياراتها وإجاباتها).
**Response — `200`:** `{ "message": "تم حذف الدرس وملفاته نهائياً" }`

---

## 6. أسئلة الفيديو (التمارين)

### 6.1 عرض أسئلة فيديو معيّن ⭐

```
GET /api/videos/{video_id}/questions
```
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
|---|---|

**سلوك دور `user`:**
- إذا كان الفيديو **مقفلاً** → `403`.
- تعود **الأسئلة غير المُجابة فقط** (المُجاب سابقاً يُستثنى).
- حقل `is_correct` **مخفي** داخل كل خيار.
- الأسئلة مرتّبة تصاعدياً حسب `time_in_video`.

**مثال:** `GET /api/videos/1/questions`

**Response — `200` (دور `user`):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "video_id": 1,
      "question": "Which file contains the API routes in Laravel?",
      "time_in_video": 30,
      "created_at": "2026-06-02T10:00:00.000000Z",
      "updated_at": "2026-06-02T10:00:00.000000Z",
      "options": [
        { "id": 1, "question_id": 1, "option_text": "routes/api.php" },
        { "id": 2, "question_id": 1, "option_text": "routes/web.php" },
        { "id": 3, "question_id": 1, "option_text": "app/routes.php" },
        { "id": 4, "question_id": 1, "option_text": "config/routes.php" }
      ]
    }
  ]
}
```

**Response — الفيديو مقفل `403`:**
```json
{ "status": "error", "message": "هذا الفيديو مقفل. عليك إكمال الفيديو السابق أولاً." }
```

> عند `admin`/`publisher`: تعود كل الأسئلة (لا قفل ولا استثناء) ومعها `is_correct` ظاهراً في الخيارات.

### 6.2 عرض سؤال محدّد

```
GET /api/video-questions/{id}
```
| **الصلاحية** | مسجّل دخول |
|---|---|

سلوك `user`: إن كان الفيديو مقفلاً أو السؤال مُجاباً سابقاً → `403`. غير ذلك يعيد السؤال مع خياراته (بدون `is_correct`).

### 6.3 إدارة الأسئلة (admin / publisher)

| العملية | الرابط | Body |
|---|---|---|
| إنشاء | `POST /api/video-questions` | `video_id`, `question` (max 1000), `time_in_video` (≥0) |
| تعديل | `PUT /api/video-questions/{id}` | `question`, `time_in_video` (اختيارية) |
| حذف | `DELETE /api/video-questions/{id}` | — (يحذف خياراته وإجاباته تلقائياً) |

شكل الاستجابة: `{ "status": "success", "message": "...", "data": { ... } }`.

---

## 7. خيارات الأسئلة

> غالباً لن تحتاج هذه الروابط منفصلة على شاشة الطالب لأن الخيارات تأتي **مضمّنة** ضمن السؤال في `/videos/{id}/questions`. موجودة للإدارة وللحالات الخاصة.

### 7.1 عرض خيارات سؤال

```
GET /api/questions/{question_id}/options
```
| **الصلاحية** | مسجّل دخول |
|---|---|

سلوك `user`: إن كان الفيديو مقفلاً أو السؤال مُجاباً → `403`. حقل `is_correct` مخفي.

**Response — `200` (دور `user`):**
```json
{
  "status": "success",
  "data": [
    { "id": 1, "question_id": 1, "option_text": "routes/api.php" },
    { "id": 2, "question_id": 1, "option_text": "routes/web.php" }
  ]
}
```
**Response — `200` (دور `admin`/`publisher`):** نفس الشكل لكن مع `"is_correct": true|false` لكل خيار.

### 7.2 عرض خيار محدّد

```
GET /api/video-options/{id}
```
نفس قواعد الصلاحية والإخفاء أعلاه.

### 7.3 إدارة الخيارات (admin / publisher)

| العملية | الرابط | Body |
|---|---|---|
| إنشاء | `POST /api/video-options` | `question_id`, `option_text` (max 500), `is_correct` (boolean) |
| تعديل | `PUT /api/video-options/{id}` | `option_text`, `is_correct` (اختيارية) |
| حذف | `DELETE /api/video-options/{id}` | — |

> عند تعيين خيار كـ `is_correct: true` يُلغى تلقائياً تصحيح باقي خيارات نفس السؤال (يبقى خيار صحيح واحد فقط).

---

## 8. الإجابات

### 8.1 إرسال إجابة ⭐

```
POST /api/video-answers
```
| **الصلاحية** | مسجّل دخول |
|---|---|

**Body:**
| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `question_id` | integer | ✅ | معرّف السؤال |
| `option_id` | integer | ✅ | معرّف الخيار الذي اختاره المستخدم |

```json
{ "question_id": 1, "option_id": 1 }
```

> **لا ترسل `is_correct`** — المخدم يحسبها من الخيار المختار ويعيدها لك. الإجابة **نهائية ومرة واحدة فقط**.

**Response — `201` (تم القبول):**
```json
{
  "status": "success",
  "message": "Answer submitted",
  "is_correct": true,
  "data": {
    "id": 10,
    "user_id": 3,
    "question_id": 1,
    "option_id": 1,
    "is_correct": true,
    "created_at": "2026-06-02T10:20:00.000000Z"
  }
}
```

**حالات الخطأ:**

| الحالة | الكود | الجسم |
|---|---|---|
| الخيار لا ينتمي للسؤال | `422` | `{ "status":"error", "message":"The selected option does not belong to this question." }` |
| الفيديو مقفل | `403` | `{ "status":"error", "message":"لا يمكنك الإجابة، هذا الفيديو مقفل." }` |
| سبق أن أجاب على السؤال | `409` | `{ "status":"error", "message":"لقد أجبت على هذا السؤال مسبقاً ولا يمكن تغيير الإجابة." }` |
| حقول ناقصة/غير صحيحة | `422` | `{ "message":"...", "errors": { "question_id": ["..."] } }` |

> تعامل مع `409` في الواجهة كـ"تمت الإجابة مسبقاً" (لا تعرضه كخطأ مزعج)، ومع `403` بإعادة المستخدم لإكمال الدرس السابق.

### 8.2 عرض الإجابات

```
GET /api/video-answers
```
- دور `user`: يرى **إجاباته هو فقط**.
- دور `admin`/`publisher`: يرى كل الإجابات.

**Response — `200`:** `{ "status":"success", "data":[ { ...answer, "question": {...}, "option": {...} } ] }`

### 8.3 عرض إجابة محدّدة

```
GET /api/video-answers/{id}
```
دور `user` يرى إجاباته فقط، وإلا `403` (`"You do not have permission to view this answer."`).

### 8.4 حذف إجابة (admin / publisher)

```
DELETE /api/video-answers/{id}
```
**Response — `200`:** `{ "status":"success", "message":"Answer deleted successfully" }`

---

## 9. مرجع الأخطاء

| الكود | المعنى | متى يحدث |
|---|---|---|
| `401` | غير مصرّح | توكن مفقود/منتهٍ، أو بيانات دخول خاطئة |
| `403` | ممنوع | دور غير كافٍ، فيديو مقفل، سؤال مُجاب، أو عرض إجابة شخص آخر |
| `404` | غير موجود | معرّف غير موجود (`No query results for model ...`) |
| `409` | تعارض | إعادة الإجابة على سؤال مُجاب |
| `422` | فشل تحقّق | حقول ناقصة/خاطئة، أو خيار لا ينتمي للسؤال |
| `500` | خطأ خادم | فشل معالجة/رفع فيديو |

شكل أخطاء التحقّق `422` القياسي من Laravel:
```json
{
  "message": "The question id field is required.",
  "errors": { "question_id": ["The question id field is required."] }
}
```

---

## 10. مرجع حقول الكائنات

### الفيديو (Video / Lesson)
| الحقل | النوع | ملاحظات |
|---|---|---|
| `id` | int | |
| `course_id` | int | |
| `title` | string | |
| `description` | string\|null | |
| `duration` | int | بالثواني |
| `order` | int | ترتيب العرض |
| `video_url`, `url_144p`, `url_360p`, `url_720p` | string\|مخفي | روابط جاهزة للتشغيل. **محذوفة للفيديو المقفل** |
| `can_watch` | boolean | **فقط** في رابط `/lessons` |
| `is_completed` | boolean | **فقط** في رابط `/lessons` |
| `created_at`, `updated_at` | datetime | |

### السؤال (Question)
| الحقل | النوع | ملاحظات |
|---|---|---|
| `id` | int | |
| `video_id` | int | |
| `question` | string | نص السؤال |
| `time_in_video` | int | الثانية التي يظهر فيها السؤال |
| `options` | array | مضمّنة عند جلب الأسئلة |

### الخيار (Option)
| الحقل | النوع | ملاحظات |
|---|---|---|
| `id` | int | |
| `question_id` | int | |
| `option_text` | string | |
| `is_correct` | boolean | **مخفي لدور `user`** |

### الإجابة (Answer)
| الحقل | النوع | ملاحظات |
|---|---|---|
| `id` | int | |
| `user_id` | int | |
| `question_id` | int | |
| `option_id` | int | الخيار المختار |
| `is_correct` | boolean | تُحسب على المخدم |
| `created_at` | datetime | لا يوجد `updated_at` |

---

## 11. ملخّص كل الروابط

| # | الميثود | الرابط | الصلاحية | الغرض |
|---|---|---|---|---|
| 1 | POST | `/api/login` | عام | تسجيل الدخول |
| 2 | POST | `/api/register` | عام | إنشاء حساب |
| 3 | POST | `/api/logout` | auth | تسجيل الخروج |
| 4 | GET | `/api/courses` | عام | قائمة الكورسات |
| 5 | GET | `/api/courses/{id}` | عام | كورس محدّد |
| 6 | **GET** | **`/api/courses/{courseId}/lessons`** | **auth** | **دروس الطالب + can_watch/is_completed** ⭐ |
| 7 | GET | `/api/videos/{video_id}/questions` | auth | أسئلة فيديو (غير المُجابة للطالب) ⭐ |
| 8 | GET | `/api/video-questions/{id}` | auth | سؤال محدّد |
| 9 | GET | `/api/questions/{question_id}/options` | auth | خيارات سؤال |
| 10 | GET | `/api/video-options/{id}` | auth | خيار محدّد |
| 11 | POST | `/api/video-answers` | auth | إرسال إجابة ⭐ |
| 12 | GET | `/api/video-answers` | auth | إجابات المستخدم |
| 13 | GET | `/api/video-answers/{id}` | auth | إجابة محدّدة |
| 14 | GET | `/api/courses/{courseId}/videos` | admin, publisher | إدارة: قائمة الفيديوهات |
| 15 | POST | `/api/courses/{courseId}/videos` | admin, publisher | إدارة: رفع فيديو |
| 16 | POST | `/api/courses/{courseId}/videos/{videoId}` | admin, publisher | إدارة: تعديل فيديو |
| 17 | DELETE | `/api/courses/{courseId}/videos/{videoId}` | admin, publisher | إدارة: حذف فيديو |
| 18 | POST | `/api/video-questions` | admin, publisher | إدارة: إنشاء سؤال |
| 19 | PUT | `/api/video-questions/{id}` | admin, publisher | إدارة: تعديل سؤال |
| 20 | DELETE | `/api/video-questions/{id}` | admin, publisher | إدارة: حذف سؤال |
| 21 | POST | `/api/video-options` | admin, publisher | إدارة: إنشاء خيار |
| 22 | PUT | `/api/video-options/{id}` | admin, publisher | إدارة: تعديل خيار |
| 23 | DELETE | `/api/video-options/{id}` | admin, publisher | إدارة: حذف خيار |
| 24 | DELETE | `/api/video-answers/{id}` | admin, publisher | إدارة: حذف إجابة |

---

> للتفاصيل الدقيقة لإدارة الأسئلة/الخيارات/الإجابات (كل أمثلة الـ CRUD) راجع أيضاً [video-questions-api.md](video-questions-api.md).
