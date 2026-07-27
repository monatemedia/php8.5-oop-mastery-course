# Module Gates
> Spaced retrieval. Answer from memory first — that is the whole mechanism.

Each module ends with a gate. Half of it covers the module you have just finished; the other half
reaches back to modules you finished a while ago. That second half is the part that does the work.

## Why the gate reaches backwards

Re-reading a lesson feels like learning and mostly is not. What moves material into long-term memory
is **retrieving** it — being asked a question with the book shut and having to reconstruct the answer.
Retrieval is uncomfortable in a way that re-reading is not, and the discomfort is the signal that
something is happening.

The second mechanism is **spacing**. A concept recalled once, then again three modules later, is held
far longer than one recalled twice on the same afternoon. So each concept below reappears on a
schedule rather than at the moment it is freshest.

| First taught | Revisited at | And again at |
|---|---|---|
| Module 1 — SOLID, composition, value objects | Gate 2 | Gate 4, Gate 5 |
| Module 2 — LSP, hooks, enums, anonymous classes | Gate 3 | Gate 5 |
| Module 3 — coupling, injection, composition root | Gate 4 | Gate 6 |
| Module 4 — containers, reflection, auto-wiring | Gate 5 | Gate 6 |
| Module 5 — doubles, TDD, behaviour testing | Gate 6 | — |

## How to take a gate

1. Shut every other file. Do not open the lesson you are being asked about — that turns retrieval
   into recognition and the benefit disappears.
2. Answer out loud or in writing. Thinking "yes, I know that one" is not retrieval; it is the
   feeling of familiarity, which is exactly what this is designed to defeat.
3. Only then read the answer.
4. Where you were wrong or vague, note the lesson in the **Notes & Sticking Points** table in
   `PROGRESS.md`. A gate you sailed through taught you nothing. The misses are the yield.

**Nothing here is machine-checked, and it cannot be.** No script can tell whether you reconstructed
an answer or recognised one. That makes this the one part of the course running purely on your own
honesty — which is worth saying plainly, since the rest of it deliberately does not.

---
---

# Gate 1 — after Module 1

## Part 1 — This module

**G1.1** State all five SOLID principles, one sentence each, in order.

**G1.2** You are looking at a class that `extends` another. What single question tells you whether
inheritance was the right call, and what is the alternative if the answer is no?

**G1.3** When would you reach for an interface, when an abstract class, and when a trait? Give the
deciding factor for each rather than a description of what they are.

**G1.4** Write, from memory, a value object holding an amount and a currency that cannot be mutated
after construction but can produce a modified copy.

## Part 2 — Carried forward

**G1.5** *(Lesson 1.0)* Which SOLID principle is violated by an interface with eleven methods where
most implementations throw on eight of them? What is the fix?

**G1.6** *(Lesson 1.1)* Why can a class implement many interfaces but extend only one parent, and
what does that constraint imply about which of the two you should reach for by default?

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 1 — Answers

**G1.1** — *Single Responsibility:* a class should have one reason to change. *Open/Closed:* open for
extension, closed for modification — you should be able to add behaviour without editing existing
code. *Liskov Substitution:* any subtype must be usable anywhere its parent type is expected, without
the caller needing to know. *Interface Segregation:* many small interfaces beat one large one; no
client should depend on methods it does not use. *Dependency Inversion:* depend on abstractions, not
concretions — high-level policy should not import low-level detail.

**G1.2** — Ask: *can I replace this `extends` with a field?* If the subclass is using the parent for
its behaviour rather than genuinely being a kind of it, hold an instance of it as a property and
delegate. Inheritance is for "is a"; composition is for "has a" and "uses a", and the second covers
far more real cases than most codebases admit.

**G1.3** — *Interface* when you need a contract and nothing else: multiple unrelated classes must be
usable in the same position. *Abstract class* when subclasses genuinely share state or a template
method — some real implementation exists to inherit. *Trait* when the same implementation must be
dropped into classes that have no useful common ancestor, and you accept that this is copy-paste
performed by the compiler rather than a type relationship. The deciding factor: interface gives you
a type, abstract class gives you a type plus shared code, trait gives you shared code and no type.

**G1.4** —

```php
final readonly class Money
{
    public function __construct(
        public int $amount,        // minor units — never a float
        public string $currency,
    ) {}

    public function withAmount(int $amount): static
    {
        return clone($this, ['amount' => $amount]);   // PHP 8.5
    }
}
```

Two details worth having recalled rather than looked up: the amount is an integer in minor units,
because binary floating point cannot represent `0.10` and money must not be approximate; and
`clone($obj, [...])` is a function call, not the `clone $obj with [...]` syntax that does not exist.
Before 8.5, write a constructor call by hand.

**G1.5** — Interface Segregation. The eight throwing methods are the tell: implementations are being
forced to depend on methods they do not use, and `throw new BadMethodCallException` is the sound a
type makes when it is lying about its own contract. Split it into several interfaces along the lines
where implementations actually diverge, and let a class implement two or three of them if it really
does offer all of that.

**G1.6** — Single inheritance is a language rule: a class has exactly one parent because the method
resolution order must be unambiguous. Interfaces carry no implementation, so there is nothing to
resolve and a class may implement as many as it likes. The implication is directional — extending
spends your one inheritance slot permanently, while implementing costs nothing and stays available.
Reach for the interface by default and spend the inheritance slot only when you are certain.

---
---

# Gate 2 — after Module 2

## Part 1 — This module

**G2.1** Explain covariance and contravariance using an example of your own — not the one in the
lesson. Which one does PHP support for parameters, and why is the other one dangerous?

**G2.2** What determines whether a property with hooks is *virtual* or *backed*? What can a backed
property do that a virtual one cannot, and vice versa?

**G2.3** An enum case has both a `name` and a `value`. Which one does `from()` take, and how do you
go the other way?

**G2.4** Give a case where an anonymous class is the right tool and a named class would be worse.

## Part 2 — Carried forward

**G2.5** *(Module 1 — Lesson 1.4)* Apply the composition test to this class. Is `extends` earning its
place?

```php
class CsvReportExporter extends FileWriter
{
    public function export(array $rows): void
    {
        $this->open('/tmp/report.csv');
        foreach ($rows as $row) {
            $this->writeLine(implode(',', $row));
        }
        $this->close();
    }
}
```

**G2.6** *(Module 1 — Lesson 1.2)* Why is `readonly` on every property not sufficient to make a class
immutable? What else has to be true?

**G2.7** *(Module 1 — Lesson 1.0)* Which SOLID principle does the Liskov Substitution Principle share
its initial with, and how would you explain LSP to someone who has just written a `Square extends
Rectangle`?

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 2 — Answers

**G2.1** — *Covariance* means an overriding method may return a **more specific** type than the one
it overrides: if `Repository::find(): ?Entity`, then `UserRepository::find(): ?User` is legal. Every
caller expecting an `Entity` is satisfied by a `User`, so nothing breaks. *Contravariance* means a
parameter may be widened to accept **more** than the parent did: if the parent takes a `User`, a
child taking `User|Admin` still honours every call the parent could handle.

PHP supports both — covariant returns and contravariant parameters — because both are safe. The
dangerous directions are the reverses: narrowing a parameter (the child rejects arguments the parent
accepted, so substituting it breaks callers) or widening a return type (the caller is handed
something less specific than the contract promised). PHP rejects both at compile time.

**G2.2** — A property is **backed** if any of its hooks reference the property itself — `set { $this->x
= ...; }` or a `get` that reads `$this->x`. Then real storage exists behind it. It is **virtual** if
no hook touches it, meaning the value is computed on every read from other state and nothing is
stored.

A backed property can hold a value between accesses and can have a default. A virtual property cannot
have a default value — there is nowhere to put it — but it can never fall out of sync with the data
it derives from, because there is no copy to go stale. Neither can be combined with `readonly`.

**G2.3** — `from()` and `tryFrom()` take the **value** — the thing after the `=` in the case
declaration. The `name` is the case identifier as written in source and has no lookup function.
To go from a name back to a case, either `constant(Status::class . '::' . $name)` or an explicit
`match` over the names. Confusing the two is the most common enum error and it is a `TypeError`
rather than a wrong result, which at least fails loudly.

**G2.4** — A test double. `new class implements MailerInterface { public array $sent = []; public
function send(...): bool { $this->sent[] = ...; return true; } }` is defined where it is used, needs
no file, no name, and no namespace, and cannot drift out of sync with the test that owns it. A named
`FakeMailer` class in a separate file invites reuse by unrelated tests, at which point changing it to
suit one test breaks another. Anonymous classes are also right for a one-off adapter at a composition
root — anywhere a type is needed exactly once, at the point of use.

**G2.5** — No, and the tell is that nothing about a CSV exporter *is* a file writer. It uses one.
`export()` calls three inherited methods and adds no meaning to `FileWriter` itself; meanwhile the
exporter has silently inherited the entire public surface of `FileWriter`, so callers can now call
`->open()` on an exporter and write anything they like to any path. Hold it as a field instead:

```php
final class CsvReportExporter
{
    public function __construct(private readonly FileWriter $writer) {}
    // ...
}
```

That also makes the writer injectable, which makes the exporter testable — the same move pays off
again in Module 3 and again in Module 5.

**G2.6** — `readonly` prevents *reassignment* of the property, not *mutation of what it points at*.
A `readonly array $items` genuinely cannot change, because PHP arrays are value types. A
`readonly Collection $items` holding an object can have `$this->items->add(...)` called on it all day:
the reference is fixed, the referent is not. For real immutability every property must be readonly
**and** every object it holds must itself be immutable, all the way down. Scalars and arrays are safe;
objects are only as immutable as they are themselves.

**G2.7** — The **L** in SOLID is Liskov; the initial it shares is with nothing else in the acronym,
but the principle it is most often confused with is the **I**, Interface Segregation — both are about
honouring a contract, but ISP is about the size of the contract and LSP is about whether you can be
swapped into it.

For `Square extends Rectangle`: the question is not whether a square is a rectangle in geometry — it
is — but whether a `Square` can be handed to every piece of code written against `Rectangle`. Code
that calls `setWidth(5)` then `setHeight(4)` and expects an area of 20 gets 16, because the square
had to break one setter to preserve its own invariant. The subtype is mathematically valid and
substitutably invalid, and it is substitutability that LSP is about. The usual resolution is that
neither should be mutable, or neither should inherit from the other.

---
---

# Gate 3 — after Module 3

## Part 1 — This module

**G3.1** Distinguish dependency injection, the dependency inversion principle, and inversion of
control. They are three different things and the words are used interchangeably in the wild.

**G3.2** Where does the composition root belong, and what is the smell that tells you something has
been wired somewhere it should not have been?

**G3.3** Name three coupling smells you could spot in an unfamiliar class inside a minute.

**G3.4** When is setter injection the right choice rather than constructor injection?

## Part 2 — Carried forward

**G3.5** *(Module 1 — Lesson 1.0)* Constructor injection is usually introduced as good practice.
Which SOLID principle is it the practical expression of, and which second principle does it tend to
improve as a side effect?

**G3.6** *(Module 2 — Lesson 2.0)* You inject an interface and pass a fake in a test. Which principle
guarantees the fake is safe to substitute, and what would a violation of it look like at runtime?

**G3.7** *(Module 2 — Lesson 2.3)* A service takes a `string $environment` that must be one of
`'dev'`, `'staging'` or `'prod'`. What is the better parameter type, and what exactly does it buy you?

**G3.8** *(Module 1 — Lesson 1.2)* Why is a value object a good thing to pass **into** a service and
a bad thing for a service to **hold and mutate**?

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 3 — Answers

**G3.1** — **Dependency injection** is a technique: an object receives its collaborators from outside
rather than constructing them. It is mechanical and says nothing about types. **The dependency
inversion principle** is a design rule: depend on abstractions rather than concretions, so that
high-level policy does not import low-level detail. You can inject a concrete class — that is DI
without DIP. **Inversion of control** is the broadest of the three: the general shift of deciding
*what happens next* from your code to a framework or container. DI is one instance of IoC; so are
callbacks, event hooks and template methods.

The precise relationship: DI is how you achieve DIP, and both are examples of IoC.

**G3.2** — At the entry point, and nowhere else — `public/index.php`, a console kernel, a test's
`setUp()`. That is Golden Rule 1. The smell is any class that is not the composition root calling
`new` on a collaborator, reading `getenv()`, or holding a reference to the container and calling
`->get()` on it. That last one is the service locator anti-pattern, and it is the subtlest, because
it looks like dependency injection while giving the class an invisible, untyped dependency on
everything in the application.

**G3.3** — `new` inside a constructor or a method body, for anything that is not a value object or an
exception. Static calls to concrete classes — `Mailer::send()`, `Logger::write()`, `DB::query()` —
which cannot be substituted at all. Reads of global state: `getenv()`, `$_SERVER`, `date()` and
`time()` where the result affects behaviour, and any singleton's `::getInstance()`. A fourth if you
have the minute: a constructor with no parameters on a class that clearly needs collaborators, which
means they are being manufactured somewhere inside.

**G3.4** — When the dependency is genuinely optional and there is a sane no-op default — a logger
defaulting to a null object is the standard case. Also when a circular reference is unavoidable and
one side must be wired after construction, though that is a signal the design is wrong rather than a
technique to reach for. The cost is real: with setter injection an object can exist in an
incompletely wired state, so the type system no longer guarantees it is usable. Constructor injection
makes it impossible to hold a half-built object, and that guarantee is worth more than the
flexibility in almost every case.

**G3.5** — It is the practical expression of the **Dependency Inversion Principle**: injecting an
interface through the constructor is precisely how a high-level class avoids importing a low-level
one. The side effect is **Single Responsibility** — once a class stops constructing its own
collaborators it stops knowing how to configure them, which removes an entire category of second
reason to change. A long constructor parameter list is also the most reliable SRP alarm in
existence: five injected dependencies is a class doing five things.

**G3.6** — The **Liskov Substitution Principle**. The fake is a subtype of the interface, and LSP is
the guarantee that any subtype can stand where the supertype is expected. A violation shows up as a
test that passes against the fake and fails against the real implementation, or the reverse — the
fake accepts input the real one rejects, returns `null` where the real one throws, or quietly ignores
a call the real one acts on. That is why a fake should honour the contract rather than merely satisfy
the compiler: a fake that lies about the interface makes the test worse than no test, because it
reports green while proving nothing.

**G3.7** — A backed enum. `Environment $environment` rather than `string $environment` moves the
validation from runtime to the type system: it becomes impossible to construct the service with
`'produciton'`, and impossible to forget a case in a `match` over it, because a non-exhaustive
`match` on an enum is a compile-visible error rather than a silent fall-through. You also get one
place to hang related behaviour — `$env->isProduction()`, `$env->logLevel()` — instead of scattering
string comparisons across the codebase. This is Golden Rule 3: the type system is a security layer,
not documentation.

**G3.8** — Passing one **in** is ideal: it is immutable, so the service cannot corrupt it and the
caller cannot be surprised by a change made downstream; and it carries its own validation, so the
service does not have to re-check what it was given. A service **holding and mutating** one is a
contradiction — you cannot mutate a value object, so what actually happens is the service replaces
its own field with a new instance, which means the service now has mutable state keyed to a
particular unit of work. That is exactly the failure Module 6 is about, and Golden Rule 5 names it:
objects either hold state or perform work, rarely both.

---
---

# Gate 4 — after Module 4

## Part 1 — This module

**G4.1** Describe what a container does in one sentence, then describe what auto-wiring adds to it.

**G4.2** Which Reflection calls does an auto-wiring container need, and at which point does
reflection become too slow to use naively?

**G4.3** Your container throws `Cannot auto-wire '$path' in 'SqliteDb'`. What are the three ways to
resolve that, and which do you prefer?

**G4.4** In the capstone, why is `public/index.php` the only file allowed to call `$container->get()`?

## Part 2 — Carried forward

**G4.5** *(Module 3 — Lesson 3.4)* A container resolves dependencies for you. Which of the three
terms in G3.1 does the container itself embody, and does using one automatically give you the other
two?

**G4.6** *(Module 2 — Lesson 2.4)* Give a container binding that is better written with an anonymous
class than with a named one.

**G4.7** *(Module 1 — Lesson 1.0)* Auto-wiring resolves constructor parameters by their type hints.
Which two SOLID principles does that quietly reward, and which one does it punish?

**G4.8** *(Module 3 — Lesson 3.1)* A colleague injects the container into a service so that the
service can resolve what it needs on demand. Name the anti-pattern and give the strongest single
argument against it.

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 4 — Answers

**G4.1** — A container is a registry that maps identifiers to instructions for building objects, and
returns the built object on request. Auto-wiring removes the instructions: instead of you writing a
factory for each class, the container reads the constructor's type hints and resolves them
recursively, so only the interface-to-implementation bindings need declaring by hand.

**G4.2** — `new ReflectionClass($id)`, then `->isInstantiable()` to reject interfaces and abstracts
with a useful message, `->getConstructor()`, and `->getParameters()`; for each parameter `->getType()`,
then `ReflectionNamedType::isBuiltin()` to separate classes from scalars, plus `->isOptional()` and
`->getDefaultValue()` for the fallback. Finally `->newInstanceArgs($deps)`.

It becomes too slow when you reflect the same class repeatedly — a container resolving a dependency
graph will meet the same class many times in one request. Cache the `ReflectionClass` and the
resolved parameter list by class name, which is what Lesson 4.2's `ReflectionCache` does. Production
containers go further and compile the whole graph to plain PHP so no reflection runs at request time
at all.

**G4.3** — Give the parameter a default value, so the container can use it. Bind the class explicitly
with a factory that supplies the argument. Or bind the parameter itself as a named container entry.
The factory binding is usually right, because it puts the value where every other piece of
configuration already lives — at the composition root — rather than hiding a production default
inside a class where it is invisible to anyone reading the wiring.

**G4.4** — Because every other call is a service locator. Once a controller can reach the container it
has an untyped dependency on everything registered in it, its constructor no longer tells the truth
about what it needs, and it can only be tested by building a container. Confining `get()` to the
entry point keeps every other class honest: what a class needs is exactly what its constructor
declares. The check is `grep -r "container->get" src/` returning nothing.

**G4.5** — The container embodies **inversion of control**: it, not your code, decides when and in
what order objects are constructed. It gives you **dependency injection** more or less for free,
since that is the mechanism it uses. It gives you **the dependency inversion principle** not at all —
you can auto-wire a graph of concrete classes that all depend on each other directly, and the
container will do it happily. DIP is a property of your type hints, and no tool can supply it for
you.

**G4.6** — A one-off adapter or a null object, where the implementation exists only to satisfy the
binding:

```php
LoggerInterface::class => fn() => new class implements LoggerInterface {
    public function log(string $level, string $message): void { /* discard */ }
},
```

A named `NullLogger` in its own file is not wrong, but the anonymous form puts the implementation
where the decision is made, and there is nothing to hunt for when reading the config. Reach for a
named class as soon as a second binding wants the same behaviour.

**G4.7** — It rewards **Dependency Inversion**, because a constructor that type-hints an interface is
exactly what auto-wiring resolves best, and **Interface Segregation**, because small focused
interfaces produce unambiguous bindings while a fat one tends to attract several plausible
implementations and force manual wiring.

It punishes **Single Responsibility** — or rather, it hides violations of it. Hand-wiring a class with
seven dependencies is painful enough that you notice; auto-wiring makes it effortless, so the class
grows unchecked and the pain that was your feedback signal disappears. Worth watching for
deliberately once wiring stops being manual.

**G4.8** — The **service locator** anti-pattern. The strongest argument: it converts explicit,
checkable dependencies into hidden ones. A constructor is a contract you can read, type-check and
satisfy in a test with three lines; a container reference is an unbounded claim on everything in the
application, invisible until the code path that calls `get()` happens to run. The failure moves from
construction time — where it is loud and immediate — to some branch in production at 3am. Everything
else wrong with it follows from that.

---
---

# Gate 5 — after Module 5

## Part 1 — This module

**G5.1** Name the four test-double types this course uses and say when each is appropriate.

**G5.2** What is the actual point of writing the failing test first? Give the reason that survives
scrutiny, not the slogan.

**G5.3** What distinguishes an integration test from a unit test here, and what should each one *not*
try to prove?

**G5.4** Golden Rule 2 says test behaviours, not layouts. Give a concrete pair — one assertion that
tests layout and the equivalent that tests behaviour.

## Part 2 — Carried forward

**G5.5** *(Module 3 — Lesson 3.0/3.2)* Why is a class that constructs its own collaborators
effectively untestable? Be specific about what you cannot do.

**G5.6** *(Module 2 — Lesson 2.4)* Why are anonymous classes the natural test double in PHP, and what
is the one situation where you should write a named double instead?

**G5.7** *(Module 1 — Lesson 1.0)* Which SOLID principle most directly determines how much work it is
to write a test double, and why?

**G5.8** *(Module 4 — Lesson 4.5)* Should a unit test build the container? What about an integration
test?

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 5 — Answers

**G5.1** — **Fake:** a working implementation with a shortcut — an in-memory repository backed by an
array. Use it when the collaborator must actually behave correctly for the test to mean anything.
**Stub:** returns canned answers, no logic. Use it when you need the collaborator to supply an input
and do not care how. **Spy:** records the calls made to it so the test can assert on them afterwards.
Use it when the behaviour under test *is* the outbound call — that an email was sent, that the event
was dispatched. **Null object:** does nothing and returns harmless values. Use it when the
collaborator is irrelevant to what you are testing and you want it out of the way.

The distinction between a spy and a mock is when the assertion happens: a spy records and you assert
afterwards, a mock is told its expectations in advance and fails at the point of the wrong call. Spies
read better and give clearer failure output; that is why this course prefers them.

**G5.2** — Because a test that has never failed has not been shown to be capable of failing. Write it
after the code and it passes on the first run, and you have no evidence whether it passes because the
code is correct or because the assertion is vacuous — asserting on the wrong object, swallowing an
exception, never reaching the assertion at all. Watching it fail for the expected reason, and then
pass for the expected reason, is the only cheap proof that the test is wired to the behaviour it
names.

The design benefit — that writing the test first forces you to use the API before you have committed
to it — is real, but secondary, and it is the half people abandon under deadline. The proof-of-failure
half is what actually cannot be obtained any other way.

**G5.3** — A unit test exercises one class with every collaborator doubled; it proves that class's
logic is right and nothing else. An integration test wires several real components together and
proves they agree about the contract between them — that the repository the service was written
against actually behaves the way the service assumes.

What a unit test should not try to prove: that the pieces fit. Every double is an assumption about a
collaborator, and a suite of green unit tests is fully compatible with an application that cannot
serve a request. What an integration test should not try to prove: every branch. It is slower and its
failures are harder to localise, so use it for the seams and leave exhaustive case coverage to unit
tests.

**G5.4** — Layout: `$this->assertSame(['id' => 1, 'name' => 'Alice'], $response['data']);` — this fails
when someone adds an `email` key, though nothing has broken. Behaviour:
`$this->assertSame(1, $response['data']['id']);` together with `assertTrue($response['success']);` —
this fails only when the thing you actually care about changes. The test to write is the one that
fails for exactly one reason and that reason is a real defect. A test asserting on the whole shape of
a structure will be edited every time the structure grows, and a test that gets routinely edited
stops being read.

**G5.5** — You cannot substitute anything. The collaborator is created inside the constructor, so
there is no seam: no way to pass a fake repository, so the test needs a real database; no way to pass
a spy mailer, so the test sends real email or you skip that assertion; no way to freeze the clock, so
any date-dependent branch is untestable or flaky. Your only remaining options are integration tests
against real infrastructure — slow, order-dependent, and unable to reach error paths, because you
cannot easily make a real database fail on demand. This is why Lesson 5.0 comes before any PHPUnit at
all: testability is not a property you add to a class later, it is a consequence of how the class gets
its dependencies.

**G5.6** — Because they need no file, no name and no namespace, and they are defined at the point of
use — so a reader sees the double and the assertion together, and changing the double cannot break a
test elsewhere. They can implement an interface, extend a class, capture surrounding variables through
the constructor, and hold public properties a spy can expose. Write a named double when the same
double is genuinely wanted by many tests **and** it has real behaviour worth maintaining — an
in-memory repository implementing a full interface is the usual case. The moment it is shared, treat
it as production code: it now needs its own tests, because a fake with a bug makes every test that
uses it lie.

**G5.7** — **Interface Segregation.** The work of writing a double is proportional to the size of the
interface, because you must implement every method whether the test uses it or not. A three-method
interface is a five-line anonymous class; a twenty-method one is a chore that pushes people towards
mocking frameworks, partial doubles, or not testing that path at all. Fat interfaces do not just make
production code worse — they tax every test that touches them, which is how they end up untested.

**G5.8** — A unit test should not build the container. It should `new` the class under test directly
and pass doubles: the container is exactly the wiring the unit test is trying to hold constant, and
resolving through it means a change to an unrelated binding can break a test that has nothing to do
with it.

An integration test should, because the wiring is part of what it exists to verify — a
misconfigured binding is a real defect and this is the layer that catches it. Build it fresh in
`setUp()` rather than sharing one across the class: a container reused between tests is a stateful
singleton, and by Module 6 you will recognise what that does to isolation.

---
---

# Gate 6 — after Module 6

## Part 1 — This module

**G6.1** Name the three object lifecycles, and the runtime in which each is the normal case.

**G6.2** Give the one-sentence rule that decides whether a class is safe as a singleton.

**G6.3** Name the five stateful-service anti-patterns from Lesson 6.3.

**G6.4** When is a factory definition the right answer rather than a scope change?

## Part 2 — Carried forward

**G6.5** *(Module 5 — Lesson 5.1)* Write the shape of a test that proves a service leaks state
between requests. What is the single detail that gives it its power?

**G6.6** *(Module 4 — Lesson 4.4)* What is PHP-DI's default scope, and what changes in the *class*
when you move it to transient?

**G6.7** *(Module 1 — Lesson 1.2)* Why is a value object automatically safe as a singleton?

**G6.8** *(Module 3 — Lesson 3.2)* A service needs the current user's ID during a request. Give the
lifecycle-safe way to supply it and say why the obvious alternative is the bug in Lesson 6.3.

**G6.9** *(Module 1 — Lesson 1.0)* Golden Rule 5 says objects either hold state or perform work,
rarely both. Which SOLID principle is that a restatement of, and what does the "rarely" leave room
for?

---

&nbsp;
&nbsp;
&nbsp;
&nbsp;

## ✅ Gate 6 — Answers

**G6.1** — **Request lifecycle:** the object is created when the request begins and destroyed when the
response is sent. Normal under PHP-FPM. **Worker lifecycle:** the object lives until the worker
process dies, across many requests or jobs. Normal under Swoole, FrankenPHP, RoadRunner and any queue
worker. **Script lifecycle:** the object lives until the CLI script exits. Normal for batch
importers and exporters, and the one people forget, because there is no request to anchor the mental
model.

**G6.2** — A class is safe as a singleton if no public method writes to a property that another public
method reads — except for properties set at construction and never changed afterwards. Equivalently:
once the constructor returns, the object's observable state must be fixed.

**G6.3** — The accumulating service; authentication state on a singleton; request-scoped data on a
singleton; counters and statistics on a singleton; and deferred initialisation that never resets.
Four of the five are the same defect wearing different clothes — a property written during one unit
of work and read during the next — which is the point: you are learning one shape, not five rules.

**G6.4** — When construction needs an argument the container cannot resolve, or a decision that
depends on runtime state. A scope change answers "how many of these should exist"; a factory answers
"how is this one built". Reaching for a factory to get freshness when transient scope would do is the
common mistake, and it works, but it buries a lifecycle decision in a closure where the next reader
will not look for it.

**G6.5** — One instance, constructed once, exercised twice:

```php
$service = new BasketService();     // created ONCE, as a worker singleton would be

$service->add('WIDGET');            // "request 1"
$this->assertCount(1, $service->items());

// "request 2" — the instance is NOT rebuilt, because a worker would not rebuild it
$this->assertCount(0, $service->items(), 'state leaked across the request boundary');
```

The detail that gives it power is the refusal to re-construct between the two units of work. A test
that builds the object fresh each time is testing the PHP-FPM case, and the PHP-FPM case is precisely
where the bug is invisible — which is why an entire suite of ordinary green unit tests is no evidence
at all of lifecycle safety.

**G6.6** — Singleton: one instance per container lifetime, for `autowire()`, `create()` and plain
auto-wiring alike. Moving to transient — `factory(fn() => new Thing())` — changes **nothing in the
class**. It is a change to the definitions file only. That is the whole lesson: scope is a wiring
decision, and a class that needs editing to survive a scope change is telling you it had mutable
state all along.

**G6.7** — Because it has no mutable state to leak. Every property is `readonly` and set at
construction, so no method can write anything another method reads; the "modified copy" methods return
new instances rather than mutating. It satisfies the singleton rule by construction rather than by
discipline, and the language enforces it. Immutability and lifecycle safety turn out to be the same
property viewed from two directions — which is why the value-object habit from Lesson 1.2 pays off
five modules later without any further work.

**G6.8** — Pass it as a parameter to the method that needs it, or inject a per-request object that
carries it and is registered as transient. The obvious alternative — a `UserSession` singleton with
`authenticate()` storing the ID and `currentUser()` reading it back — is anti-pattern 2 exactly: a
property written during one request and read during the next, so under a persistent worker request 2
sees request 1's user. Storing it as a `clear()`-me-first field is worse than either, because the
guarantee then rests on every future caller remembering, forever, and nothing in the type system will
remind them.

**G6.9** — It is **Single Responsibility**, stated in terms of state rather than reasons to change: an
object that both holds data and performs operations has at least two, and the two pull in opposite
directions under every runtime in Module 6.

The "rarely" leaves room for the cases where holding state *is* the work — an entity, a value object,
a collection, an explicitly request-scoped context object. What it excludes is the accidental
version: a service that was meant to perform work and acquired a property along the way because it
was convenient. The test is whether the state is the object's purpose or a side effect of its
implementation.

---
---

## After Gate 6

Every gate answered from memory means the course has moved from your notes into your head, which is
the only place it is any use at 3am.

Where a gate found a gap, the fix is not to re-read the lesson — that is the mechanism this file
exists to replace. Re-attempt the challenge with the solution closed. Retrieval is what failed, so
retrieval is what needs the practice.
