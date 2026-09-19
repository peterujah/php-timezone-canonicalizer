<?php
declare(strict_types=1);

namespace Luminova\Time\Tests;

use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Peterujah\TimezoneCanonicalizer;
use PHPUnit\Framework\TestCase;

final class TimezoneCanonicalizerTest extends TestCase
{
    /**
     * The alias registry is static, so every test starts and ends from the
     * default state to keep tests independent of each other.
     */
    protected function setUp(): void
    {
        parent::setUp();

        TimezoneCanonicalizer::reset();
    }

    protected function tearDown(): void
    {
        TimezoneCanonicalizer::reset();

        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function defaultsFromDataFile(): array
    {
        /** @var array<string, string> $defaults */
        $defaults = require __DIR__ . '/../src/data/aliases.php';

        return $defaults;
    }

    // ------------------------------------------------------------------
    // resolve(): canonical alias resolution
    // ------------------------------------------------------------------

    public function testResolvesAliasesToCanonicalIdentifiers(): void
    {
        $expected = [
            'Asia/Calcutta' => 'Asia/Kolkata',
            'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
            'Europe/Kiev' => 'Europe/Kyiv',
            'US/Eastern' => 'America/New_York',
            'US/Pacific' => 'America/Los_Angeles',
            'Japan' => 'Asia/Tokyo',
            'UCT' => 'UTC',
        ];

        foreach ($expected as $alias => $canonical) {
            $this->assertSame(
                $canonical,
                TimezoneCanonicalizer::resolve($alias),
                "Failed resolving alias {$alias}"
            );
        }
    }

    public function testResolvesDateTimeZoneInstances(): void
    {
        $this->assertSame(
            'America/Los_Angeles',
            TimezoneCanonicalizer::resolve(new DateTimeZone('US/Pacific'))
        );

        $this->assertSame(
            'Asia/Kolkata',
            TimezoneCanonicalizer::resolve(new DateTimeZone('Asia/Calcutta'))
        );
    }

    public function testDateTimeZoneInstanceOfCanonicalIdentifierReturnsNull(): void
    {
        $this->assertNull(TimezoneCanonicalizer::resolve(new DateTimeZone('Asia/Kolkata')));
        $this->assertNull(TimezoneCanonicalizer::resolve(new DateTimeZone('UTC')));
    }


    public function testResolveThrowsForInvalidTimezoneIdentifiers(): void
    {
        foreach (['Mars/Olympus_Mons', 'Invalid/Timezone', 'Europe/Nowhere'] as $invalid) {
            try {
                TimezoneCanonicalizer::resolve($invalid);

                $this->fail("Expected InvalidArgumentException for {$invalid}");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("Invalid timezone: {$invalid}", $e->getMessage());
                $this->assertInstanceOf(Exception::class, $e->getPrevious());
            }
        }
    }

    // ------------------------------------------------------------------
    // add()
    // ------------------------------------------------------------------

    public function testAddRegistersANewAlias(): void
    {
        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertNull(TimezoneCanonicalizer::resolve('Asia/Kuala_Lumpur'));

        TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertTrue(TimezoneCanonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertSame('Asia/Singapore', TimezoneCanonicalizer::resolve('Asia/Kuala_Lumpur'));
        $this->assertSame('Asia/Singapore', TimezoneCanonicalizer::aliases()['Asia/Kuala_Lumpur']);
    }

    public function testAddKeepsDefaultAliasesIntact(): void
    {
        $defaults = $this->defaultsFromDataFile();

        TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertSame(
            $defaults + ['Asia/Kuala_Lumpur' => 'Asia/Singapore'],
            TimezoneCanonicalizer::aliases()
        );
    }

    public function testAddThrowsForInvalidCanonicalTimezoneAndLeavesRegistryUntouched(): void
    {
        try {
            TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Mars/Olympus_Mons');

            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid timezone: Mars/Olympus_Mons', $e->getMessage());
        }

        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertSame($this->defaultsFromDataFile(), TimezoneCanonicalizer::aliases());
    }

    public function testCustomAliasOverridesDefaultAlias(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertSame('America/Los_Angeles', $defaults['US/Pacific']);
        $this->assertSame('America/Los_Angeles', TimezoneCanonicalizer::resolve('US/Pacific'));

        TimezoneCanonicalizer::add('US/Pacific', 'America/Vancouver');

        $this->assertSame('America/Vancouver', TimezoneCanonicalizer::resolve('US/Pacific'));
        $this->assertSame('America/Vancouver', TimezoneCanonicalizer::aliases()['US/Pacific']);
        $this->assertCount(count($defaults), TimezoneCanonicalizer::aliases());

        // Other defaults are not affected by the override.
        $this->assertSame('America/New_York', TimezoneCanonicalizer::resolve('US/Eastern'));
    }

    public function testRegisteredAliasStillRequiresAValidInputIdentifier(): void
    {
        // resolve() validates its input with DateTimeZone before the lookup, so an
        // alias PHP does not recognise is registered but cannot be resolved.
        TimezoneCanonicalizer::add('Custom/Legacy', 'Europe/Paris');

        $this->assertTrue(TimezoneCanonicalizer::isAlias('Custom/Legacy'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone: Custom/Legacy');

        TimezoneCanonicalizer::resolve('Custom/Legacy');
    }

    // ------------------------------------------------------------------
    // remove()
    // ------------------------------------------------------------------

    public function testRemoveDeletesACustomAlias(): void
    {
        TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertTrue(TimezoneCanonicalizer::remove('Asia/Kuala_Lumpur'));

        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertNull(TimezoneCanonicalizer::resolve('Asia/Kuala_Lumpur'));
        $this->assertArrayNotHasKey('Asia/Kuala_Lumpur', TimezoneCanonicalizer::aliases());
    }

    public function testRemoveDeletesADefaultAlias(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertTrue(TimezoneCanonicalizer::remove('Asia/Calcutta'));

        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Calcutta'));
        $this->assertNull(TimezoneCanonicalizer::resolve('Asia/Calcutta'));
        $this->assertCount(count($defaults) - 1, TimezoneCanonicalizer::aliases());

        // Repeated calls (which re-read the registry) must not bring it back.
        $this->assertArrayNotHasKey('Asia/Calcutta', TimezoneCanonicalizer::aliases());
        $this->assertFalse(TimezoneCanonicalizer::remove('Asia/Calcutta'));

        // Other defaults are untouched.
        $this->assertSame('Asia/Ho_Chi_Minh', TimezoneCanonicalizer::resolve('Asia/Saigon'));
    }

    public function testRemoveReturnsFalseForUnregisteredAlias(): void
    {
        $this->assertFalse(TimezoneCanonicalizer::remove('Does/Not_Exist'));
        $this->assertFalse(TimezoneCanonicalizer::remove('America/New_York'));

        $this->assertSame($this->defaultsFromDataFile(), TimezoneCanonicalizer::aliases());
    }

    public function testRemovedDefaultAliasCanBeAddedAgain(): void
    {
        TimezoneCanonicalizer::remove('Asia/Calcutta');
        TimezoneCanonicalizer::add('Asia/Calcutta', 'Asia/Kolkata');

        $this->assertTrue(TimezoneCanonicalizer::isAlias('Asia/Calcutta'));
        $this->assertSame('Asia/Kolkata', TimezoneCanonicalizer::resolve('Asia/Calcutta'));
    }

    // ------------------------------------------------------------------
    // isAlias()
    // ------------------------------------------------------------------

    public function testIsAlias(): void
    {
        $this->assertTrue(TimezoneCanonicalizer::isAlias('Asia/Calcutta'));
        $this->assertTrue(TimezoneCanonicalizer::isAlias('US/Eastern'));

        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Kolkata'));
        $this->assertFalse(TimezoneCanonicalizer::isAlias('America/New_York'));
    }

    public function testIsAliasDoesNotValidateItsInput(): void
    {
        $this->assertFalse(TimezoneCanonicalizer::isAlias('Mars/Olympus_Mons'));
        $this->assertFalse(TimezoneCanonicalizer::isAlias(''));
    }

    // ------------------------------------------------------------------
    // aliases() and default alias loading
    // ------------------------------------------------------------------

    public function testDefaultAliasesAreLoadedFromTheDataFile(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertNotEmpty($defaults);
        $this->assertSame($defaults, TimezoneCanonicalizer::aliases());
    }

    public function testDefaultAliasDataIsWellFormed(): void
    {
        foreach ($this->defaultsFromDataFile() as $alias => $canonical) {
            $this->assertIsString($alias);
            $this->assertNotSame('', $alias);
            $this->assertIsString($canonical);
            $this->assertNotSame('', $canonical);
        }
    }

    public function testDefaultAliasDataContainsNoAliasChains(): void
    {
        $defaults = $this->defaultsFromDataFile();

        foreach ($defaults as $alias => $canonical) {
            if (isset($defaults[$canonical])) {
                $this->assertSame(
                    $canonical,
                    $defaults[$canonical],
                    "Alias {$alias} points to {$canonical}, which is itself an alias for {$defaults[$canonical]}"
                );
            }
        }
    }

    public function testAliasesReturnsACopyOfTheRegistry(): void
    {
        $aliases = TimezoneCanonicalizer::aliases();
        $aliases['Asia/Calcutta'] = 'Somewhere/Else';
        unset($aliases['US/Eastern']);

        $this->assertSame('Asia/Kolkata', TimezoneCanonicalizer::resolve('Asia/Calcutta'));
        $this->assertTrue(TimezoneCanonicalizer::isAlias('US/Eastern'));
    }

    public function testAliasesReflectsRuntimeChanges(): void
    {
        TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');
        TimezoneCanonicalizer::add('US/Pacific', 'America/Vancouver');
        TimezoneCanonicalizer::remove('Asia/Calcutta');

        $aliases = TimezoneCanonicalizer::aliases();

        $this->assertSame('Asia/Singapore', $aliases['Asia/Kuala_Lumpur']);
        $this->assertSame('America/Vancouver', $aliases['US/Pacific']);
        $this->assertArrayNotHasKey('Asia/Calcutta', $aliases);
        $this->assertSame('America/New_York', $aliases['US/Eastern']);
    }

    // ------------------------------------------------------------------
    // reset()
    // ------------------------------------------------------------------

    public function testResetRestoresTheDefaultAliases(): void
    {
        TimezoneCanonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');
        TimezoneCanonicalizer::add('US/Pacific', 'America/Vancouver');
        TimezoneCanonicalizer::remove('Asia/Calcutta');

        TimezoneCanonicalizer::reset();

        $this->assertSame($this->defaultsFromDataFile(), TimezoneCanonicalizer::aliases());
        $this->assertSame('America/Los_Angeles', TimezoneCanonicalizer::resolve('US/Pacific'));
        $this->assertSame('Asia/Kolkata', TimezoneCanonicalizer::resolve('Asia/Calcutta'));
        $this->assertFalse(TimezoneCanonicalizer::isAlias('Asia/Kuala_Lumpur'));
    }
}