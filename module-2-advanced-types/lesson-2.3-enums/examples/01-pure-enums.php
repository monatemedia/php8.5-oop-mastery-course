# Lesson 2.3 — Enums (PHP 8.1+)
> **Module 2: Advanced Types & Enums** · PHP 8.5 OOP Mastery Course
> ✅ Available from PHP 8.1 — works in 8.1, 8.2, 8.3, and 8.4.

---

## 📁 Lesson Folder Structure

```
lesson-2.3-enums/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-pure-enums.php                  ← Unit enums — named cases, no value
│   ├── 02-backed-enums.php                ← String and integer backing
│   ├── 03-enum-methods-and-constants.php  ← Behaviour inside enums
│   ├── 04-enums-and-interfaces.php        ← Enums implementing interfaces
│   └── 05-from-tryfrom-and-match.php      ← Safe parsing + exhaustiveness
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php
│   └── solution.php
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — What Problem Do Enums Solve?

Before PHP 8.1, the standard approach to representing a fixed set of values was either class constants or plain strings:

```php
// ❌ The magic-string problem — before enums
class Order {
    public string $status = 'pending';

    public function confirm(): void {
        $this->status = 'confirmed'; // Typo? 'confirmd'? No error.
    }
}

// Caller code — no guarantee this is a valid status
processOrder('panding'); // Silently accepted, logic breaks later
```

The problems:
- **Typos compile silently.** `'panding'` is just another string.
- **No discoverability.** IDEs cannot tell you the valid values.
- **No type safety.** Any string passes a `string` type hint.
- **No behaviour.** You cannot attach methods or labels to string constants.

Enums fix all four problems by making a closed set of values into a **first-class type**:

```php
enum OrderStatus {
    case Pending;
    case Confirmed;
    case Shipped;
    case Cancelled;
}

class Order {
    public OrderStatus $status = OrderStatus::Pending;

    public function confirm(): void {
        $this->status = OrderStatus::Confirmed; // Typo → compile error
    }
}

function processOrder(OrderStatus $status): void { ... }

processOrder(OrderStatus::Pending);   // ✅
processOrder('panding');              // TypeError — not an OrderStatus
```

---

## 2 — Pure (Unit) Enums

A **pure enum** (also called a unit enum) declares named cases with no attached value. Each case is its own singleton object.

```php
enum Direction {
    case North;
    case South;
    case East;
    case West;
}
```

**Key facts about pure enum cases:**
- Each case is an object — `Direction::North` is an instance of `Direction`.
- All cases of the same enum are `instanceof` that enum.
- Cases cannot be instantiated with `new` — you access them via `EnumName::CaseName`.
- Pure enum cases have no backing value — `Direction::North->value` is a fatal error.
- Two cases of the same enum are equal only if they are the same case: `Direction::North === Direction::North` is `true`; `Direction::North === Direction::South` is `false`.

```php
$dir = Direction::North;

var_dump($dir instanceof Direction); // true
var_dump($dir === Direction::North); // true
var_dump($dir === Direction::South); // false
var_dump($dir->name);                // "North" — every case has a name property
```

---

## 3 — Backed Enums

A **backed enum** attaches a scalar value (string or int) to each case. The backing type is declared after the enum name with `:`.

```php
// String-backed enum
enum Suit: string {
    case Hearts   = 'H';
    case Diamonds = 'D';
    case Clubs    = 'C';
    case Spades   = 'S';
}

// Integer-backed enum
enum Priority: int {
    case Low    = 1;
    case Medium = 5;
    case High   = 10;
}
```

**Rules for backed enums:**
- Every case must have a value of the declared backing type.
- Values must be unique within the enum.
- String values can be any string; integer values can be any integer (not necessarily sequential).
- Backed cases have both a `->name` property (the case name) and a `->value` property (the backing value).

```php
$suit = Suit::Hearts;
echo $suit->name;   // "Hearts"
echo $suit->value;  // "H"
```

---

## 4 — `from()` and `tryFrom()`

Backed enums provide two static methods for converting a scalar value back to an enum case:

### `from()` — throws if the value is not found

```php
$suit = Suit::from('H');     // Returns Suit::Hearts
$suit = Suit::from('X');     // ValueError: 'X' is not a valid backing value for enum Suit
```

### `tryFrom()` — returns `null` if the value is not found

```php
$suit = Suit::tryFrom('H');  // Returns Suit::Hearts
$suit = Suit::tryFrom('X');  // Returns null — no exception
```

**When to use which:**
- `from()` — when the value is expected to be valid (e.g. reading from your own database). Invalid input is genuinely unexpected, so a crash is appropriate.
- `tryFrom()` — when the value comes from untrusted input (API payload, form data, CSV). Use it and handle `null` explicitly.

```php
// Safe parsing from untrusted input
$raw = $request->get('priority');
$priority = Priority::tryFrom((int) $raw);

if ($priority === null) {
    throw new \InvalidArgumentException("Invalid priority: {$raw}");
}
```

---

## 5 — Enum Methods and Constants

Enums can contain methods and constants — making them genuinely behavioural, not just labels.

```php
enum Suit: string {
    case Hearts   = 'H';
    case Diamonds = 'D';
    case Clubs    = 'C';
    case Spades   = 'S';

    const DEFAULT = self::Hearts;

    public function label(): string {
        return match($this) {
            Suit::Hearts   => '♥ Hearts',
            Suit::Diamonds => '♦ Diamonds',
            Suit::Clubs    => '♣ Clubs',
            Suit::Spades   => '♠ Spades',
        };
    }

    public function colour(): string {
        return match($this) {
            Suit::Hearts, Suit::Diamonds => 'red',
            Suit::Clubs,  Suit::Spades   => 'black',
        };
    }

    public function isRed(): bool {
        return $this->colour() === 'red';
    }
}

echo Suit::Hearts->label();   // "♥ Hearts"
echo Suit::Spades->colour();  // "black"
echo Suit::DEFAULT->name;     // "Hearts"
```

**Rules for enum methods:**
- Methods can be `public`, `protected`, or `private`.
- Methods can reference `$this` — which is the current case.
- Methods cannot be `abstract` (enums are not abstract classes).
- Static methods are allowed — useful for factory methods.
- Enums cannot have constructor methods; use `cases()` or static methods instead.

---

## 6 — Implementing Interfaces on Enums

Enums can implement interfaces. This is powerful because it means enum cases can be used wherever the interface is expected:

```php
interface HasLabel {
    public function label(): string;
}

enum Status: string implements HasLabel {
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';

    public function label(): string {
        return match($this) {
            Status::Active   => '✅ Active',
            Status::Inactive => '⏸ Inactive',
            Status::Banned   => '🚫 Banned',
        };
    }
}

function printLabel(HasLabel $item): void {
    echo $item->label() . "\n";
}

printLabel(Status::Active);   // "✅ Active"
printLabel(Status::Banned);   // "🚫 Banned"
```

---

## 7 — Enums as Type Hints

Enum types work everywhere a class or interface name works in a type hint:

```php
// Parameter type
function ship(OrderStatus $status): void { ... }

// Return type
function getDefaultStatus(): OrderStatus {
    return OrderStatus::Pending;
}

// Nullable
function findStatus(?OrderStatus $status): string {
    return $status?->name ?? 'none';
}

// Union
function process(OrderStatus|null $status): void { ... }

// Property type (and with PHP 8.4 hooks)
class Order {
    public OrderStatus $status = OrderStatus::Pending;
}
```

---

## 8 — Enums in `match` — Exhaustiveness

`match` expressions with enums are **exhaustive** if you cover every case. A static analyser (PHPStan, Psalm) will warn you if a case is missing. The PHP runtime throws `UnhandledMatchError` if no arm matches.

```php
function describeStatus(OrderStatus $status): string {
    return match($status) {
        OrderStatus::Pending   => "Waiting for confirmation.",
        OrderStatus::Confirmed => "Confirmed, being prepared.",
        OrderStatus::Shipped   => "On the way.",
        OrderStatus::Cancelled => "This order was cancelled.",
        // If you add a new case (e.g. Refunded) and forget it here,
        // UnhandledMatchError is thrown at runtime.
        // A static analyser catches this at compile time.
    };
}
```

This is one of the key benefits of enums over string constants — adding a new case forces you (or your analyser) to handle it everywhere.

---

## 9 — `cases()` — Listing All Cases

Every enum exposes a static `cases()` method that returns an array of all cases:

```php
$allSuits = Suit::cases();
// Returns [Suit::Hearts, Suit::Diamonds, Suit::Clubs, Suit::Spades]

foreach (Suit::cases() as $suit) {
    echo $suit->name . ': ' . $suit->value . "\n";
}
```

This is useful for building dropdowns, validation lists, or iterating over all valid values.

---

## 10 — What Enums Cannot Do

| Limitation | Notes |
|-----------|-------|
| Cannot be extended | Enums cannot use `extends` |
| Cannot be instantiated with `new` | Use `EnumName::CaseName` |
| Cannot have instance properties | Only `name` and `value` (backed) exist |
| Cannot have a constructor | No `__construct()` |
| Pure enum cases have no `->value` | Accessing `->value` on a pure enum is a fatal error |
| Cannot implement abstract classes | Enums can implement interfaces, not extend classes |
| Backed enum values must be unique | Duplicate backing values cause a fatal error |

---

## 11 — Quick Reference

```php
// Pure enum
enum Color { case Red; case Green; case Blue; }

// Backed enum — string
enum Status: string {
    case Active   = 'active';
    case Inactive = 'inactive';
}

// Backed enum — integer
enum Priority: int { case Low = 1; case High = 10; }

// Access
$s = Status::Active;
echo $s->name;              // "Active"
echo $s->value;             // "active"

// Convert scalar → enum
$s = Status::from('active');        // Status::Active
$s = Status::tryFrom('unknown');    // null

// All cases
$all = Status::cases();             // [Status::Active, Status::Inactive]

// Type hint
function process(Status $s): void { }

// match
$label = match($s) {
    Status::Active   => 'Active',
    Status::Inactive => 'Inactive',
};

// instanceof
var_dump($s instanceof Status);     // true

// Implements interface
interface HasLabel { public function label(): string; }
enum Status: string implements HasLabel {
    case Active = 'active';
    public function label(): string { return ucfirst($this->value); }
}

// Constants in enums
enum Status: string {
    case Active = 'active';
    const DEFAULT = self::Active;
}
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 4 (`from` vs `tryFrom`) and 8 (match exhaustiveness)
- [ ] Run and study `examples/01-pure-enums.php`
- [ ] Run and study `examples/02-backed-enums.php`
- [ ] Run and study `examples/03-enum-methods-and-constants.php`
- [ ] Run and study `examples/04-enums-and-interfaces.php`
- [ ] Run and study `examples/05-from-tryfrom-and-match.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **2.4 — Anonymous Classes** — inline implementations for testing and one-off use.*