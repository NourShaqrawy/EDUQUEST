# Certificate Issuance — Complete Frontend Implementation Guide

> **Audience:** Frontend developer using Claude Code for VS Code.
> This document describes every API call, data shape, business rule, and edge case involved in the certificate issuance flow. Read it end-to-end before writing a single line of UI code.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Scoring Formula](#2-scoring-formula)
3. [Certificate Eligibility Rules](#3-certificate-eligibility-rules)
4. [Complete User Journey](#4-complete-user-journey)
5. [API Reference](#5-api-reference)
6. [TypeScript Interfaces](#6-typescript-interfaces)
7. [Implementation Guide](#7-implementation-guide)
8. [Real-time Notifications (WebSocket)](#8-real-time-notifications-websocket)
9. [PDF Download](#9-pdf-download)
10. [Error Handling Reference](#10-error-handling-reference)
11. [State Machine](#11-state-machine)

---

## 1. System Overview

**Base URL:** `http://127.0.0.1:8000/api`
**Auth mechanism:** Bearer token via Sanctum (`Authorization: Bearer <token>`)
**Role required for all certificate endpoints:** `user` (student role)
**All responses are JSON** except the PDF download endpoint which returns a binary PDF.

### Technology Stack (backend)

| Layer | Technology |
|---|---|
| Framework | Laravel 13 / PHP 8.4 |
| Auth | Laravel Sanctum (token-based, not cookie-based) |
| Real-time | Laravel Reverb (WebSocket server on port 8080) |
| PDF | barryvdh/laravel-dompdf |
| Database | MySQL |

### Standard success response shape

```json
{
  "status": "success",
  "message": "...",
  "data": { ... }
}
```

### Standard validation error shape (422)

```json
{
  "message": "The given data was invalid.",
  "errors": { "field": ["..."] }
}
```

> **Note:** Business-rule rejections (e.g. ineligible for certificate) also return HTTP 422, but with a single top-level `message` field and no `errors` key.

---

## 2. Scoring Formula

Understanding how the backend calculates the final score is critical for building the result/certificate UI.

```
final_score = (lessons_percentage × 0.30) + (exam_percentage × 0.70)
```

| Component | Weight | Source |
|---|---|---|
| `lessons_percentage` | 30% | Correct answers on video-lesson questions across all course videos |
| `exam_percentage` | 70% | Correct answers on the final exam attempt |

**Edge cases the backend handles silently:**
- A course with **no lesson questions** → `lessons_percentage = 100` (gives full lesson credit)
- An exam question **not answered** → counts as incorrect (0 points)
- `final_score` is rounded to 2 decimal places

---

## 3. Certificate Eligibility Rules

All rules are enforced server-side. Never gate the request client-side alone — the server will reject ineligible requests.

| Rule | Threshold | HTTP on failure |
|---|---|---|
| Student must have enrolled | — | 403 (middleware) |
| Student must have a **finalized** exam attempt | `status = submitted` OR `status = expired` | 422 |
| `final_score` must be ≥ **60%** | 60.0 | 422 |

### Certificate Levels

| `level` | Score range |
|---|---|
| `"average"` | 60% – 69.99% |
| `"good"` | 70% – 84.99% |
| `"excellent"` | ≥ 85% |

> **Idempotency:** Certificates are unique per `(user, course)`. If the student already has a certificate and requests again, the same certificate is returned. It is always safe to call `POST /certificate` multiple times.

---

## 4. Complete User Journey

The certificate is the **final stage** of a linear progression. The student cannot skip ahead.

```
ENROLL in course
      │
      ▼
WATCH lesson videos (sequential unlock — must complete previous)
      │   Each lesson: watch video → answer lesson questions → next unlocks
      ▼
All lessons completed? ──NO──→ cannot access exam
      │
     YES
      │
      ▼
START final exam  (GET /courses/{id}/exam → POST /courses/{id}/exam/start)
      │
      ├── Answer questions (POST /courses/{id}/exam/answers — autosave)
      │
      └── SUBMIT (POST /courses/{id}/exam/submit)
               OR timer expires (backend auto-finalizes → status: "expired")
                    │
                    ▼
           final_score calculated immediately
                    │
           ┌────────┴────────┐
        < 60%             ≥ 60%
           │                 │
           ▼                 ▼
     No certificate    Certificate auto-issued
     (but result        in database + "certificate"
      is saved)         WebSocket notification sent
           │                 │
           └────────┬────────┘
                    ▼
      GET /courses/{id}/exam/result
      (always available after exam finalized)
                    │
                    ▼
    eligible_for_certificate === true?
           │
          YES
           │
           ▼
    POST /courses/{id}/certificate     ← student explicitly requests
           │
           ▼
    GET /courses/{id}/certificate      ← fetch certificate data
           │
           ▼
    GET /courses/{id}/certificate/download  ← download PDF (blob)
```

---

## 5. API Reference

### 5.1 Authentication

#### `POST /api/login`

```json
// Request body
{ "email": "user@example.com", "password": "password123" }

// Response 200
{
  "status": "success",
  "message": "...",
  "data": {
    "token": "1|abcdefghij...",
    "user": { "id": 12, "name": "Ahmad", "email": "...", "role": "user" }
  }
}
```

Store `data.token` — send as `Authorization: Bearer <token>` on every subsequent request.

---

### 5.2 Enrollment

#### `POST /api/courses/{courseId}/enroll`

No request body needed.

```json
// Response 201
{
  "status": "success",
  "message": "تم التسجيل في الكورس بنجاح.",
  "data": {
    "id": 5,
    "course_id": 3,
    "enrolled_at": "2026-06-13T10:00:00+00:00",
    "course": { "id": 3, "title": "Introduction to Laravel", ... }
  }
}
```

---

### 5.3 Course Lessons

#### `GET /api/courses/{courseId}/lessons`

Returns all lessons with sequential-unlock state for the authenticated student.

```json
// Response 200
{
  "status": "success",
  "data": {
    "course": { "id": 3, "title": "Introduction to Laravel" },
    "is_enrolled": true,
    "progress": {
      "total": 5,
      "watched": 3,
      "completed": 2,
      "percentage": 40
    },
    "videos": [
      {
        "id": 10,
        "title": "Lesson 1: Setup",
        "order": 1,
        "duration": 1200,
        "can_watch": true,
        "is_watched": true,
        "is_completed": true,
        "video_url": "...",
        "url_144p": "...",
        "url_360p": "...",
        "url_720p": "..."
      },
      {
        "id": 11,
        "title": "Lesson 2: Routing",
        "order": 2,
        "can_watch": true,
        "is_watched": false,
        "is_completed": false
        // video_url fields are omitted when can_watch === false
      }
    ]
  }
}
```

**Sequential unlock logic (important for UI):**
- Lesson 1 (`can_watch: true`) is always accessible — even without enrollment (preview).
- Lesson N (`can_watch: true`) requires: enrolled + all lessons 1…N-1 have `is_completed: true`.
- `is_completed = true` when the student has answered **all** lesson questions of that video.
- If a lesson has **zero questions**, it is considered completed once watched.

#### `POST /api/courses/{courseId}/lessons/{videoId}/watch`

Call this when the student finishes watching a video. No body required.

```json
// Response 200
{ "status": "success", "message": "تم تسجيل مشاهدة الدرس." }
```

---

### 5.4 Final Exam

#### `GET /api/courses/{courseId}/exam`

Check if the student can start the exam (no attempt is created here).

```json
// Response 200
{
  "status": "success",
  "data": {
    "is_enrolled": true,
    "lessons_completed": true,
    "exam": {
      "duration_minutes": 60,
      "questions_count": 20,
      "is_published": true
    },
    "attempt": null,
    "can_start": true
  }
}
```

`can_start` is `false` when:
- Student is not enrolled, OR
- Lessons are not all completed, OR
- Exam is not published, OR
- Student already has a finalized attempt.

#### `POST /api/courses/{courseId}/exam/start`

Creates or resumes an attempt. Returns the randomized question set for **this attempt only** (questions are locked on start).

```json
// Response 200
{
  "status": "success",
  "message": "تم بدء الامتحان.",
  "data": {
    "attempt": {
      "id": 7,
      "status": "in_progress",
      "started_at": "2026-06-13T10:05:00+00:00",
      "ends_at": "2026-06-13T11:05:00+00:00",
      "remaining_seconds": 3600,
      "score": null,
      "timer_channel": "exam-attempt.7"
    },
    "questions": [
      {
        "id": 101,
        "text": "What is a service provider?",
        "options": [
          { "id": 501, "text": "Option A" },
          { "id": 502, "text": "Option B" },
          { "id": 503, "text": "Option C" },
          { "id": 504, "text": "Option D" }
        ]
      }
    ]
  }
}
```

> **Important:** Options have no `is_correct` field in the response — answer correctness is evaluated server-side only.

#### `POST /api/courses/{courseId}/exam/answers` — Autosave (one answer at a time)

```json
// Request body
{
  "exam_question_id": 101,
  "exam_question_option_id": 502
}

// Response 200
{ "status": "success", "message": "تم حفظ الإجابة." }
```

Call this after the student selects an option. Safe to call on every change — the backend does `updateOrCreate`.

#### `POST /api/courses/{courseId}/exam/submit` — Final Submit

```json
// Request body (optional — last-chance batch submission)
{
  "answers": [
    { "exam_question_id": 101, "exam_question_option_id": 502 },
    { "exam_question_id": 102, "exam_question_option_id": 505 }
  ]
}

// Response 200
{
  "status": "success",
  "message": "تم تسليم الامتحان واحتساب التقييم.",
  "data": {
    "attempt": {
      "id": 7,
      "status": "submitted",
      "submitted_at": "2026-06-13T10:45:00+00:00",
      "remaining_seconds": 0,
      "score": 75.0
    },
    "result": {
      "course_id": 3,
      "lessons_percentage": "88.00",
      "exam_percentage": "70.00",
      "final_score": "75.40",
      "weights": { "lessons": 30, "exam": 70 },
      "passed": true,
      "eligible_for_certificate": true
    }
  }
}
```

> `eligible_for_certificate: true` means `final_score >= 60`. Use this field to decide whether to show the "Get Certificate" button immediately after submission — no extra API call needed.

#### `GET /api/courses/{courseId}/exam/result`

Fetch the result at any time after the exam is finalized (for result pages navigated to later).

```json
// Response 200
{
  "status": "success",
  "data": {
    "course_id": 3,
    "lessons_percentage": "88.00",
    "exam_percentage": "70.00",
    "final_score": "75.40",
    "weights": { "lessons": 30, "exam": 70 },
    "passed": true,
    "eligible_for_certificate": true
  }
}
```

---

### 5.5 Certificate Endpoints

#### `GET /api/courses/{courseId}/certificate`

Returns the issued certificate, or 404 with eligibility details if not yet issued.

```json
// ✅ Certificate exists (200)
{
  "status": "success",
  "message": "success",
  "data": {
    "id": 1,
    "certificate_code": "CERT-3-12-AB4XYZ",
    "level": "good",
    "issued_at": "2026-06-13T10:46:00.000000Z",
    "student": { "id": 12, "name": "Ahmad Al-Sayed" },
    "course": { "id": 3, "title": "Introduction to Laravel" },
    "download_url": "http://127.0.0.1:8000/api/courses/3/certificate/download"
  }
}

// ❌ Not yet issued (404)
{
  "status": "not_issued",
  "message": "لم تُصدر شهادة لهذا الكورس بعد.",
  "data": null,
  "eligibility": {
    "eligible": true,
    "reason": null,
    "final_score": 75.4
  }
}

// ❌ Not issued + ineligible (404)
{
  "status": "not_issued",
  "message": "لم تُصدر شهادة لهذا الكورس بعد.",
  "data": null,
  "eligibility": {
    "eligible": false,
    "reason": "معدلك الكلي (45.0%) أقل من الحد الأدنى للحصول على الشهادة (60%).",
    "final_score": 45.0
  }
}
```

#### `POST /api/courses/{courseId}/certificate`

Request issuance. No request body required. **Idempotent.**

```json
// ✅ First-time issuance (201)
{
  "status": "success",
  "message": "تم إصدار الشهادة بنجاح. يمكنك تحميلها الآن.",
  "data": {
    "id": 1,
    "certificate_code": "CERT-3-12-AB4XYZ",
    "level": "good",
    "issued_at": "2026-06-13T10:46:00.000000Z",
    "student": { "id": 12, "name": "Ahmad Al-Sayed" },
    "course": { "id": 3, "title": "Introduction to Laravel" },
    "download_url": "http://127.0.0.1:8000/api/courses/3/certificate/download"
  }
}

// ✅ Already issued (200 — same data shape, different message)
{
  "status": "success",
  "message": "الشهادة مُصدرة مسبقاً.",
  "data": { ... }
}

// ❌ Ineligible (422)
{
  "message": "لم تقم باجتياز الامتحان النهائي بعد."
}
// or
{
  "message": "معدلك الكلي (45.0%) أقل من الحد الأدنى للحصول على الشهادة (60%)."
}
```

#### `GET /api/courses/{courseId}/certificate/download`

Returns a **PDF binary** (not JSON). See [Section 9 — PDF Download](#9-pdf-download) for the complete implementation.

```
HTTP 200
Content-Type: application/pdf
Content-Disposition: attachment; filename="certificate-CERT-3-12-AB4XYZ.pdf"

<binary PDF data>
```

```json
// ❌ No certificate issued yet (404 JSON)
{
  "status": "error",
  "message": "لا توجد شهادة لتحميلها. قم بطلب إصدار الشهادة أولاً."
}
```

---

## 6. TypeScript Interfaces

```typescript
// ─── Auth ────────────────────────────────────────────────────────────────────

interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'publisher' | 'user';
}

// ─── Exam ────────────────────────────────────────────────────────────────────

interface ExamAttempt {
  id: number;
  status: 'in_progress' | 'submitted' | 'expired';
  started_at: string;        // ISO 8601
  ends_at: string;           // ISO 8601
  submitted_at: string | null;
  remaining_seconds: number; // 0 when finalized
  score: number | null;
  timer_channel: string;     // e.g. "exam-attempt.7"
}

interface ExamOption {
  id: number;
  text: string;
  // NOTE: no `is_correct` field — server-evaluated only
}

interface ExamQuestion {
  id: number;
  text: string;
  options: ExamOption[];
}

// ─── Result ──────────────────────────────────────────────────────────────────

interface CourseResult {
  course_id: number;
  lessons_percentage: string;      // "88.00"
  exam_percentage: string;         // "70.00"
  final_score: string;             // "75.40"
  weights: { lessons: 30; exam: 70 };
  passed: boolean;                 // final_score >= 50
  eligible_for_certificate: boolean; // final_score >= 60
}

// ─── Certificate ─────────────────────────────────────────────────────────────

type CertificateLevel = 'average' | 'good' | 'excellent';

interface Certificate {
  id: number;
  certificate_code: string;
  level: CertificateLevel;
  issued_at: string;   // ISO 8601
  student: { id: number; name: string };
  course: { id: number; title: string };
  download_url: string;
}

// ─── Eligibility (from GET /certificate 404 body) ────────────────────────────

interface EligibilityStatus {
  eligible: boolean;
  reason: string | null;
  final_score: number | null;
}

// ─── API response wrappers ───────────────────────────────────────────────────

interface ApiSuccess<T> {
  status: 'success';
  message: string;
  data: T;
}

interface CertificateNotIssued {
  status: 'not_issued';
  message: string;
  data: null;
  eligibility: EligibilityStatus;
}
```

---

## 7. Implementation Guide

### Axios setup (recommended)

```typescript
// api/client.ts
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
});

// Attach token from wherever you store it
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export default api;
```

### Certificate service layer

```typescript
// api/certificateService.ts
import api from './client';
import type { Certificate, EligibilityStatus } from '../types';

export interface CertificateStatusResult =
  | { issued: true; certificate: Certificate }
  | { issued: false; eligibility: EligibilityStatus };

/** GET /courses/{courseId}/certificate */
export async function getCertificateStatus(courseId: number): Promise<CertificateStatusResult> {
  try {
    const { data } = await api.get<{ data: Certificate }>(`/courses/${courseId}/certificate`);
    return { issued: true, certificate: data.data };
  } catch (err: any) {
    if (err.response?.status === 404 && err.response.data?.status === 'not_issued') {
      return { issued: false, eligibility: err.response.data.eligibility };
    }
    throw err;
  }
}

/** POST /courses/{courseId}/certificate */
export async function requestCertificate(courseId: number): Promise<Certificate> {
  const { data } = await api.post<{ data: Certificate }>(`/courses/${courseId}/certificate`);
  return data.data;
}
```

### Result page — show certificate section

```typescript
// After exam submit, you already have `result.eligible_for_certificate` in the response.
// Use it to decide what to render:

function ExamResultPage({ courseId, result }: { courseId: number; result: CourseResult }) {

  // If eligible, immediately try to render the certificate section.
  // The certificate may already be issued (the backend auto-issues on exam submit for eligible scores).
  return (
    <div>
      <ScoreCard result={result} />

      {result.eligible_for_certificate ? (
        <CertificateSection courseId={courseId} />
      ) : (
        <p>
          Your score ({result.final_score}%) is below the certificate threshold (60%).
          {result.passed ? ' You passed the course but did not qualify for a certificate.' : ''}
        </p>
      )}
    </div>
  );
}
```

### Certificate section component

```typescript
import { useState, useEffect } from 'react';
import { getCertificateStatus, requestCertificate } from '../api/certificateService';
import { downloadCertificate } from '../api/certificateDownload';
import type { Certificate } from '../types';

function CertificateSection({ courseId }: { courseId: number }) {
  const [certificate, setCertificate] = useState<Certificate | null>(null);
  const [loading, setLoading] = useState(true);
  const [requesting, setRequesting] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getCertificateStatus(courseId).then((result) => {
      if (result.issued) setCertificate(result.certificate);
    }).finally(() => setLoading(false));
  }, [courseId]);

  async function handleRequest() {
    setRequesting(true);
    setError(null);
    try {
      const cert = await requestCertificate(courseId);
      setCertificate(cert);
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'An error occurred.');
    } finally {
      setRequesting(false);
    }
  }

  async function handleDownload() {
    if (!certificate) return;
    setDownloading(true);
    try {
      await downloadCertificate(courseId, certificate.certificate_code);
    } catch (err: any) {
      setError('PDF download failed. Please try again.');
    } finally {
      setDownloading(false);
    }
  }

  if (loading) return <Spinner />;

  if (!certificate) {
    return (
      <div>
        <p>You are eligible for a certificate!</p>
        {error && <p className="error">{error}</p>}
        <button onClick={handleRequest} disabled={requesting}>
          {requesting ? 'Issuing...' : 'Get My Certificate'}
        </button>
      </div>
    );
  }

  return (
    <div>
      <h2>Your Certificate</h2>
      <p>Code: <strong>{certificate.certificate_code}</strong></p>
      <p>Level: <strong>{certificate.level}</strong></p>
      <p>Issued: {new Date(certificate.issued_at).toLocaleDateString()}</p>
      {error && <p className="error">{error}</p>}
      <button onClick={handleDownload} disabled={downloading}>
        {downloading ? 'Preparing PDF...' : 'Download PDF'}
      </button>
    </div>
  );
}
```

---

## 8. Real-time Notifications (WebSocket)

The backend sends two notifications relevant to the certificate flow via **Laravel Reverb** (WebSocket).

### Setup (Laravel Echo + Pusher JS)

```bash
npm install laravel-echo pusher-js
```

```typescript
// echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'eduquestkey',            // matches REVERB_APP_KEY in .env
  wsHost: '127.0.0.1',
  wsPort: 8080,
  wssPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
});

export default echo;
```

> **Note:** The Reverb server must be running (`php artisan reverb:start`) and the queue worker must be running (`php artisan queue:listen`) for events to arrive. WebSocket events use a **public channel** (`user.{userId}`) — no channel auth is needed.

### Listening for certificate-related notifications

```typescript
// In your component or global notification handler:
import echo from './echo';

function listenForCertificateNotifications(userId: number, onCertificate: (data: any) => void) {
  echo.channel(`user.${userId}`)
    .listen('notification.new', (event: any) => {

      if (event.type === 'exam_result') {
        // Fired immediately after exam submit/expire.
        // event.data = { course_id, final_score, passed }
        console.log('Exam result:', event.data.final_score);
      }

      if (event.type === 'certificate') {
        // Fired when the certificate is auto-issued (eligible students only).
        // event.data = { course_id, certificate_code, level }
        onCertificate(event.data);
      }
    });
}
```

**Event payload shapes:**

```typescript
// type === 'exam_result'
interface ExamResultNotification {
  type: 'exam_result';
  title: string;
  body: string;
  data: {
    course_id: number;
    final_score: number;
    passed: boolean;
  };
}

// type === 'certificate'
interface CertificateNotification {
  type: 'certificate';
  title: string;
  body: string;
  data: {
    course_id: number;
    certificate_code: string;
    level: CertificateLevel;
  };
}
```

**When `certificate` notification arrives:**
- The certificate already exists in the database at this point.
- Call `GET /api/courses/{course_id}/certificate` to fetch full certificate data and show the download button.
- Or prompt the student to navigate to the course result page.

---

## 9. PDF Download

The download endpoint returns a **binary PDF**, not JSON. A plain `<a href="...">` tag won't work because the Authorization header won't be sent.

```typescript
// api/certificateDownload.ts

/**
 * Fetches the certificate PDF and triggers a browser file download.
 * Must be called with a Bearer token in the Authorization header.
 */
export async function downloadCertificate(
  courseId: number,
  certificateCode: string
): Promise<void> {
  const token = localStorage.getItem('auth_token');

  const response = await fetch(
    `http://127.0.0.1:8000/api/courses/${courseId}/certificate/download`,
    {
      method: 'GET',
      headers: { Authorization: `Bearer ${token}` },
    }
  );

  if (!response.ok) {
    // The backend returns JSON on error even for this endpoint
    const err = await response.json();
    throw new Error(err.message ?? 'PDF download failed');
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `certificate-${certificateCode}.pdf`;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}
```

**With Axios (if your project uses it consistently):**

```typescript
import api from './client'; // axios instance with auth interceptor

export async function downloadCertificateAxios(
  courseId: number,
  certificateCode: string
): Promise<void> {
  const response = await api.get(`/courses/${courseId}/certificate/download`, {
    responseType: 'blob',
  });

  const url = URL.createObjectURL(
    new Blob([response.data], { type: 'application/pdf' })
  );
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `certificate-${certificateCode}.pdf`;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}
```

**PDF characteristics:**
- Paper: A4 landscape
- Design: Formal certificate with border frame, student name, course title, certificate code, issue date, level badge
- Font: DejaVu Sans (embedded — no system font dependency)
- Filename: `certificate-{certificate_code}.pdf`

---

## 10. Error Handling Reference

### HTTP Status Codes

| Status | Scenario | Action |
|---|---|---|
| `200` | Success / certificate already issued | Show data |
| `201` | Certificate just issued for the first time | Show success state + download button |
| `401` | Not authenticated / token expired | Clear token → redirect to login |
| `403` | Role mismatch (not a student) | Show access denied |
| `404` (`not_issued`) | `GET /certificate` — no cert yet | Check `eligibility.eligible` |
| `404` (`error`) | `GET /certificate/download` — no cert | Should not occur if UI flow is correct |
| `422` | Business rule violation | Show `response.data.message` to user |
| `500` | Server error | Generic error message + retry button |

### 422 messages the user may see

| Scenario | Message |
|---|---|
| Exam not taken | `"لم تقم باجتياز الامتحان النهائي بعد."` |
| Score too low | `"معدلك الكلي (X%) أقل من الحد الأدنى للحصول على الشهادة (60%)."` |
| No result record | `"لا يوجد تقييم نهائي مسجّل لك في هذا الكورس."` |

### Axios interceptor for global auth handling

```typescript
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
```

---

## 11. State Machine

The certificate section of your UI has the following states:

```
                        ┌─────────────────────────────────────────┐
                        │             LOADING                      │
                        │  (GET /courses/{id}/exam/result or       │
                        │   GET /courses/{id}/certificate)         │
                        └──────────────────┬──────────────────────┘
                                           │
                  ┌────────────────────────┼─────────────────────────┐
                  │                        │                         │
                  ▼                        ▼                         ▼
      ┌──────────────────┐    ┌──────────────────────┐   ┌───────────────────┐
      │   NOT_ELIGIBLE   │    │   ELIGIBLE_NO_CERT   │   │  CERT_ISSUED      │
      │                  │    │                      │   │                   │
      │ final_score < 60 │    │ final_score ≥ 60 AND │   │ Certificate in DB │
      │                  │    │ no certificate yet   │   │                   │
      │ Show score +     │    │                      │   │ Show cert card +  │
      │ "not eligible"   │    │ Show "Get Cert" btn  │   │ download button   │
      └──────────────────┘    └──────────┬───────────┘   └────────┬──────────┘
                                         │                        │
                               User clicks "Get Certificate"      │
                                         │                        │
                              ┌──────────▼───────────┐           │
                              │     REQUESTING       │           │
                              │ POST /certificate    │           │
                              └──────────┬───────────┘           │
                                         │                        │
                              ┌──────────▼───────────┐           │
                              │    CERT_ISSUED       │───────────┘
                              │ (201 first issue)    │
                              └──────────────────────┘
                                         │
                              User clicks "Download PDF"
                                         │
                              ┌──────────▼───────────┐
                              │    DOWNLOADING       │
                              │ GET /download (blob) │
                              └──────────────────────┘
```

---

## Quick Reference Card

```
LOGIN                     POST /api/login
ENROLL                    POST /api/courses/{id}/enroll
GET LESSONS               GET  /api/courses/{id}/lessons
MARK WATCHED              POST /api/courses/{id}/lessons/{videoId}/watch
EXAM INFO                 GET  /api/courses/{id}/exam
START EXAM                POST /api/courses/{id}/exam/start
AUTOSAVE ANSWER           POST /api/courses/{id}/exam/answers
SUBMIT EXAM               POST /api/courses/{id}/exam/submit
GET RESULT                GET  /api/courses/{id}/exam/result
─────────────────────────────────────────────────────────
GET CERTIFICATE STATUS    GET  /api/courses/{id}/certificate
REQUEST CERTIFICATE       POST /api/courses/{id}/certificate
DOWNLOAD PDF              GET  /api/courses/{id}/certificate/download
```

**Key field to watch:** `result.eligible_for_certificate` (boolean) — returned by both `/exam/submit` and `/exam/result`. Use it to gate the certificate UI without an extra API call.
