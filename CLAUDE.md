# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

EDUQUEST is a Laravel 13 (PHP 8.3) **API-only backend** for an e-learning platform. There is no Blade/server-rendered UI — `routes/web.php` is empty and all functionality lives in `routes/api.php` under the `/api` prefix. Auth is token-based via Laravel Sanctum. Many code comments are in Arabic.

Core domain: users with roles → publishers create **courses** → courses have **course videos** → videos have **video questions** → questions have **options** (one marked `is_correct`) → users submit **video question answers** (auto-graded) → completing a course yields a **course certificate**. **Categories** group courses.

## Commands

```bash
composer dev        # run server + queue listener + log tailer (pail) + vite concurrently
php artisan serve   # API server only
composer test       # clears config cache, then runs the full test suite
php artisan test --filter=SomeTest   # run a single test class/method
php artisan test tests/Feature/ExampleTest.php   # run one file
./vendor/bin/pint   # format code (Laravel Pint / PSR-12)
php artisan migrate # apply migrations (default DB is SQLite)
php artisan db:seed # seed test data (see below)
```

Tests use an in-memory SQLite DB (`phpunit.xml`); they do not touch the dev database. The repo currently only ships the default example tests.

**Seeding / test credentials.** `DatabaseSeeder` calls `UsersTableSeeder` (creates `admin@example.com`, `publisher@example.com`, `user@example.com` — all password `password123`, one per role) then `TestDataSeeder` (a full Category → Course → CourseVideo → two VideoQuestions + options chain, useful for exercising the question/answer flow). These are raw `DB::table()->insert` seeds, not factories.

## Authorization model (important)

Access control is **not** done with Sanctum abilities or Laravel policies/gates. It uses a single custom middleware aliased as `role` in `bootstrap/app.php` → [RoleMiddleware.php](app/Http/Middleware/RoleMiddleware.php). It checks `$request->user()->role` against a variadic list:

```php
Route::middleware('role:admin,publisher')->group(...)   // admin OR publisher
```

Roles are an enum column on `users`: `admin`, `publisher`, `user` (see the users migration). Route groups in [api.php](routes/api.php) layer `auth:sanctum` then `role:...`. When adding endpoints, place them in the correct role group rather than re-checking roles inside controllers — though note several legacy controllers (e.g. [UserController.php](app/Http/Controllers/UserController.php)) still do inline `$request->user()->role !== 'admin'` checks, which is redundant with the middleware.

## Two coexisting architectures (read before editing user code)

The codebase is mid-migration between two styles:

1. **Legacy fat controllers** — most controllers ([AuthController](app/Http/Controllers/AuthController.php), [CourseController](app/Http/Controllers/CourseController.php), the `VideoQuestion*` controllers, the root [UserController](app/Http/Controllers/UserController.php)). These do validation, business logic, and JSON responses all inline, use `App\Models\*`, and return raw `response()->json(...)`. **These are what `routes/api.php` actually wires up.**

2. **Domain-driven layer** under [app/Domain/Users/](app/Domain/Users/) — Actions, DTOs, FormRequests, an Eloquent Repository (+ interface), a `UserResource`, and a *separate* `App\Domain\Users\Models\User`. Driven by [app/Http/Controllers/Users/UserController.php](app/Http/Controllers/Users/UserController.php). This is the intended target pattern (Request → `toDto()` → Action → Resource) but **is not currently referenced by any route** — the routes still point at the legacy `App\Http\Controllers\UserController`.

There are therefore **two `User` models**: `App\Models\User` (used by legacy code + Sanctum tokens, has `HasApiTokens` and relationships) and `App\Domain\Users\Models\User` (used by the Domain layer, no API tokens). Both map to the `users` table. Be deliberate about which you import. New user-related work should follow the Domain pattern; cross-cutting/relationship work generally uses `App\Models\User`.

## Conventions to match

- **Answer grading**: correctness is derived server-side — never trust a client-sent `is_correct`. See [VideoQuestionAnswerController::store](app/Http/Controllers/VideoQuestionAnswerController.php) which looks up the option's `is_correct` and uses `updateOrCreate` keyed on `(user_id, question_id)` so re-answering overwrites.
- **File uploads** (course thumbnails) use the `public` disk under `courses/thumbnails`; updates delete the old file first. Because of multipart/PUT issues, **course and video updates are routed as `POST`** (with `{id}`), not `PUT` — preserve this when adding update routes that accept files.
- **JSON response shape is inconsistent across controllers**: newer ones (VideoQuestion*) wrap as `{ "status": "...", "data": ... }`; older ones return the model/array directly. Match the surrounding controller rather than introducing a third shape.
- **Validation** is done either inline with `$request->validate([...])` or via `Validator::make(...)` returning 422 on failure. The Domain layer uses FormRequests with a `toDto()` method.
- Eager-loading of relations (`with([...])`) is commented out in several `CourseController` methods — leave intentional unless addressing N+1.

## Partially scaffolded features (exist but inert)

- **Notifications/broadcasting.** [NewNotification](app/Events/NewNotification.php) is a `ShouldBroadcast` event on a per-user channel (`user.{id}`) and references `App\Models\Notification`, but **that model does not exist** and [NotificationController](app/Http/Controllers/NotificationController.php) is an empty stub. Nothing is wired into `routes/api.php`. Don't assume the feature works.
- **Course certificates.** The `course_certificates` table, [CourseCertificate](app/Models/CourseCertificate.php) model, and [CourseCertificateController](app/Http/Controllers/CourseCertificateController.php) exist, but no certificate routes are registered in `routes/api.php` — the "completing a course yields a certificate" flow is not actually reachable via the API yet.

## Config notes

Default `DB_CONNECTION` is `sqlite`; queue, cache, and sessions default to the `database` driver in `.env.example`. Run `composer setup` for first-time install (copies `.env`, generates key, migrates, builds assets).

## Docs

[docs/video-questions-api.md](docs/video-questions-api.md) is a hand-written (Arabic) API reference for the video-questions/options/answers endpoints, including request/response examples and the `password123` test login — the most complete endpoint documentation in the repo. Keep it in sync when changing those controllers.
