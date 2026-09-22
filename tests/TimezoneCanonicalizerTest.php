<?php
declare(strict_types=1);

namespace Peterujah\Timezone\Tests;

use DateTimeZone;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Peterujah\Timezone\Canonicalizer;

final class CanonicalizerTest extends TestCase
{
    /**
     * The alias registry is static, so every test starts and ends from the
     * default state to keep tests independent of each other.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Canonicalizer::reset();
    }

    protected function tearDown(): void
    {
        Canonicalizer::reset();

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
                Canonicalizer::resolve($alias),
                "Failed resolving alias {$alias}"
            );
        }
    }

    public function testResolvesDateTimeZoneInstances(): void
    {
        $this->assertSame(
            'America/Los_Angeles',
            Canonicalizer::resolve(new DateTimeZone('US/Pacific'))
        );

        $this->assertSame(
            'Asia/Kolkata',
            Canonicalizer::resolve(new DateTimeZone('Asia/Calcutta'))
        );
    }

    public function testDateTimeZoneInstanceOfCanonicalIdentifierReturnsNull(): void
    {
        $this->assertNull(Canonicalizer::resolve(new DateTimeZone('Asia/Kolkata')));
        $this->assertNull(Canonicalizer::resolve(new DateTimeZone('UTC')));
    }


    public function testResolveThrowsForInvalidTimezoneIdentifiers(): void
    {
        foreach (['Mars/Olympus_Mons', 'Invalid/Timezone', 'Europe/Nowhere'] as $invalid) {
            try {
                Canonicalizer::resolve($invalid);

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
        $this->assertFalse(Canonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertNull(Canonicalizer::resolve('Asia/Kuala_Lumpur'));

        Canonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertTrue(Canonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertSame('Asia/Singapore', Canonicalizer::resolve('Asia/Kuala_Lumpur'));
        $this->assertSame('Asia/Singapore', Canonicalizer::aliases()['Asia/Kuala_Lumpur']);
    }

    public function testAddKeepsDefaultAliasesIntact(): void
    {
        $defaults = $this->defaultsFromDataFile();

        Canonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertSame(
            $defaults + ['Asia/Kuala_Lumpur' => 'Asia/Singapore'],
            Canonicalizer::aliases()
        );
    }

    public function testAddThrowsForInvalidCanonicalTimezoneAndLeavesRegistryUntouched(): void
    {
        try {
            Canonicalizer::add('Asia/Kuala_Lumpur', 'Mars/Olympus_Mons');

            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid timezone: Mars/Olympus_Mons', $e->getMessage());
        }

        $this->assertFalse(Canonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertSame($this->defaultsFromDataFile(), Canonicalizer::aliases());
    }

    public function testCustomAliasOverridesDefaultAlias(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertSame('America/Los_Angeles', $defaults['US/Pacific']);
        $this->assertSame('America/Los_Angeles', Canonicalizer::resolve('US/Pacific'));

        Canonicalizer::add('US/Pacific', 'America/Vancouver');

        $this->assertSame('America/Vancouver', Canonicalizer::resolve('US/Pacific'));
        $this->assertSame('America/Vancouver', Canonicalizer::aliases()['US/Pacific']);
        $this->assertCount(count($defaults), Canonicalizer::aliases());

        // Other defaults are not affected by the override.
        $this->assertSame('America/New_York', Canonicalizer::resolve('US/Eastern'));
    }

    public function testRegisteredAliasStillRequiresAValidInputIdentifier(): void
    {
        // resolve() validates its input with DateTimeZone before the lookup, so an
        // alias PHP does not recognise is registered but cannot be resolved.
        Canonicalizer::add('Custom/Legacy', 'Europe/Paris');

        $this->assertTrue(Canonicalizer::isAlias('Custom/Legacy'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone: Custom/Legacy');

        Canonicalizer::resolve('Custom/Legacy');
    }

    // ------------------------------------------------------------------
    // remove()
    // ------------------------------------------------------------------

    public function testRemoveDeletesACustomAlias(): void
    {
        Canonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');

        $this->assertTrue(Canonicalizer::remove('Asia/Kuala_Lumpur'));

        $this->assertFalse(Canonicalizer::isAlias('Asia/Kuala_Lumpur'));
        $this->assertNull(Canonicalizer::resolve('Asia/Kuala_Lumpur'));
        $this->assertArrayNotHasKey('Asia/Kuala_Lumpur', Canonicalizer::aliases());
    }

    public function testRemoveDeletesADefaultAlias(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertTrue(Canonicalizer::remove('Asia/Calcutta'));

        $this->assertFalse(Canonicalizer::isAlias('Asia/Calcutta'));
        $this->assertNull(Canonicalizer::resolve('Asia/Calcutta'));
        $this->assertCount(count($defaults) - 1, Canonicalizer::aliases());

        // Repeated calls (which re-read the registry) must not bring it back.
        $this->assertArrayNotHasKey('Asia/Calcutta', Canonicalizer::aliases());
        $this->assertFalse(Canonicalizer::remove('Asia/Calcutta'));

        // Other defaults are untouched.
        $this->assertSame('Asia/Ho_Chi_Minh', Canonicalizer::resolve('Asia/Saigon'));
    }

    public function testRemoveReturnsFalseForUnregisteredAlias(): void
    {
        $this->assertFalse(Canonicalizer::remove('Does/Not_Exist'));
        $this->assertFalse(Canonicalizer::remove('America/New_York'));

        $this->assertSame($this->defaultsFromDataFile(), Canonicalizer::aliases());
    }

    public function testRemovedDefaultAliasCanBeAddedAgain(): void
    {
        Canonicalizer::remove('Asia/Calcutta');
        Canonicalizer::add('Asia/Calcutta', 'Asia/Kolkata');

        $this->assertTrue(Canonicalizer::isAlias('Asia/Calcutta'));
        $this->assertSame('Asia/Kolkata', Canonicalizer::resolve('Asia/Calcutta'));
    }

    // ------------------------------------------------------------------
    // isAlias()
    // ------------------------------------------------------------------

    public function testIsAlias(): void
    {
        $this->assertTrue(Canonicalizer::isAlias('Asia/Calcutta'));
        $this->assertTrue(Canonicalizer::isAlias('US/Eastern'));

        $this->assertFalse(Canonicalizer::isAlias('Asia/Kolkata'));
        $this->assertFalse(Canonicalizer::isAlias('America/New_York'));
    }

    public function testIsAliasDoesNotValidateItsInput(): void
    {
        $this->assertFalse(Canonicalizer::isAlias('Mars/Olympus_Mons'));
        $this->assertFalse(Canonicalizer::isAlias(''));
    }

    // ------------------------------------------------------------------
    // aliases() and default alias loading
    // ------------------------------------------------------------------

    public function testDefaultAliasesAreLoadedFromTheDataFile(): void
    {
        $defaults = $this->defaultsFromDataFile();

        $this->assertNotEmpty($defaults);
        $this->assertSame($defaults, Canonicalizer::aliases());
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
        $aliases = Canonicalizer::aliases();
        $aliases['Asia/Calcutta'] = 'Somewhere/Else';
        unset($aliases['US/Eastern']);

        $this->assertSame('Asia/Kolkata', Canonicalizer::resolve('Asia/Calcutta'));
        $this->assertTrue(Canonicalizer::isAlias('US/Eastern'));
    }

    public function testAliasesReflectsRuntimeChanges(): void
    {
        Canonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');
        Canonicalizer::add('US/Pacific', 'America/Vancouver');
        Canonicalizer::remove('Asia/Calcutta');

        $aliases = Canonicalizer::aliases();

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
        Canonicalizer::add('Asia/Kuala_Lumpur', 'Asia/Singapore');
        Canonicalizer::add('US/Pacific', 'America/Vancouver');
        Canonicalizer::remove('Asia/Calcutta');

        Canonicalizer::reset();

        $this->assertSame($this->defaultsFromDataFile(), Canonicalizer::aliases());
        $this->assertSame('America/Los_Angeles', Canonicalizer::resolve('US/Pacific'));
        $this->assertSame('Asia/Kolkata', Canonicalizer::resolve('Asia/Calcutta'));
        $this->assertFalse(Canonicalizer::isAlias('Asia/Kuala_Lumpur'));
    }
}
