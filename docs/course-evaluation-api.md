# نظام تقييم الكورسات (Course Evaluation API)

نظام شامل: التسجيل في الكورس → متابعة الدروس (مشاهدة + أسئلة الدروس 30%) → امتحان الكورس النهائي (70%) مع
عدّاد زمني عبر WebSocket (Reverb) → احتساب التقييم النهائي وإصدار الشهادة.

كل الاستجابات بصيغة موحّدة:

```json
{ "status": "success|error", "message": "...", "data": { } }
```

الأخطاء تُعالَج مركزياً في [bootstrap/app.php](../bootstrap/app.php) (لا `try/catch` مبعثرة) عبر
`ApiException` / `EnrollmentException` / `ExamException`.

## معادلة التقييم

```
نسبة الدروس   = (مجموع الإجابات الصحيحة على كل أسئلة الدروس ÷ مجموع أسئلة الدروس) × 100
نسبة الامتحان = (الإجابات الصحيحة في الامتحان ÷ عدد أسئلة الامتحان) × 100
التقييم النهائي = نسبة الدروس × 30% + نسبة الامتحان × 70%
الشهادة: excellent ≥ 85، good ≥ 70، average ≥ 50، أقل من 50 لا شهادة (يبقى التقييم محفوظاً).
```

## قواعد الوصول

- **غير المسجَّل**: يشاهد الفيديو الأول فقط (معاينة)؛ باقي الفيديوهات مقفولة.
- **المسجَّل**: فتح تسلسلي — يُفتح الفيديو بعد الإجابة على أسئلة كل ما قبله.
- **بوابة الامتحان**: مسجَّل + الامتحان منشور + مشاهدة *جميع* الفيديوهات + الإجابة على *جميع* أسئلة الدروس.
- **محاولة امتحان واحدة فقط** لكل طالب لكل كورس.

## مسارات الطالب (`auth:sanctum` + `role:user`)

| الطريقة | المسار | الوصف |
|--------|--------|-------|
| GET  | `/api/my-enrollments` | كورساتي المسجَّلة |
| POST | `/api/courses/{course}/enroll` | التسجيل في كورس |
| GET  | `/api/courses/{course}/lessons` | الدروس مع `can_watch`/`is_watched`/`is_completed` + ملخّص التقدّم |
| POST | `/api/courses/{course}/lessons/{video}/watch` | تسجيل مشاهدة درس |
| GET  | `/api/courses/{course}/exam` | نظرة عامة على الامتحان والجاهزية (`can_start`) |
| POST | `/api/courses/{course}/exam/start` | بدء/استئناف المحاولة → يُرجع `remaining_seconds` + `timer_channel` + الأسئلة (دون الإجابة الصحيحة) |
| POST | `/api/courses/{course}/exam/answers` | حفظ تلقائي تدريجي لإجابة (`exam_question_id`, `exam_question_option_id`) |
| POST | `/api/courses/{course}/exam/submit` | الإرسال النهائي دفعة واحدة (`answers[]`) → يُرجع التقييم فوراً |
| GET  | `/api/courses/{course}/exam/result` | التقييم النهائي |

## مسارات الناشر (`auth:sanctum` + `role:admin,publisher`)

| الطريقة | المسار | الوصف |
|--------|--------|-------|
| GET    | `/api/courses/{course}/exam/manage` | الامتحان وأسئلته مع الإجابات الصحيحة |
| POST   | `/api/courses/{course}/exam` | إنشاء/تحديث الإعداد (`duration_minutes`) |
| POST   | `/api/courses/{course}/exam/questions` | إضافة سؤال + خياراته (2–4 خيارات، إجابة صحيحة واحدة) |
| PUT    | `/api/exam-questions/{examQuestion}` | تعديل سؤال (استبدال الخيارات إن أُرسلت) |
| DELETE | `/api/exam-questions/{examQuestion}` | حذف سؤال |
| POST   | `/api/courses/{course}/exam/publish` | نشر الامتحان (يتطلب 10–35 سؤالاً صحيح البنية) |
| POST   | `/api/courses/{course}/exam/unpublish` | إلغاء النشر (للسماح بالتعديل) |

> تعديل الأسئلة ممنوع أثناء النشر — يجب إلغاء النشر أولاً (حفاظاً على نزاهة الامتحان).

## المؤقّت ومكافحة الغش (WebSocket / Reverb)

- **الخادم وحده مرجع الوقت**: `ends_at = started_at + duration` محفوظ في القاعدة. قطع الاتصال **لا يوقف العدّاد**.
- البثّ على قناة خاصة `exam-attempt.{attemptId}` (يُصرَّح بها لصاحب المحاولة فقط — [routes/channels.php](../routes/channels.php)).
- الأحداث: `timer.tick` (phase=`running` عدّاد دقائق، ثم `final_minute` عدّاد ثوانٍ)، و`time.up` عند الانتهاء.
- **حفظ تلقائي تدريجي** لكل إجابة؛ عند انتهاء الوقت تُعتمد الإجابات المحفوظة تلقائياً.
- شبكة أمان: وظيفة `FinalizeExamAttempt` (مؤجّلة حتى `ends_at`) + أمر مجدول `exam:broadcast-ticks` كل دقيقة.

### التشغيل

```bash
php artisan reverb:start      # خادم WebSocket
php artisan queue:work        # لمعالجة وظائف المؤقّت/الإنهاء
php artisan schedule:work     # لبثّ عدّاد الدقائق وإنهاء المنتهية
```

إعدادات `.env`: `BROADCAST_CONNECTION=reverb` + متغيّرات `REVERB_*` (انظر `.env.example`).

العميل يستمع عبر Laravel Echo على القناة الخاصة ويصادق عبر `POST /broadcasting/auth` بتوكن Sanctum.
