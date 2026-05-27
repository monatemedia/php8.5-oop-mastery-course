<?php
// TODO Task 1: Add declare(strict_types=1) here

/**
 * CHALLENGE STARTER — Lesson 2.2: PHP 8.4 Property Hooks
 * ─────────────────────────────────────────────────────────
 * PHP 8.5. Read CHALLENGE.md before touching this file.
 *
 * This file uses the traditional getter/setter pattern.
 * Your job is to replace ALL getters and setters with PHP 8.4
 * property hooks while keeping the output identical.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// TODO Task 7: Define interface Article with property hook syntax
// interface Article { ... }


/**
 * BlogArticle — pre-8.4 style with explicit getters/setters.
 * After your refactor: zero get*() methods, zero set*() methods.
 */
class BlogArticle {  // TODO: implements Article

    // TODO: Convert all six properties to use hooks
    private string               $title       = '';
    private string               $body        = '';
    private string               $author      = '';
    private ?\DateTimeImmutable  $publishedAt = null;
    private array                $tags        = [];
    private string               $slug        = '';  // TODO: make this virtual


    // ── Getters ──────────────────────────────────────────────────────────────
    // TODO: Delete all six getter methods after adding hooks

    public function getTitle(): string { return $this->title; }
    public function getBody(): string  { return $this->body; }
    public function getAuthor(): string { return $this->author; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getTags(): array   { return $this->tags; }

    // $slug is derived from $title — currently recomputed in getter
    public function getSlug(): string {
        $slug = strtolower(trim($this->title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }


    // ── Setters ──────────────────────────────────────────────────────────────
    // TODO: Delete all six setter methods after adding hooks

    public function setTitle(string $title): void {
        $this->title = trim($title);
    }

    public function setBody(string $body): void {
        $this->body = trim($body);
    }

    public function setAuthor(string $author): void {
        $this->author = ucwords(strtolower(trim($author)));
    }

    // Accepts a date string OR a DateTimeImmutable object
    public function setPublishedAt(string|\DateTimeImmutable $date): void {
        if (is_string($date)) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($parsed === false) {
                throw new \InvalidArgumentException("Invalid date format. Expected Y-m-d.");
            }
            $this->publishedAt = $parsed;
        } else {
            $this->publishedAt = $date;
        }
    }

    public function setTags(array $tags): void {
        $cleaned = array_map(fn(string $t) => strtolower(trim($t)), $tags);
        $cleaned = array_filter($cleaned, fn(string $t) => $t !== '');
        $cleaned = array_unique($cleaned);
        sort($cleaned);
        $this->tags = array_values($cleaned);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 8: Replace all get*()/set*() calls below with direct property access
// e.g. $article->setTitle('...') → $article->title = '...'
//      $article->getTitle()      → $article->title
// ─────────────────────────────────────────────────────────────────────────────

$article1 = new BlogArticle();
$article1->setTitle('  php 8.4 is here  ');
$article1->setBody('PHP 8.4 introduces property hooks, which replace boilerplate getters and setters with concise inline hook declarations.');
$article1->setAuthor('  alice smith  ');
$article1->setPublishedAt('2024-11-21');
$article1->setTags(['  PHP  ', 'New-Features', ' HOOKS ', 'php', '']);

$article2 = new BlogArticle();
$article2->setTitle('OOP Design Patterns');
$article2->setBody('Design patterns are reusable solutions to common problems in software design. They provide templates for solving challenges that arise repeatedly.');
$article2->setAuthor('BOB JONES');
$article2->setTags(['OOP', 'Design-Patterns', 'php', 'OOP']);
// $article2 has no publishedAt — remains null

function printArticle(BlogArticle $article): void {
    $published = $article->getPublishedAt()
        ? $article->getPublishedAt()->format('Y-m-d')
        : '(not yet published)';
    $bodyPreview = substr($article->getBody(), 0, 60) . '...';
    $tagsStr = implode(', ', $article->getTags());

    echo "Title:  " . $article->getTitle() . "\n";
    echo "Author: " . $article->getAuthor() . "\n";
    echo "Slug:   " . $article->getSlug() . "\n";
    echo "Tags:   {$tagsStr}\n";
    echo "Published: {$published}\n";
    echo "Body preview: {$bodyPreview}\n";
}

echo "=== Article 1 ===\n";
printArticle($article1);

echo "\n=== Article 2 ===\n";
printArticle($article2);

// TODO: After Task 7, add a type-safe function that uses the Article interface:
// function displayArticle(Article $article): void { ... }
// echo "\n=== Type-safe function ===\n";
// displayArticle($article1);
// displayArticle($article2);