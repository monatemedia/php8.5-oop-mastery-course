<?php
declare(strict_types=1); // Task 1

/**
 * CHALLENGE SOLUTION — Lesson 2.2: PHP 8.4 Property Hooks
 * ─────────────────────────────────────────────────────────
 * PHP 8.5.
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Zero getter methods, zero setter methods
 *   2. $slug is a virtual property (no default, no set hook)
 *   3. $publishedAt set hook accepts string|DateTimeImmutable
 *   4. $tags set hook: lowercase, trim, deduplicate, sort
 *   5. Article interface uses { get; } and { get; set; } syntax
 *   6. All calling code uses direct property access
 */


// Task 7 — Interface with property hook syntax
interface Article {
    public string               $title  { get; set; }
    public string               $author { get; set; }
    public string               $slug   { get; }        // read-only contract
    /** @var string[] */
    public array                $tags   { get; set; }
}


class BlogArticle implements Article {

    // Task 2 — title: trim on write (arrow set hook)
    public string $title = '' {
        set(string $value) => $this->title = trim($value);
    }

    // Task 2 — body: trim on write
    public string $body = '' {
        set(string $value) => $this->body = trim($value);
    }

    // Task 3 — author: trim + title-case on write
    public string $author = '' {
        set(string $value) => $this->author = ucwords(strtolower(trim($value)));
    }

    // Task 4 — publishedAt: accepts string OR DateTimeImmutable, stores only DT
    // The set parameter type (string|\DateTimeImmutable) is WIDER than the
    // property's declared type (?DateTimeImmutable) — this is legal in PHP 8.4.
    public ?\DateTimeImmutable $publishedAt = null {
        set(string|\DateTimeImmutable $value) {
            if (is_string($value)) {
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
                if ($parsed === false) {
                    throw new \InvalidArgumentException(
                        "Invalid date format. Expected Y-m-d."
                    );
                }
                $this->publishedAt = $parsed;
            } else {
                $this->publishedAt = $value;
            }
        }
    }

    // Task 5 — tags: lowercase, trim, deduplicate, sort on write
    public array $tags = [] {
        set(array $value) {
            $cleaned = array_map(fn(string $t) => strtolower(trim($t)), $value);
            $cleaned = array_filter($cleaned, fn(string $t) => $t !== '');
            $cleaned = array_unique($cleaned);
            sort($cleaned);
            $this->tags = array_values($cleaned);
        }
    }

    // Task 6 — slug: virtual property, derived from $title, no storage
    // No default value + no set hook = virtual (read-only, computed on every read)
    public string $slug {
        get {
            $slug = strtolower(trim($this->title));
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            return trim($slug, '-');
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 8 — All calling code uses direct property access
// ─────────────────────────────────────────────────────────────────────────────

$article1 = new BlogArticle();
$article1->title       = '  php 8.4 is here  ';
$article1->body        = 'PHP 8.4 introduces property hooks, which replace boilerplate getters and setters with concise inline hook declarations.';
$article1->author      = '  alice smith  ';
$article1->publishedAt = '2024-11-21';
$article1->tags        = ['  PHP  ', 'New-Features', ' HOOKS ', 'php', ''];

$article2 = new BlogArticle();
$article2->title  = 'OOP Design Patterns';
$article2->body   = 'Design patterns are reusable solutions to common problems in software design. They provide templates for solving challenges that arise repeatedly.';
$article2->author = 'BOB JONES';
$article2->tags   = ['OOP', 'Design-Patterns', 'php', 'OOP'];
// No publishedAt — remains null

// printArticle now uses direct property access
function printArticle(BlogArticle $article): void {
    $published   = $article->publishedAt?->format('Y-m-d') ?? '(not yet published)';
    $bodyPreview = substr($article->body, 0, 60) . '...';
    $tagsStr     = implode(', ', $article->tags);

    echo "Title:  {$article->title}\n";
    echo "Author: {$article->author}\n";
    echo "Slug:   {$article->slug}\n";   // Virtual property
    echo "Tags:   {$tagsStr}\n";
    echo "Published: {$published}\n";
    echo "Body preview: {$bodyPreview}\n";
}

echo "=== Article 1 ===\n";
printArticle($article1);

echo "\n=== Article 2 ===\n";
printArticle($article2);


// Task 7 — Type-safe function using the Article interface
function displayArticle(Article $article): void {
    $tagsStr = implode(', ', $article->tags);
    echo "[ARTICLE] {$article->slug} by {$article->author} ({$tagsStr})\n";
}

echo "\n=== Type-safe function ===\n";
displayArticle($article1);
displayArticle($article2);


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] declare(strict_types=1) is the first statement?\n";
echo "[ ] Zero getter methods in BlogArticle?\n";
echo "[ ] Zero setter methods in BlogArticle?\n";
echo "[ ] \$slug has no default value and no set hook (virtual)?\n";
echo "[ ] \$publishedAt set hook accepts string|\\DateTimeImmutable?\n";
echo "[ ] \$tags set hook: lowercase, trim, deduplicate, sort — all present?\n";
echo "[ ] Article interface uses { get; } and { get; set; } syntax?\n";
echo "[ ] BlogArticle implements Article?\n";
echo "[ ] All calling code uses \$article->prop instead of \$article->getProp()?\n";
echo "[ ] displayArticle() is typed against Article (the interface)?\n";