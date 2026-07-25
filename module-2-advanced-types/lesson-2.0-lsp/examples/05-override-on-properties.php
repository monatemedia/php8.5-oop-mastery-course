<?php
declare(strict_types=1);

/**
 * Example 05 — #[\Override] on Properties (PHP 8.5)
 * ----------------------------------------------------
 * Run from the course root:
 *   php module-2-advanced-types/lesson-2.0-lsp/examples/05-override-on-properties.php
 *
 * PHP 8.3 introduced #[\Override] for METHODS: it tells the engine "this method
 * is meant to replace one from a parent or interface — fail loudly at compile
 * time if it doesn't."
 *
 * PHP 8.5 extends the same attribute to PROPERTIES.
 *
 * Why this belongs in the LSP lesson:
 *   LSP is about a subtype honouring the contract it inherited. A property
 *   redeclaration is part of that contract. When a child "overrides" a property
 *   that the parent no longer has — because it was renamed, moved, or deleted —
 *   the child does not fail. It quietly declares a NEW property that nothing
 *   reads, and the parent's value is used instead. The behaviour changes; no
 *   error appears. That is an LSP violation that hides in plain sight.
 *
 *   #[\Override] converts that silent drift into a compile-time error.
 *
 * PHP 8.5+ required for this file.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  #[\\Override] on Properties (PHP 8.5)                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The silent failure, without #[\Override]
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: The bug #[\\Override] exists to catch ─────\n\n";

abstract class BaseRepositoryV1
{
    // Imagine a later refactor renames this to $tableName.
    protected string $table = 'generic';

    public function describe(): string
    {
        return static::class . ' reads from table: ' . $this->table;
    }
}

class UserRepositoryV1 extends BaseRepositoryV1
{
    // No #[\Override]. Today this correctly replaces the parent's value.
    protected string $table = 'users';
}

echo (new UserRepositoryV1())->describe() . "\n";
echo "  ✓ Correct today.\n\n";

echo "Now imagine BaseRepository is refactored and \$table becomes \$tableName.\n";
echo "UserRepository still declares \$table — but the parent no longer reads it.\n";
echo "PHP raises NO error. The child simply gains an unused property, and\n";
echo "describe() falls back to the parent's default. Silent behaviour change.\n\n";

// This is that scenario, played out:
abstract class BaseRepositoryV2
{
    protected string $tableName = 'generic';   // ← renamed during a refactor

    public function describe(): string
    {
        return static::class . ' reads from table: ' . $this->tableName;
    }
}

class UserRepositoryV2 extends BaseRepositoryV2
{
    // Nobody updated this line. PHP accepts it without complaint.
    protected string $table = 'users';
}

echo "After the rename, with no #[\\Override]:\n";
echo "  " . (new UserRepositoryV2())->describe() . "\n";
echo "  ✗ Reads 'generic', not 'users' — and nothing warned us.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — #[\Override] on a property, used correctly
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: #[\\Override] applied correctly ───────────\n\n";

abstract class BaseRepository
{
    protected string $table      = 'generic';
    protected int    $cacheTtl   = 60;
    protected bool   $softDelete = false;

    public function describe(): string
    {
        return sprintf(
            '%-22s table=%-12s ttl=%-5d softDelete=%s',
            static::class,
            $this->table,
            $this->cacheTtl,
            $this->softDelete ? 'yes' : 'no'
        );
    }
}

class UserRepository extends BaseRepository
{
    #[\Override]                       // ← "this MUST exist on a parent"
    protected string $table = 'users';

    #[\Override]
    protected int $cacheTtl = 300;

    // $softDelete is not redeclared — the parent's default is inherited as-is.
}

class OrderRepository extends BaseRepository
{
    #[\Override]
    protected string $table = 'orders';

    #[\Override]
    protected bool $softDelete = true;
}

echo (new UserRepository())->describe()  . "\n";
echo (new OrderRepository())->describe() . "\n\n";
echo "Every overridden property is declared as an override. If any parent\n";
echo "property is renamed or removed, these lines become compile errors.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — What the failure looks like
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: The error you now get instead ────────────\n\n";

echo "With #[\\Override] in place, the Part 1 refactor fails immediately:\n\n";
echo "  abstract class BaseRepository {\n";
echo "      protected string \$tableName = 'generic';   // renamed\n";
echo "  }\n\n";
echo "  class UserRepository extends BaseRepository {\n";
echo "      #[\\Override]\n";
echo "      protected string \$table = 'users';         // ← no such parent property\n";
echo "  }\n\n";
echo "  Fatal error: UserRepository::\$table has #[\\Override] attribute,\n";
echo "               but no matching parent property exists\n\n";
echo "A compile-time failure at the exact line that is wrong, instead of a\n";
echo "wrong table name discovered in production three weeks later.\n\n";

// EXPERIMENT: uncomment this block to see the fatal error for yourself.
/*
class BrokenRepository extends BaseRepository
{
    #[\Override]
    protected string $noSuchProperty = 'boom';   // parent has no $noSuchProperty
}
*/


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Methods and properties, side by side
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: #[\\Override] on methods (8.3) and properties (8.5) ──\n\n";

interface Exportable
{
    public function export(): string;
}

abstract class Report implements Exportable
{
    protected string $format    = 'txt';
    protected string $delimiter = "\n";

    public function export(): string
    {
        return "[{$this->format}] export using delimiter " . json_encode($this->delimiter);
    }
}

final class CsvReport extends Report
{
    #[\Override]                        // PHP 8.5 — property override
    protected string $format = 'csv';

    #[\Override]                        // PHP 8.5 — property override
    protected string $delimiter = ',';

    #[\Override]                        // PHP 8.3 — method override
    public function export(): string
    {
        return 'CSV: ' . parent::export();
    }
}

echo (new CsvReport())->export() . "\n\n";
echo "Same attribute, same guarantee, now covering both halves of the contract.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — When to use it
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 5: When to reach for it ─────────────────────\n\n";

echo "Use #[\\Override] on a property when:\n";
echo "  ✓ You are redeclaring a property to change its default value\n";
echo "  ✓ The parent lives in a package or team you do not control\n";
echo "  ✓ The hierarchy is more than one level deep\n";
echo "  ✓ The property drives behaviour (table names, TTLs, feature flags)\n\n";

echo "Do NOT use it when:\n";
echo "  ✗ The property is genuinely new on the child — that is not an override\n";
echo "  ✗ You are only satisfying an interface (interfaces cannot declare\n";
echo "    plain properties; only hooked ones, and those are a different case)\n\n";

echo "The LSP connection: an override you *intended* is a contract you are\n";
echo "deliberately honouring. An override that stopped matching is a contract\n";
echo "you are silently breaking. #[\\Override] is how you tell the two apart.\n";

echo "\n--- Recap ---\n";
echo "#[\\Override] on methods:    PHP 8.3\n";
echo "#[\\Override] on properties: PHP 8.5\n";
echo "Effect: compile-time error if no matching parent member exists.\n";
echo "Cost:   one attribute line. Benefit: refactors fail loudly, not silently.\n";
echo "LSP:    catches the subtype/supertype drift that LSP violations hide in.\n";
