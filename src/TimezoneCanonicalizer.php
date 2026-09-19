<?php
/**
 * Resolve backward-compatible IANA timezone aliases to canonical timezone identifiers.
 * 
 * @author Ujah Chigozie Peter
 */
declare(strict_types=1);

namespace Peterujah;

use Exception;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Resolves backward-compatible IANA timezone aliases to canonical identifiers.
 *
 * The default alias map is loaded lazily from `src/data/aliases.php`. Aliases
 * can be added, overridden, or removed at runtime; changes are kept in memory
 * for the current PHP process only and can be discarded with {@see self::reset()}.
 */
final class TimezoneCanonicalizer
{
    /**
     * Timezone snapshot version.
     * 
     * @see timezone_version_get()
     * @see /data/aliases.php
     * 
     * @var string VERSION
     */
    public const VERSION = '2022.7';

    /**
     * Active alias registry: the default aliases plus any runtime additions
     * and overrides, minus any runtime removals.
     *
     * Lazily initialised from {@see self::$DEFAULTS}
     *
     * @var array<string,string>|null $ALIASES
     */
    private static ?array $ALIASES = null;

    /**
     * Default IANA alias mappings loaded from the bundled data file.
     *
     * @var array<string,string>|null $DEFAULTS
     */
    private static ?array $DEFAULTS = null;

    /**
     * Resolve a timezone alias to its canonical IANA identifier.
     *
     * @param DateTimeZone|string $timezone The timezone identifier or object.
     *
     * @return string The canonical timezone identifier, or the timezone is not an alias.
     *
     * @throws InvalidArgumentException If the timezone is invalid.
     */
    public static function resolve(DateTimeZone|string $timezone): string
    {
        $timezone = ($timezone instanceof DateTimeZone)
            ? $timezone->getName()
            : $timezone;

        self::assert($timezone);

        return self::aliases()[$timezone] ?? $timezone;
    }

    /**
     * Add a timezone alias and its canonical identifier.
     *
     * If the alias is already registered (including a default alias), its
     * canonical identifier is replaced.
     *
     * @param string $alias The backward-compatible IANA timezone alias.
     * @param string $timezone The canonical IANA timezone identifier.
     *
     * @return void
     * @throws InvalidArgumentException If the canonical timezone is invalid.
     */
    public static function add(string $alias, string $timezone): void
    {
        self::assert($timezone);

        $aliases = self::aliases();
        $aliases[$alias] = $timezone;

        self::$ALIASES = $aliases;
    }

    /**
     * Remove a registered timezone alias.
     *
     * Both runtime and default aliases can be removed. A removed default alias
     * stays removed until it is added again or {@see self::reset()} is called.
     *
     * @param string $alias The timezone alias to remove.
     *
     * @return bool Whether the alias was registered and removed.
     */
    public static function remove(string $alias): bool
    {
        $aliases = self::aliases();

        if (!isset($aliases[$alias])) {
            return false;
        }

        unset($aliases[$alias]);

        self::$ALIASES = $aliases;

        return true;
    }

    /**
     * Discard all runtime changes and restore the default alias map.
     *
     * Useful in long-running processes and in test suites that need a clean state.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$ALIASES = null;
    }

    /**
     * Get all registered timezone aliases.
     *
     * @return array<string, string> A map of aliases to canonical timezone identifiers.
     */
    public static function aliases(): array
    {
        if (self::$ALIASES === null) {
            self::$ALIASES = [];
        }

        if (self::$DEFAULTS === null) {
            self::$DEFAULTS = require __DIR__ . '/data/aliases.php';

            self::$ALIASES = array_merge(
                self::$DEFAULTS,
                self::$ALIASES
            );
        }

        return self::$ALIASES;
    }

    /**
     * Determine whether a timezone identifier is a registered alias.
     *
     * This is a plain registry lookup: the identifier is not validated, so
     * unknown identifiers simply return false.
     *
     * @param string $timezone The timezone identifier.
     *
     * @return bool Whether the timezone is registered as an alias.
     */
    public static function isAlias(string $timezone): bool
    {
        return isset(self::aliases()[$timezone]);
    }

    /**
     * Validate a timezone identifier.
     *
     * @param string $timezone The timezone identifier.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the timezone is invalid.
     */
    private static function assert(string $timezone): void
    {
        try {
            new DateTimeZone($timezone);
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                "Invalid timezone: {$timezone}",
                previous: $e
            );
        }
    }
}
