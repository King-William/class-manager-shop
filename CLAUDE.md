# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **class/training management backend** built with Hyperf 3.1 (PHP 8.1+) on Swoole. It serves as the API backend for a WeChat Mini Program used by a tutoring school. Teachers manage classes and students via the Mini Program; students auto-register on first WeChat login.

### Tech Stack
- **Framework**: Hyperf 3.1 (coroutine-based PHP microservice framework)
- **Database**: MySQL (via PDO)
- **Cache/Tokens**: Redis
- **Auth**: WeChat Mini Program OAuth (code2session flow)
- **Queues**: Redis-backed async queue
- **Deployment**: Docker / Docker Swarm

## Common Commands

```bash
# Start the server (development)
php bin/hyperf.php start

# Start via Composer script
composer start

# Run tests
composer test

# Run static analysis
composer analyse

# Fix code style
composer cs-fix

# Run Docker Compose
docker-compose up

# Generate model from database
php bin/hyperf.php gen:model -d
```

## Architecture

### Request Flow
```
Client → TokenDecodeMiddleware → AuthMiddleware → Controller → Service → Model/DB
```

1. **TokenDecodeMiddleware** (global) — Extracts Bearer token, verifies against Redis, sets `MemberId` in Context
2. **AuthMiddleware** (global) — Role enforcement; `/api/class/*` routes require `role=1` (teacher)
3. **Controller** — Parameter extraction, basic validation, try/catch → JSON response
4. **Service** — All business logic, database operations, transactions
5. **Model** — Hyperf DB Model (extends `Hyperf\DbConnection\Model\Model`)

### Key Patterns
- **Controller-Service separation**: Controllers only handle HTTP concerns (params, validation, response). All business logic lives in Service classes.
- **Error responses**: `['code' => int, 'msg' => string, 'data' => mixed|null]` — standardized across all endpoints
- **Business exceptions**: `BusinessException` wraps application errors; `AppExceptionHandler` converts them to JSON responses
- **Token storage**: UUID tokens stored in Redis with sliding TTL (7 days); payload contains `uid`, `role`, `issued_at`
- **Dependency injection**: Hyperf DI container with `#[Inject]` annotation on properties
- **Constants**: `ErrorCode` class defines all error codes and messages via `#[Constants]` annotation

### Directory Structure
```
app/
  Controller/       — HTTP controllers (AbstractController base)
  Service/          — Business logic (AuthService, ClassService, TokenService, UserService)
  Middleware/       — TokenDecodeMiddleware, AuthMiddleware
  Model/            — User, ClassModel, ClassStudent (extend app/Model/Model)
  Exception/        — BusinessException, AppExceptionHandler
  Constants/        — ErrorCode
  Listener/         — DB query logger, queue event logger
  Process/          — AsyncQueueConsumer
config/
  routes.php        — Static routes (favicon, index, logout)
  autoload/         — Middleware, databases, redis, cache, server, async_queue
migrations/         — DB schema migrations
test/               — PHPUnit tests (HttpTestCase base class)
```

### Database Schema
- **users** — `id, openid, phone, nickname, gender, age, class_id, role, timestamps`
- **classes** — `id, name, teacher_id, start_date, end_date, class_days, class_start_time, class_end_time, timestamps`
- **class_students** — `id, class_id, student_id, created_at` (unique on class_id+student_id)

## Important Details

### Authentication
- WeChat Mini Program login: POST `/api/auth/wechat-login` with `{"code": "..."}`
- Token generated as UUID v4, stored in Redis key `class:token:{uuid}`
- Token payload: `{uid, role, issued_at}` — no PII stored in token
- Sliding TTL: 7 days, with absolute max lifetime enforced via `issued_at`
- Logout: POST `/api/auth/logout`

### Role-Based Access
- `role=1` = teacher, `role=2` = student
- `/api/class/*` routes require teacher role (enforced by AuthMiddleware)
- User routes (`/api/user/*`) require any authenticated user

### Transactions
- Class creation, student assignment, removal, and addition all use `Db::transaction()` for atomicity
- `ClassService::addStudents()` uses `insertOrIgnore` to handle duplicate enrollment gracefully

### Testing
- Tests extend `HyperfTest\HttpTestCase` which provides `get()`, `post()`, `json()`, `file()`, `request()` helpers
- PHPUnit config: `phpunit.xml.dist` — runs with `APP_ENV=testing`
- Bootstrap: `test/bootstrap.php` — initializes Hyperf container
- Run a single test: `composer test -- tests/Cases/SomeTest.php`

### Configuration
- Environment variables from `.env` (never commit `.env`, use `.env.example` as template)
- Redis auth from `REDIS_AUTH` env var
- WeChat credentials: `WECHAT_MINI_PROGRAM_APP_ID`, `WECHAT_MINI_PROGRAM_APP_SECRET`
- Server listens on `0.0.0.0:9501`
