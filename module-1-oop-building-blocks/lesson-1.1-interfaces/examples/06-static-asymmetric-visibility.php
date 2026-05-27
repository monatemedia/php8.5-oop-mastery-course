<?php
declare(strict_types=1);

/**
 * Example 06 — Static Asymmetric Visibility (PHP 8.5)
 * ------------------------------------------------------
 * PHP 8.4 introduced asymmetric visibility for INSTANCE properties:
 *   public private(set) string $name
 *
 * PHP 8.5 extends this to STATIC properties:
 *   public static private(set) string $environment
 *
 * This allows a static property to be:
 *   - READ from anywhere (public read)
 *   - WRITTEN only from inside the class (private set)
 *
 * Before PHP 8.5, achieving this required a private static property
 * plus a public static getter method — boilerplate that PHP now eliminates.
 *
 * PHP 8.5+ required for this file.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Static Asymmetric Visibility (PHP 8.5)             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The problem before PHP 8.5
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Before PHP 8.5 (boilerplate required) ────\n\n";

class AppConfigOld {
    // Private static — cannot be read from outside without a getter
    private static string $environment = 'production';
    private static string $version     = '1.0.0';
    private static bool   $debug       = false;

    // Had to write this getter just to expose the read
    public static function getEnvironment(): string { return self::$environment; }
    public static function getVersion(): string     { return self::$version; }
    public static function isDebug(): bool          { return self::$debug; }

    // Controlled write via a static method
    public static function setEnvironment(string $env): void {
        $allowed = ['production', 'staging', 'development', 'testing'];
        if (!in_array($env, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid environment: {$env}");
        }
        self::$environment = $env;
    }

    public static function enableDebug(): void {
        self::$debug = true;
    }
}

echo "Pre-8.5 pattern:\n";
echo "  Read: AppConfigOld::getEnvironment() = " . AppConfigOld::getEnvironment() . "\n";
AppConfigOld::setEnvironment('staging');
echo "  After set: " . AppConfigOld::getEnvironment() . "\n";
echo "  Problem: getEnvironment(), getVersion(), isDebug() are pure boilerplate.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — PHP 8.5 static asymmetric visibility
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: PHP 8.5 static asymmetric visibility ─────\n\n";

class AppConfig {
    // public static private(set): readable from anywhere, writable only inside the class
    public static private(set) string $environment = 'production';
    public static private(set) string $version     = '1.0.0';
    public static private(set) bool   $debug       = false;

    // No getter methods needed — read directly via AppConfig::$environment

    // Controlled write — validation still inside the class
    public static function setEnvironment(string $env): void {
        $allowed = ['production', 'staging', 'development', 'testing'];
        if (!in_array($env, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid environment: {$env}");
        }
        self::$environment = $env; // ✅ write from inside — allowed
    }

    public static function enableDebug(): void {
        self::$debug = true;
    }

    public static function configure(string $env, string $version): void {
        self::setEnvironment($env);
        self::$version = $version;  // ✅ write from inside — allowed
    }
}

echo "PHP 8.5 pattern — read directly, no getter needed:\n";
echo "  AppConfig::\$environment = " . AppConfig::$environment . "\n";
echo "  AppConfig::\$version     = " . AppConfig::$version . "\n";
echo "  AppConfig::\$debug       = " . (AppConfig::$debug ? 'true' : 'false') . "\n\n";

// Controlled write via public static method (inside the class)
AppConfig::setEnvironment('staging');
AppConfig::configure('development', '2.1.0');
AppConfig::enableDebug();

echo "After configure():\n";
echo "  AppConfig::\$environment = " . AppConfig::$environment . "\n";
echo "  AppConfig::\$version     = " . AppConfig::$version . "\n";
echo "  AppConfig::\$debug       = " . (AppConfig::$debug ? 'true' : 'false') . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Write from outside is a fatal error
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: Writing from outside is a fatal error ────\n\n";

try {
    // This line would throw: Cannot modify private(set) static property
    // AppConfig::$environment = 'testing';
    echo "AppConfig::\$environment = 'testing';\n";
    echo "→ Fatal error: Cannot modify private(set) static property AppConfig::\$environment\n";
    echo "  (Commented out in this example to allow the script to continue)\n\n";
} catch (\Error $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — All asymmetric visibility combinations for static properties
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: All asymmetric visibility combinations ───\n\n";

class VisibilityDemo {
    // Most common: readable everywhere, settable only from inside the class
    public static private(set) string $publicReadPrivateSet = 'a';

    // Readable by class and subclasses, settable only from inside the class
    public static protected(set) string $publicReadProtectedSet = 'b';

    // Readable by class and subclasses, settable only from class and subclasses
    protected static private(set) string $protectedReadPrivateSet = 'c';

    public static function readAll(): void {
        echo "  publicReadPrivateSet:    " . self::$publicReadPrivateSet . "\n";
        echo "  publicReadProtectedSet:  " . self::$publicReadProtectedSet . "\n";
        echo "  protectedReadPrivateSet: " . self::$protectedReadPrivateSet . "\n";
    }

    public static function setAll(): void {
        self::$publicReadPrivateSet    = 'A'; // ✅ inside class
        self::$publicReadProtectedSet  = 'B'; // ✅ inside class
        self::$protectedReadPrivateSet = 'C'; // ✅ inside class
    }
}

class ChildDemo extends VisibilityDemo {
    public static function setFromChild(): void {
        // self::$publicReadPrivateSet = 'x';   // ❌ private(set) — child cannot write
        self::$publicReadProtectedSet = 'P';     // ✅ protected(set) — child can write
        // self::$protectedReadPrivateSet = 'y'; // ❌ private(set) — child cannot write
    }
}

VisibilityDemo::readAll();
VisibilityDemo::setAll();
echo "After setAll():\n";
VisibilityDemo::readAll();


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — Practical real-world use case: Registry pattern
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 5: Real-world use — Registry pattern ─────────\n\n";

class FeatureRegistry {
    // Read from anywhere, only modified via the register() method
    public static private(set) array $features = [];
    public static private(set) int   $count    = 0;

    public static function register(string $name, bool $enabled = true): void {
        self::$features[$name] = $enabled;
        self::$count           = count(self::$features);
        echo "  [REGISTRY] Registered feature: {$name} (" . ($enabled ? 'on' : 'off') . ")\n";
    }

    public static function isEnabled(string $name): bool {
        return self::$features[$name] ?? false;
    }
}

FeatureRegistry::register('dark_mode',       true);
FeatureRegistry::register('beta_checkout',   false);
FeatureRegistry::register('new_dashboard',   true);

echo "\nDirect read (no getter method needed):\n";
echo "  FeatureRegistry::\$count = " . FeatureRegistry::$count . "\n";
echo "  dark_mode enabled: " . (FeatureRegistry::isEnabled('dark_mode') ? 'YES' : 'NO') . "\n";
echo "  beta_checkout enabled: " . (FeatureRegistry::isEnabled('beta_checkout') ? 'YES' : 'NO') . "\n\n";

echo "The \$features and \$count arrays are readable from anywhere\n";
echo "but cannot be modified outside the class — no accidental overwrite.\n";

echo "\n── Comparison summary ───────────────────────────────\n\n";
echo "  Before PHP 8.5 (static props):\n";
echo "    private static \$env = 'prod';   // Must write getter\n";
echo "    public static function getEnv(): string { return self::\$env; }\n\n";
echo "  PHP 8.4 (instance props only):\n";
echo "    public private(set) string \$name;  // instance only\n\n";
echo "  PHP 8.5 (extends to static):\n";
echo "    public static private(set) string \$env = 'prod';  // read anywhere\n";
echo "    // No getter method needed\n";

echo "\n--- Recap ---\n";
echo "public static private(set): readable from anywhere, writable only inside class.\n";
echo "public static protected(set): readable from anywhere, writable inside class and subclasses.\n";
echo "PHP 8.5 eliminates the need for static getter methods on guarded static state.\n";
echo "Write protection still enforced — Fatal error if written from outside.\n";