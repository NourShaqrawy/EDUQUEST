# توثيق API — أسئلة الفيديو

قاعدة الرابط: `http://localhost:8000/api`

---

## المصادقة

جميع الروابط المحمية تتطلب إرسال التوكن في الـ Header:

```
Authorization: Bearer <token>
```

للحصول على التوكن:

**`POST /api/login`**

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

```json
{
  "message": "Logged in successfully",
  "user": { ... },
  "token": "1|abc123..."
}
```

---

## الأدوار (Roles)

| الدور | الصلاحيات |
|---|---|
| `user` | عرض الأسئلة والخيارات، إرسال الإجابات |
| `publisher` | كل ما يفعله user + إنشاء وتعديل وحذف الأسئلة والخيارات وحذف الإجابات |
| `admin` | كل الصلاحيات |

---

## نظام التقدّم وقفل الفيديوهات (Progression)

تُشاهَد فيديوهات الكورس بالترتيب (`order` ثم `id`). القواعد المطبَّقة على دور `user`:

1. **الفيديو الأول دائماً مفتوح.**
2. **لا يُفتح فيديو إلا بعد إكمال كل الفيديوهات السابقة.** الإكمال = الإجابة على **كل** أسئلة الفيديو. الفيديو الذي لا يحوي أسئلة يُعتبر مكتملاً تلقائياً.
3. **كل تمرين يظهر مرة واحدة فقط.** بمجرد أن يجيب المستخدم على سؤال، لا يظهر له مجدداً، ولا يمكن تغيير الإجابة (حتى لو كانت خاطئة).

هذه القواعد **مفروضة على المخدم** وليست مجرد أعلام للواجهة: أي محاولة لجلب أسئلة/خيارات أو إرسال إجابة لفيديو مقفل تُرفض بـ `403`، وإعادة الإجابة تُرفض بـ `409`.

> لدوري `admin` و `publisher` لا يوجد قفل ولا إخفاء — يرون كل المحتوى.

### عرض دروس الكورس للطالب

```
GET /api/courses/{courseId}/lessons
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

يرجّع كل فيديوهات الكورس مرتّبة، مع حقلين إضافيين لكل فيديو:

| الحقل | النوع | الوصف |
|---|---|---|
| `can_watch` | boolean | هل يُسمح للمستخدم بمشاهدة هذا الفيديو الآن؟ |
| `is_completed` | boolean | هل أكمل المستخدم هذا الفيديو (أجاب على كل أسئلته)؟ |

> **مهم:** روابط ومسارات الفيديو (`video_url`, `url_144p`, `url_360p`, `url_720p`, `video_path`, …) تُحذف من الاستجابة للفيديوهات المقفلة (`can_watch = false`).

**مثال:** `GET /api/courses/1/lessons`

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "data": {
    "course": { "id": 1, "title": "Laravel API Development", "...": "..." },
    "videos": [
      {
        "id": 1,
        "course_id": 1,
        "title": "Introduction to Routes",
        "order": 1,
        "duration": 300,
        "can_watch": true,
        "is_completed": false,
        "video_url": "http://localhost:8000/storage/course-videos/1/.../original.mp4",
        "url_360p": "http://localhost:8000/storage/course-videos/1/.../video_360p.mp4"
      },
      {
        "id": 2,
        "course_id": 1,
        "title": "Controllers",
        "order": 2,
        "duration": 420,
        "can_watch": false,
        "is_completed": false
      }
    ]
  }
}
```

---

## 1. أسئلة الفيديو

### عرض أسئلة فيديو معين

```
GET /api/videos/{video_id}/questions
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

**مثال:** `GET /api/videos/1/questions`

**Response — نجاح `200`:**
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
    },
    {
      "id": 2,
      "video_id": 1,
      "question": "Which middleware protects routes with Sanctum?",
      "time_in_video": 120,
      "options": [ ... ]
    }
  ]
}
```

> ملاحظة: الأسئلة مرتّبة تصاعدياً بحسب `time_in_video`.
> حقل `is_correct` **مخفي** في الـ options عند دور `user`.
> لدور `user`: تُستثنى الأسئلة التي سبق أن أجاب عليها (تُعاد فقط الأسئلة غير المُجابة).

**Response — الفيديو مقفل (دور `user`) `403`:**
```json
{
  "status": "error",
  "message": "هذا الفيديو مقفل. عليك إكمال الفيديو السابق أولاً."
}
```

---

### عرض سؤال محدد

```
GET /api/video-questions/{id}
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

**مثال:** `GET /api/video-questions/1`

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "video_id": 1,
    "question": "Which file contains the API routes in Laravel?",
    "time_in_video": 30,
    "options": [
      { "id": 1, "question_id": 1, "option_text": "routes/api.php" },
      { "id": 2, "question_id": 1, "option_text": "routes/web.php" },
      { "id": 3, "question_id": 1, "option_text": "app/routes.php" },
      { "id": 4, "question_id": 1, "option_text": "config/routes.php" }
    ]
  }
}
```

**Response — غير موجود `404`:**
```json
{ "message": "No query results for model [App\\Models\\VideoQuestion] 99" }
```

---

### إنشاء سؤال جديد

```
POST /api/video-questions
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |
| **Content-Type** | `application/json` |

**Request Body:**

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `video_id` | integer | ✅ | ID الفيديو |
| `question` | string | ✅ | نص السؤال (max: 1000) |
| `time_in_video` | integer | ✅ | الوقت بالثواني الذي يظهر فيه السؤال (min: 0) |

```json
{
  "video_id": 1,
  "question": "What does REST stand for?",
  "time_in_video": 60
}
```

**Response — نجاح `201`:**
```json
{
  "status": "success",
  "message": "Question created successfully",
  "data": {
    "id": 3,
    "video_id": 1,
    "question": "What does REST stand for?",
    "time_in_video": 60,
    "created_at": "2026-06-02T10:05:00.000000Z",
    "updated_at": "2026-06-02T10:05:00.000000Z"
  }
}
```

**Response — خطأ validation `422`:**
```json
{
  "message": "The video id field is required.",
  "errors": {
    "video_id": ["The video id field is required."],
    "time_in_video": ["The time in video field is required."]
  }
}
```

---

### تعديل سؤال

```
PUT /api/video-questions/{id}
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |
| **Content-Type** | `application/json` |

**Request Body** (جميع الحقول اختيارية):

| الحقل | النوع | الوصف |
|---|---|---|
| `question` | string | نص السؤال الجديد (max: 1000) |
| `time_in_video` | integer | الوقت الجديد بالثواني (min: 0) |

```json
{
  "question": "What does RESTful API mean?",
  "time_in_video": 75
}
```

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "message": "Question updated successfully",
  "data": {
    "id": 1,
    "video_id": 1,
    "question": "What does RESTful API mean?",
    "time_in_video": 75,
    "updated_at": "2026-06-02T10:10:00.000000Z"
  }
}
```

---

### حذف سؤال

```
DELETE /api/video-questions/{id}
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |

> تحذير: حذف السؤال يحذف تلقائياً جميع خياراته وإجاباته المرتبطة به.

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "message": "Question deleted successfully"
}
```

---

## 2. خيارات الأسئلة

### عرض خيارات سؤال معين

```
GET /api/questions/{question_id}/options
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

**مثال:** `GET /api/questions/1/options`

**Response — نجاح `200` (دور `user`):**
```json
{
  "status": "success",
  "data": [
    { "id": 1, "question_id": 1, "option_text": "routes/api.php" },
    { "id": 2, "question_id": 1, "option_text": "routes/web.php" },
    { "id": 3, "question_id": 1, "option_text": "app/routes.php" },
    { "id": 4, "question_id": 1, "option_text": "config/routes.php" }
  ]
}
```

**Response — نجاح `200` (دور `admin` أو `publisher`):**
```json
{
  "status": "success",
  "data": [
    { "id": 1, "question_id": 1, "option_text": "routes/api.php",    "is_correct": true  },
    { "id": 2, "question_id": 1, "option_text": "routes/web.php",    "is_correct": false },
    { "id": 3, "question_id": 1, "option_text": "app/routes.php",    "is_correct": false },
    { "id": 4, "question_id": 1, "option_text": "config/routes.php", "is_correct": false }
  ]
}
```

---

### عرض خيار محدد

```
GET /api/video-options/{id}
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

**مثال:** `GET /api/video-options/1`

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "question_id": 1,
    "option_text": "routes/api.php"
  }
}
```

---

### إنشاء خيار جديد

```
POST /api/video-options
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |
| **Content-Type** | `application/json` |

**Request Body:**

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `question_id` | integer | ✅ | ID السؤال |
| `option_text` | string | ✅ | نص الخيار (max: 500) |
| `is_correct` | boolean | ✅ | هل هو الجواب الصحيح؟ |

```json
{
  "question_id": 1,
  "option_text": "bootstrap/app.php",
  "is_correct": false
}
```

> ملاحظة: إذا أرسلت `is_correct: true`، سيتم تلقائياً إلغاء تصحيح باقي الخيارات لنفس السؤال.

**Response — نجاح `201`:**
```json
{
  "status": "success",
  "message": "Option created successfully",
  "data": {
    "id": 9,
    "question_id": 1,
    "option_text": "bootstrap/app.php",
    "is_correct": false,
    "created_at": "2026-06-02T10:15:00.000000Z",
    "updated_at": "2026-06-02T10:15:00.000000Z"
  }
}
```

---

### تعديل خيار

```
PUT /api/video-options/{id}
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |
| **Content-Type** | `application/json` |

**Request Body** (جميع الحقول اختيارية):

| الحقل | النوع | الوصف |
|---|---|---|
| `option_text` | string | نص الخيار الجديد (max: 500) |
| `is_correct` | boolean | تغيير الإجابة الصحيحة |

```json
{
  "is_correct": true
}
```

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "message": "Option updated successfully",
  "data": {
    "id": 2,
    "question_id": 1,
    "option_text": "routes/web.php",
    "is_correct": true
  }
}
```

---

### حذف خيار

```
DELETE /api/video-options/{id}
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "message": "Option deleted successfully"
}
```

---

## 3. إجابات الأسئلة

### عرض الإجابات

```
GET /api/video-answers
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |

> دور `user`: يرى إجاباته هو فقط.
> دور `admin` أو `publisher`: يرى جميع الإجابات.

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "user_id": 3,
      "question_id": 1,
      "option_id": 1,
      "is_correct": true,
      "created_at": "2026-06-02T10:20:00.000000Z",
      "question": {
        "id": 1,
        "question": "Which file contains the API routes in Laravel?",
        "time_in_video": 30
      },
      "option": {
        "id": 1,
        "option_text": "routes/api.php",
        "is_correct": true
      }
    }
  ]
}
```

---

### إرسال إجابة

```
POST /api/video-answers
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول (جميع الأدوار) |
| **Header** | `Authorization: Bearer <token>` |
| **Content-Type** | `application/json` |

**Request Body:**

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `question_id` | integer | ✅ | ID السؤال |
| `option_id` | integer | ✅ | ID الخيار الذي اختاره الطالب |

```json
{
  "question_id": 1,
  "option_id": 1
}
```

> ملاحظة: الإجابة **نهائية** وتُسجَّل مرة واحدة فقط. محاولة إعادة الإجابة على نفس السؤال تُرفض بـ `409` (لا تحديث ولا كتابة فوقها، حتى لو كانت الإجابة الأولى خاطئة).
> كما يجب أن يكون فيديو السؤال **مفتوحاً** للمستخدم، وإلا تُرفض الإجابة بـ `403`.

**Response — نجاح `201` (إجابة صحيحة):**
```json
{
  "status": "success",
  "message": "Answer submitted",
  "is_correct": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "question_id": 1,
    "option_id": 1,
    "is_correct": true,
    "created_at": "2026-06-02T10:20:00.000000Z"
  }
}
```

**Response — نجاح `201` (إجابة خاطئة):**
```json
{
  "status": "success",
  "message": "Answer submitted",
  "is_correct": false,
  "data": { ... }
}
```

**Response — خيار لا ينتمي للسؤال `422`:**
```json
{
  "status": "error",
  "message": "The selected option does not belong to this question."
}
```

**Response — الفيديو مقفل `403`:**
```json
{
  "status": "error",
  "message": "لا يمكنك الإجابة، هذا الفيديو مقفل."
}
```

**Response — سبق أن أجاب على السؤال `409`:**
```json
{
  "status": "error",
  "message": "لقد أجبت على هذا السؤال مسبقاً ولا يمكن تغيير الإجابة."
}
```

---

### عرض إجابة محددة

```
GET /api/video-answers/{id}
```

| | |
|---|---|
| **الصلاحية** | مسجّل دخول — `user` يرى إجاباته فقط |
| **Header** | `Authorization: Bearer <token>` |

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "user_id": 3,
    "question_id": 1,
    "option_id": 1,
    "is_correct": true,
    "created_at": "2026-06-02T10:20:00.000000Z",
    "question": { ... },
    "option": { ... }
  }
}
```

**Response — محاولة عرض إجابة شخص آخر `403`:**
```json
{
  "message": "You do not have permission to view this answer."
}
```

---

### حذف إجابة

```
DELETE /api/video-answers/{id}
```

| | |
|---|---|
| **الصلاحية** | `admin` أو `publisher` |
| **Header** | `Authorization: Bearer <token>` |

**Response — نجاح `200`:**
```json
{
  "status": "success",
  "message": "Answer deleted successfully"
}
```

---

## ملخص الروابط

| الميثود | الرابط | الصلاحية |
|---|---|---|
| GET | `/api/courses/{courseId}/lessons` | auth |
| GET | `/api/videos/{video_id}/questions` | auth |
| GET | `/api/video-questions/{id}` | auth |
| POST | `/api/video-questions` | admin, publisher |
| PUT | `/api/video-questions/{id}` | admin, publisher |
| DELETE | `/api/video-questions/{id}` | admin, publisher |
| GET | `/api/questions/{question_id}/options` | auth |
| GET | `/api/video-options/{id}` | auth |
| POST | `/api/video-options` | admin, publisher |
| PUT | `/api/video-options/{id}` | admin, publisher |
| DELETE | `/api/video-options/{id}` | admin, publisher |
| GET | `/api/video-answers` | auth |
| GET | `/api/video-answers/{id}` | auth |
| POST | `/api/video-answers` | auth |
| DELETE | `/api/video-answers/{id}` | admin, publisher |
