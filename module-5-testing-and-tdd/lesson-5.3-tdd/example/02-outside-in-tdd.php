<?php
declare(strict_types=1);

/**
 * Example 02 — Outside-In TDD
 * -----------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.3-tdd/examples/02-outside-in-tdd.php
 *
 * Outside-in TDD starts at the outermost behaviour the caller cares about
 * and works inward. You write the test for the high-level service first,
 * using anonymous class doubles for dependencies that do not exist yet.
 * The interfaces emerge from what the test needs.
 *
 * This example builds a SlugService — converts article titles to URL slugs
 * and stores them — using outside-in TDD.
 *
 * Journey:
 *   PART A — Start from the outside: what does the caller want?
 *   PART B — The test defines the interfaces (before any interface exists)
 *   PART C — Implement just enough to pass each test
 *   PART D — The interfaces, extracted from what the tests needed
 *   PART E — The full SlugService implementation, driven by tests
 *   PART F — The full test suite
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Interfaces that emerged from the tests
//
// These did not exist at the start of the TDD session.
// The tests defined what methods were needed; then the interfaces were extracted.
// ─────────────────────────────────────────────────────────────────────────────

interface SlugRepositoryInterface
{
    // Emerged from test: "storeSlug() persists via the repository"
    public function store(string $articleId, string $slug): void;

    // Emerged from test: "findSlug() returns null when not stored"
    public function findByArticleId(string $articleId): ?string;

    // Emerged from test: "slugExists() prevents duplicate slugs"
    public function slugExists(string $slug): bool;
}

interface SlugFormatterInterface
{
    // Emerged from test: "generate() formats title to kebab-case slug"
    public function format(string $title): string;
}


// ─────────────────────────────────────────────────────────────────────────────
// PART E — SlugService, implemented outside-in
// ─────────────────────────────────────────────────────────────────────────────

class SlugService
{
    public function __construct(
        private SlugRepositoryInterface $repository,
        private SlugFormatterInterface  $formatter
    ) {}

    /**
     * Generates a URL slug for an article title and persists it.
     * If the base slug already exists, appends -2, -3, etc.
     *
     * @throws \InvalidArgumentException if title is empty
     */
    public function createSlug(string $articleId, string $title): string
    {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Title cannot be empty');
        }

        $baseSlug = $this->formatter->format($title);
        $slug     = $baseSlug;
        $counter  = 2;

        while ($this->repository->slugExists($slug)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $this->repository->store($articleId, $slug);

        return $slug;
    }

    /**
     * Returns the stored slug for an article, or null if not found.
     */
    public function findSlug(string $articleId): ?string
    {
        return $this->repository->findByArticleId($articleId);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART F — Test suite
// ─────────────────────────────────────────────────────────────────────────────

class OutsideInTDDExampleTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared doubles
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fake repository — in-memory store, real behaviour.
     * Emerged from the tests needing both store() and findByArticleId().
     */
    private function makeFakeRepo(): SlugRepositoryInterface
    {
        return new class implements SlugRepositoryInterface {
            private array $slugs = [];  // articleId → slug

            public function store(string $articleId, string $slug): void {
                $this->slugs[$articleId] = $slug;
            }

            public function findByArticleId(string $articleId): ?string {
                return $this->slugs[$articleId] ?? null;
            }

            public function slugExists(string $slug): bool {
                return in_array($slug, $this->slugs, true);
            }
        };
    }

    /**
     * Stub formatter — returns a predictable slug from any title.
     * Emerged from needing to control the slug value in early tests.
     */
    private function makeStubFormatter(string $returns): SlugFormatterInterface
    {
        return new class($returns) implements SlugFormatterInterface {
            public function __construct(private string $value) {}
            public function format(string $title): string { return $this->value; }
        };
    }

    /**
     * Real-ish formatter — strips, lowercases, hyphenates.
     * Added once the formatting behaviour needed to be tested directly.
     */
    private function makeRealFormatter(): SlugFormatterInterface
    {
        return new class implements SlugFormatterInterface {
            public function format(string $title): string {
                $slug = strtolower(trim($title));
                $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
                $slug = preg_replace('/[\s-]+/', '-', $slug);
                return trim($slug, '-');
            }
        };
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 1 — Start from the outside
    //
    // "I want createSlug() to return a string."
    // That is all we know. Write the smallest test that forces the class to exist.
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 1: createSlug() must exist and return a string.
     *
     * Writing this test IMMEDIATELY reveals:
     *   - SlugService needs a constructor (what arguments?)
     *   - We need some kind of formatter (what interface?)
     *   - We need some kind of repository (what interface?)
     *
     * The test uses anonymous class stubs — interfaces are defined inline.
     * This is outside-in: the test drives the interface design.
     */
    public function testCreateSlugReturnsAString(): void
    {
        $service = new SlugService(
            $this->makeFakeRepo(),
            $this->makeStubFormatter('my-article-title')
        );

        $result = $service->createSlug('article-1', 'My Article Title');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 2 — Does it use the formatter's output?
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 2: the returned slug must come from the formatter.
     *
     * Stub formatter returns a known value.
     * Test verifies the service uses that value as the slug.
     */
    public function testCreateSlugUsesFormatterOutputAsBaseSlug(): void
    {
        $service = new SlugService(
            $this->makeFakeRepo(),
            $this->makeStubFormatter('formatted-slug')
        );

        $slug = $service->createSlug('article-1', 'Anything');

        $this->assertSame('formatted-slug', $slug);
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 3 — Does it persist via the repository?
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 3: the slug must be findable after createSlug().
     *
     * This test forces the service to call repository->store().
     * Without this test, a naive implementation could just return the slug
     * without persisting it — and the caller would never know.
     */
    public function testCreatedSlugCanBeFoundByArticleId(): void
    {
        $repo    = $this->makeFakeRepo();
        $service = new SlugService($repo, $this->makeStubFormatter('my-slug'));

        $service->createSlug('article-42', 'My Title');

        $found = $service->findSlug('article-42');
        $this->assertSame('my-slug', $found);
    }

    /**
     * TDD step 3b: findSlug() returns null for an unknown articleId.
     */
    public function testFindSlugReturnsNullForUnknownArticleId(): void
    {
        $service = new SlugService(
            $this->makeFakeRepo(),
            $this->makeStubFormatter('irrelevant')
        );

        $this->assertNull($service->findSlug('does-not-exist'));
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 4 — What about duplicate slugs?
    //
    // This behaviour was NOT in the original spec. The test asks:
    // "What happens if I create two articles with the same title?"
    // TDD forces you to answer the question explicitly.
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 4: if the base slug already exists, append -2.
     *
     * This test FORCES the while loop inside createSlug().
     * Without this test, the duplicate-slug logic would not exist.
     */
    public function testCreateSlugAppendsTwoWhenBaseSlugAlreadyExists(): void
    {
        $repo    = $this->makeFakeRepo();
        $service = new SlugService($repo, $this->makeStubFormatter('my-article'));

        $first  = $service->createSlug('article-1', 'My Article');
        $second = $service->createSlug('article-2', 'My Article'); // same title → same base slug

        $this->assertSame('my-article',   $first);
        $this->assertSame('my-article-2', $second);
    }

    /**
     * TDD step 4b: third article with the same title gets -3.
     */
    public function testCreateSlugIncrementsCounterForEachDuplicate(): void
    {
        $repo    = $this->makeFakeRepo();
        $service = new SlugService($repo, $this->makeStubFormatter('duplicate'));

        $service->createSlug('a1', 'Duplicate');
        $service->createSlug('a2', 'Duplicate');
        $third = $service->createSlug('a3', 'Duplicate');

        $this->assertSame('duplicate-3', $third);
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 5 — Guard conditions
    //
    // "What should happen for bad input?"
    // TDD forces you to decide and document that decision via tests.
    // ═══════════════════════════════════════════════════════════

    public function testCreateSlugThrowsForEmptyTitle(): void
    {
        $service = new SlugService(
            $this->makeFakeRepo(),
            $this->makeStubFormatter('')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title cannot be empty');

        $service->createSlug('article-1', '');
    }

    public function testCreateSlugThrowsForWhitespaceOnlyTitle(): void
    {
        $service = new SlugService(
            $this->makeFakeRepo(),
            $this->makeStubFormatter('')
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->createSlug('article-1', '   ');
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 6 — Test the formatter itself (now that we know what it does)
    //
    // Outside-in: we started with SlugService and used stub formatters.
    // Now we zoom in and test the real formatter directly.
    // This is "working inward" — the inner test suite.
    // ═══════════════════════════════════════════════════════════

    public function testRealFormatterConvertsSpacesToHyphens(): void
    {
        $formatter = $this->makeRealFormatter();

        $this->assertSame('my-article-title', $formatter->format('My Article Title'));
    }

    public function testRealFormatterLowercasesTitle(): void
    {
        $formatter = $this->makeRealFormatter();

        $this->assertSame('hello-world', $formatter->format('HELLO WORLD'));
    }

    public function testRealFormatterRemovesSpecialCharacters(): void
    {
        $formatter = $this->makeRealFormatter();

        $this->assertSame('hello-world', $formatter->format('Hello, World!'));
    }

    public function testRealFormatterCollapsesMultipleSpaces(): void
    {
        $formatter = $this->makeRealFormatter();

        $this->assertSame('hello-world', $formatter->format('Hello    World'));
    }

    public function testRealFormatterTrimsLeadingAndTrailingHyphens(): void
    {
        $formatter = $this->makeRealFormatter();

        $this->assertSame('hello', $formatter->format('  hello  '));
    }

    // ═══════════════════════════════════════════════════════════
    // STEP 7 — Integration: real formatter + fake repo + service
    //
    // The final step: wire everything together with the real formatter
    // to verify the full behaviour end-to-end (still no real DB).
    // ═══════════════════════════════════════════════════════════

    public function testFullFlowWithRealFormatterAndFakeRepository(): void
    {
        $service = new SlugService($this->makeFakeRepo(), $this->makeRealFormatter());

        $slug1 = $service->createSlug('a1', 'Hello World');
        $slug2 = $service->createSlug('a2', 'Hello World'); // duplicate
        $slug3 = $service->createSlug('a3', 'PHP Testing');

        $this->assertSame('hello-world',   $slug1);
        $this->assertSame('hello-world-2', $slug2);
        $this->assertSame('php-testing',   $slug3);

        // All slugs are findable
        $this->assertSame('hello-world',   $service->findSlug('a1'));
        $this->assertSame('hello-world-2', $service->findSlug('a2'));
        $this->assertSame('php-testing',   $service->findSlug('a3'));
    }

    /**
     * REFLECTION: What outside-in TDD did for this design
     *
     * Before writing a single line of SlugService, the tests defined:
     *   1. SlugService needs two constructor args (drove DI)
     *   2. A formatter interface with format(string): string
     *   3. A repository interface with store(), findByArticleId(), slugExists()
     *   4. The deduplication behaviour (slug-2, slug-3...)
     *   5. The guard for empty titles
     *
     * None of these were "designed upfront". They emerged from writing tests
     * and asking: "What does this test need to work?"
     */
    public function testOutsideInReflection_SpyConfirmsBothDependenciesAreCalled(): void
    {
        // Spy on both dependencies to confirm integration
        $spyRepo = new class implements SlugRepositoryInterface {
            public array $stored = [];
            public array $existsChecks = [];

            public function store(string $articleId, string $slug): void {
                $this->stored[] = compact('articleId', 'slug');
            }
            public function findByArticleId(string $articleId): ?string { return null; }
            public function slugExists(string $slug): bool {
                $this->existsChecks[] = $slug;
                return false;
            }
        };

        $spyFormatter = new class implements SlugFormatterInterface {
            public array $formatted = [];
            public function format(string $title): string {
                $this->formatted[] = $title;
                return strtolower(str_replace(' ', '-', $title));
            }
        };

        $service = new SlugService($spyRepo, $spyFormatter);

        $service->createSlug('a1', 'My Great Article');

        // Formatter was called with the original title
        $this->assertCount(1, $spyFormatter->formatted);
        $this->assertSame('My Great Article', $spyFormatter->formatted[0]);

        // Repository was asked to check existence, then to store
        $this->assertNotEmpty($spyRepo->existsChecks);
        $this->assertCount(1, $spyRepo->stored);
        $this->assertSame('a1', $spyRepo->stored[0]['articleId']);
    }
}