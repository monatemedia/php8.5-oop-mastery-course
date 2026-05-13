<?php
declare(strict_types=1);

/**
 * Example 01 — Defining and Using a Trait
 * -----------------------------------------
 * The basics: what a trait looks like, how to use it, and what happens
 * when two completely unrelated class hierarchies share the same behaviour.
 *
 * Scenario: Several model classes across two different inheritance trees
 * all need slug generation and soft-delete functionality.
 * Neither a shared parent class nor copy-pasting is acceptable.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Defining and Using Traits                          ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Two reusable traits — no inheritance, no hierarchy
// ─────────────────────────────────────────────────────────────────────────────

trait HasSlug {
    public function generateSlug(string $title): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);   // remove special chars
        $slug = preg_replace('/[\s-]+/', '-', $slug);          // spaces/hyphens → single dash
        return trim($slug, '-');
    }

    public function getSlug(): string {
        // Relies on the host class having a 'title' or 'name' property.
        // This works because trait methods have access to $this.
        $source = property_exists($this, 'title') ? $this->title
                : (property_exists($this, 'name') ? $this->name : '');
        return $this->generateSlug($source);
    }
}

trait SoftDeletable {
    private ?\DateTimeImmutable $deletedAt = null;

    public function delete(): void {
        $this->deletedAt = new \DateTimeImmutable();
        echo "[SOFT DELETE] " . get_class($this) . " deleted at "
           . $this->deletedAt->format('Y-m-d H:i:s') . "\n";
    }

    public function restore(): void {
        $this->deletedAt = null;
        echo "[RESTORE] " . get_class($this) . " restored.\n";
    }

    public function isDeleted(): bool {
        return $this->deletedAt !== null;
    }

    public function getDeletedAt(): ?\DateTimeImmutable {
        return $this->deletedAt;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Two completely unrelated class hierarchies — both use the same traits
// ─────────────────────────────────────────────────────────────────────────────

// Hierarchy 1: Content management
abstract class ContentBase {
    public function __construct(
        public readonly string $title,
        public readonly string $author
    ) {}
}

class BlogPost extends ContentBase {
    use HasSlug, SoftDeletable; // Two traits at once

    public function __construct(
        string $title,
        string $author,
        public readonly string $body
    ) {
        parent::__construct($title, $author);
    }
}

class VideoPost extends ContentBase {
    use HasSlug, SoftDeletable; // Same two traits, different class

    public function __construct(
        string $title,
        string $author,
        public readonly string $videoUrl
    ) {
        parent::__construct($title, $author);
    }
}


// Hierarchy 2: E-commerce — completely unrelated to ContentBase
abstract class ProductBase {
    public function __construct(
        public readonly string $name,
        public readonly float  $price
    ) {}
}

class PhysicalProduct extends ProductBase {
    use HasSlug, SoftDeletable; // Same traits again — no shared parent with ContentBase

    public function __construct(
        string $name,
        float  $price,
        public readonly float $weightKg
    ) {
        parent::__construct($name, $price);
    }
}

class DigitalProduct extends ProductBase {
    use SoftDeletable; // Only needs one of the traits

    public function __construct(
        string $name,
        float  $price,
        public readonly string $downloadUrl
    ) {
        parent::__construct($name, $price);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the classes
// ─────────────────────────────────────────────────────────────────────────────

echo "── Content hierarchy ────────────────────────────────\n\n";

$post = new BlogPost('Hello World! This is My First Post', 'Alice', 'Body content...');
echo "Title:  {$post->title}\n";
echo "Slug:   {$post->getSlug()}\n";
echo "Deleted? " . ($post->isDeleted() ? 'YES' : 'NO') . "\n";
$post->delete();
echo "Deleted? " . ($post->isDeleted() ? 'YES' : 'NO') . "\n";
$post->restore();
echo "Deleted after restore? " . ($post->isDeleted() ? 'YES' : 'NO') . "\n";

echo "\n";

$video = new VideoPost('PHP 8.4 — New Features & Improvements', 'Bob', 'https://vimeo.com/abc');
echo "Title: {$video->title}\n";
echo "Slug:  {$video->getSlug()}\n";

echo "\n── Product hierarchy (unrelated) ───────────────────\n\n";

$widget = new PhysicalProduct('Premium Widget Pro (2024 Edition)', 299.00, 0.45);
echo "Name:  {$widget->name}\n";
echo "Slug:  {$widget->getSlug()}\n";
$widget->delete();

$ebook = new DigitalProduct('PHP OOP Mastery eBook', 49.00, 'https://dl.example.com/ebook');
echo "\nDigital: {$ebook->name} | R{$ebook->price}\n";
$ebook->delete();
$ebook->restore();


// ─────────────────────────────────────────────────────────────────────────────
// Key point: traits are NOT types — you cannot type-hint them
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Traits are not types ─────────────────────────────\n\n";

// This function works ONLY by luck of duck-typing — it is not type-safe.
// We will fix this in Example 04 using the interface + trait pattern.
function deleteIfExists(object $entity): void {
    if (method_exists($entity, 'delete')) {
        $entity->delete();
    }
}

deleteIfExists($widget);
deleteIfExists(new \stdClass()); // stdClass has no delete() — silently does nothing

echo "\n--- Recap ---\n";
echo "trait keyword:  defines a reusable block of methods.\n";
echo "use TraitName:  injects trait methods into a class as if written there.\n";
echo "Multiple traits: use A, B, C; — all injected at once.\n";
echo "Traits are NOT types — cannot be used in type-hints or instanceof.\n";
echo "Key use case:   behaviour shared across UNRELATED class hierarchies.\n";