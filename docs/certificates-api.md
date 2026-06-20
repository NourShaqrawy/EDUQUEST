# Course Certificate API

**Audience:** Frontend developer using Claude Code for VS Code.
**Base URL:** `http://127.0.0.1:8000/api`
**Auth:** All endpoints require `Authorization: Bearer <token>` (Sanctum token from `/api/login`).
**Role:** Student only (`role = user`).

---

## Overview

Certificates are issued on-demand when a student explicitly requests one. The backend validates eligibility instantly and responds with the full certificate object (including a `download_url` for PDF). No polling or async processing is involved.

### Eligibility Rules (enforced server-side — never trust the client)

| Condition | Required value |
|-----------|---------------|
| Exam attempt status | `submitted` or `expired` (must have taken the final exam) |
| Final score (`final_score`) | **≥ 60%** of the combined weighted score |

The combined score is: `(lesson questions score × 30%) + (exam score × 70%)`.

### Certificate Levels

| `level` value | Score range |
|---------------|------------|
| `average` | 60% – 69% |
| `good` | 70% – 84% |
| `excellent` | 85%+ |

---

## Endpoints

### 1. Get Certificate Status

```
GET /api/courses/{courseId}/certificate
```

Returns the issued certificate if it exists, or 404 with eligibility info if not.

**Success (200) — certificate exists:**

```json
{
  "status": "success",
  "message": "success",
  "data": {
    "id": 1,
    "certificate_code": "CERT-3-12-AB4XYZ",
    "level": "good",
    "issued_at": "2026-06-13T10:30:00.000000Z",
    "student": {
      "id": 12,
      "name": "Ahmad Al-Sayed"
    },
    "course": {
      "id": 3,
      "title": "Introduction to Laravel"
    },
    "download_url": "http://127.0.0.1:8000/api/courses/3/certificate/download"
  }
}
```

**Not found (404) — certificate not yet issued:**

```json
{
  "status": "not_issued",
  "message": "لم تُصدر شهادة لهذا الكورس بعد.",
  "data": null,
  "eligibility": {
    "eligible": true,
    "reason": null,
    "final_score": 73.5
  }
}
```

> **UI tip:** If `eligibility.eligible === true`, show the "Request Certificate" button. If `false`, show `eligibility.reason` as a disabled-state explanation.

---

### 2. Request Certificate Issuance

```
POST /api/courses/{courseId}/certificate
```

**Request body:** none (no body required).

Issues the certificate if the student is eligible. Idempotent — calling this multiple times is safe and always returns the same certificate.

**Success — first issuance (201):**

```json
{
  "status": "success",
  "message": "تم إصدار الشهادة بنجاح. يمكنك تحميلها الآن.",
  "data": {
    "id": 1,
    "certificate_code": "CERT-3-12-AB4XYZ",
    "level": "good",
    "issued_at": "2026-06-13T10:30:00.000000Z",
    "student": { "id": 12, "name": "Ahmad Al-Sayed" },
    "course": { "id": 3, "title": "Introduction to Laravel" },
    "download_url": "http://127.0.0.1:8000/api/courses/3/certificate/download"
  }
}
```

**Success — already issued (200):**

Same `data` shape, `message` changes to `"الشهادة مُصدرة مسبقاً."`.

**Ineligible (422):**

```json
{
  "message": "معدلك الكلي (45.0%) أقل من الحد الأدنى للحصول على الشهادة (60%)."
}
```

Other possible 422 messages:
- `"لم تقم باجتياز الامتحان النهائي بعد."` — exam not taken or not finalized.
- `"لا يوجد تقييم نهائي مسجّل لك في هذا الكورس."` — result record missing (edge case).

---

### 3. Download Certificate as PDF

```
GET /api/courses/{courseId}/certificate/download
```

Returns a PDF binary (A4 landscape). **This is not a regular JSON endpoint** — you must handle it as a blob.

**Response headers:**
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="certificate-CERT-3-12-AB4XYZ.pdf"
```

**Error (404) — no certificate issued yet:**
```json
{
  "status": "error",
  "message": "لا توجد شهادة لتحميلها. قم بطلب إصدار الشهادة أولاً."
}
```

#### How to trigger a PDF download in the browser

Because this endpoint is protected by Bearer token auth, a plain `<a href="...">` won't work (it won't send the Authorization header). Use the fetch/axios blob pattern:

```javascript
async function downloadCertificate(courseId, token) {
  const response = await fetch(
    `http://127.0.0.1:8000/api/courses/${courseId}/certificate/download`,
    {
      headers: { Authorization: `Bearer ${token}` },
    }
  );

  if (!response.ok) {
    const err = await response.json();
    throw new Error(err.message);
  }

  const blob = await response.blob();
  const url  = URL.createObjectURL(blob);

  const a    = document.createElement('a');
  a.href     = url;
  a.download = `certificate-course-${courseId}.pdf`;
  a.click();

  // clean up
  URL.revokeObjectURL(url);
}
```

**With Axios (if you use it in your project):**

```javascript
const response = await axios.get(
  `/api/courses/${courseId}/certificate/download`,
  { responseType: 'blob' }   // axios automatically sends stored auth header
);

const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
const a   = document.createElement('a');
a.href     = url;
a.download = `certificate-course-${courseId}.pdf`;
a.click();
URL.revokeObjectURL(url);
```

---

## Recommended UI Flow

```
[Exam Result Page]
       │
       ├─ final_score < 60%  ──→  Show score + "You need ≥60% to earn a certificate"
       │
       └─ final_score ≥ 60%  ──→  Show "Get Your Certificate" button
                                        │
                                        ▼
                               POST /certificate
                                        │
                               Show certificate card
                               (code, level, issued date)
                                        │
                               "Download PDF" button
                                        │
                               GET /certificate/download
                               (blob → trigger file download)
```

### Checking eligibility before showing the button

The `GET /api/courses/{courseId}/exam/result` endpoint already returns:

```json
{
  "data": {
    "final_score": "73.50",
    "passed": true,
    "eligible_for_certificate": true,
    ...
  }
}
```

Use `eligible_for_certificate` to gate the "Get Certificate" button — no extra API call needed.

---

## Error Handling Reference

| HTTP | Scenario | Action |
|------|----------|--------|
| 200 | Certificate already exists | Show certificate card + download button |
| 201 | Certificate just issued | Show success toast + certificate card |
| 404 (`not_issued`) | No certificate yet | Show eligibility info + request button if eligible |
| 404 (`error`) | Download attempted before issuance | Should not happen if UI flow is correct; show error |
| 422 | Eligibility check failed | Show `message` to user |
| 401 | Not authenticated | Redirect to login |

---

## Integration with the Notifications System

When a certificate is auto-issued (which also happens at exam submission time on the backend), the student receives a real-time WebSocket notification of type `certificate` via Laravel Reverb. You can listen for it on the public channel `user.{userId}` with event name `notification.new`:

```javascript
Echo.channel(`user.${userId}`)
  .listen('notification.new', (e) => {
    if (e.type === 'certificate') {
      // e.data.certificate_code, e.data.level, e.data.course_id
      // Prompt the student to view / download their certificate
    }
  });
```

For the full Reverb/Echo setup, refer to the broadcasting configuration in your project.
