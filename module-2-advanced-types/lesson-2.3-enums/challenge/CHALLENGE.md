# Code Challenge — Lesson 2.3: Enums (PHP 8.1+)

> **Replace magic string constants across a module with backed enums**

---

## The Brief

You have inherited a small e-commerce notification module that uses plain strings and class constants for status codes, channels, and priority levels — everywhere. Every function that accepts a status or channel accepts `string`, with no guarantee that valid values are passed. Typos, wrong casing, and missing validation have already caused three production bugs this month.

Your job is to replace every magic string constant with properly designed backed enums, add methods to each enum, and update all calling code to use the enum types instead of strings.

---

## What is Wrong With the Starter Code

Open `starter.php`. You will find:

**Problem 1 — Magic string constants scattered across classes**
```php
class NotificationService {
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS   = 'sms';
    const CHANNEL_PUSH  = 'push';
    // ... used as strings everywhere
}
```

**Problem 2 — String parameters with no validation**
```php
function sendNotification(string $channel, string $priority, string $message): void {
    // What if channel is 'EMAL' or priority is 'urgent'? Silent bugs.
}
```

**Problem 3 — Status strings with no behaviour**
```php
$notification->status = 'pending';
// No methods, no label, no colour — just a raw string
```

**Problem 4 — Unsafe parsing from external data**
```php
$channel = $request['channel']; // Could be anything — no validation
```

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Create `NotificationChannel: string`
Cases: `Email = 'email'`, `SMS = 'sms'`, `Push = 'push'`, `Slack = 'slack'`

Add these methods:
- `label(): string` — human-readable name (e.g. `'Email'`, `'SMS'`, `'Push Notification'`, `'Slack'`)
- `supportsRichText(): bool` — `true` for Email and Slack, `false` for SMS and Push
- `maxMessageLength(): int` — Email: 10000, SMS: 160, Push: 256, Slack: 4000

### Task 2 — Create `NotificationPriority: int`
Cases: `Low = 1`, `Normal = 5`, `High = 10`, `Urgent = 99`

Add these methods:
- `label(): string` — e.g. `'Low'`, `'Normal'`, `'High'`, `'🚨 Urgent'`
- `shouldAlert(): bool` — `true` for High and Urgent only
- `retryAttempts(): int` — Low: 1, Normal: 3, High: 5, Urgent: 10

### Task 3 — Create `NotificationStatus: string`
Cases: `Pending = 'pending'`, `Sent = 'sent'`, `Failed = 'failed'`, `Cancelled = 'cancelled'`

Add these methods:
- `label(): string` — e.g. `'⏳ Pending'`, `'✅ Sent'`, `'❌ Failed'`, `'🚫 Cancelled'`
- `isTerminal(): bool` — `true` for Sent, Failed, Cancelled (cannot be changed once reached)
- `canRetry(): bool` — `true` for Failed only

### Task 4 — Update `Notification` class
Replace all string properties with enum-typed properties:
- `$channel` — type `NotificationChannel`
- `$priority` — type `NotificationPriority`
- `$status` — type `NotificationStatus` (default `NotificationStatus::Pending`)

### Task 5 — Update `NotificationService`
- Remove all `const CHANNEL_*` and `const PRIORITY_*` string constants
- Change `send()` parameter types from `string` to the enum types
- Use `tryFrom()` to safely parse channel and priority from the incoming request array
- Use `match` (not `if`/`switch`) wherever you branch on enum values

### Task 6 — Update `NotificationRepository`
- Change `findByChannel(string $channel)` to `findByChannel(NotificationChannel $channel)`
- Change `findByStatus(string $status)` to `findByStatus(NotificationStatus $status)`
- Update the filter logic to use enum identity (`===`) not string comparison

### Task 7 — Update calling code
Replace all string literals with enum cases and all `->getStatus() === 'sent'` comparisons with `=== NotificationStatus::Sent`.

---

## Acceptance Criteria

- [ ] Three backed enums defined with correct cases and backing values
- [ ] All three enums have the required methods
- [ ] `Notification` class uses enum types for all three properties
- [ ] `NotificationService` has no string constants and no string parameters for channel/priority/status
- [ ] `tryFrom()` used for parsing external input — not raw string assignment
- [ ] `match` used for all enum branching — no `if ($x === 'email')` patterns remain
- [ ] `NotificationRepository` filter methods accept enum types, not strings
- [ ] All existing output is preserved exactly

---

## Expected Output

```
=== Sending notifications ===
[Email] 🚨 Urgent: Server is down! (retries: 10)
  Channel: Email | Rich text: YES | Max length: 10000 chars
[SMS] 🔴 High: Disk usage above 90% (retries: 5)
  Channel: SMS | Rich text: NO | Max length: 160 chars
[Push] 📢 Normal: Weekly summary is ready (retries: 3)
  Channel: Push Notification | Rich text: NO | Max length: 256 chars
[Slack] 🟢 Low: Cron job completed (retries: 1)
  Channel: Slack | Rich text: YES | Max length: 4000 chars

=== Status transitions ===
INF-001: ⏳ Pending → ✅ Sent
INF-002: ⏳ Pending → ❌ Failed
INF-002: canRetry=YES → ⏳ Pending → ✅ Sent

=== Repository queries ===
Email notifications: 1
Failed notifications: 0

=== Parsing from external request ===
✓ Parsed channel: Email
✓ Parsed priority: 🔴 High
[Email] 🔴 High: Test (retries: 5)
  Channel: Email | Rich text: YES | Max length: 10000 chars
✗ Invalid channel 'telegram' — valid options: email, sms, push, slack
✗ Invalid priority '7' — valid options: 1, 5, 10, 99
```