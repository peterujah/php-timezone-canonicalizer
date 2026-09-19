# PHP Timezone Canonicalizer

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Composer](https://img.shields.io/badge/Composer-Required-885630?logo=composer&logoColor=white)](https://getcomposer.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)


A small, dependency-free PHP library that resolves **backward-compatible IANA timezone aliases** to their canonical identifiers (e.g, `Asia/Calcutta`, `US/Eastern`, `Europe/Kiev`) to their **canonical identifiers** (`Asia/Kolkata`, `America/New_York`, `Europe/Kyiv`).


## Installation

```bash
composer require peterujah/php-timezone-canonicalizer
```

## Usage

```php
use Peterujah\TimezoneCanonicalizer;

$timezone = TimezoneCanonicalizer::resolve('Asia/Calcutta');

echo $timezone, PHP_EOL;
// Asia/Kolkata
```

`resolve()` accepts either a timezone identifier or a `DateTimeZone` instance.

```php
$timezone = new DateTimeZone('US/Pacific');

echo TimezoneCanonicalizer::resolve($timezone), PHP_EOL;
// America/Los_Angeles
```

If the identifier is valid but is not registered as an alias, `resolve()` returns the identifier unchanged.

```php
echo TimezoneCanonicalizer::resolve('Asia/Kolkata'), PHP_EOL;
// Asia/Kolkata
```

An invalid timezone identifier throws `InvalidArgumentException`.

## Aliases

The default aliases are loaded from the bundled `src/data/aliases.php` file.

### Add or override an alias

```php
TimezoneCanonicalizer::add(
    'Asia/Kuala_Lumpur',
    'Asia/Singapore'
);

echo TimezoneCanonicalizer::resolve('Asia/Kuala_Lumpur');
// Asia/Singapore
```

`add()` validates the canonical timezone with PHP's `DateTimeZone`. If it is invalid, `InvalidArgumentException` is thrown and the registry is not changed.

An existing alias can also be overridden:

```php
TimezoneCanonicalizer::add(
    'US/Pacific',
    'America/Vancouver'
);

echo TimezoneCanonicalizer::resolve('US/Pacific');
// America/Vancouver
```

The alias itself is not validated by `add()`. However, `resolve()` validates its input before checking the alias registry, so a custom alias must also be accepted by PHP's `DateTimeZone` to be resolved.

### Remove an alias

```php
TimezoneCanonicalizer::remove('Asia/Calcutta');
// true
```

`remove()` returns `true` when the alias was registered and removed, otherwise `false`.

Removing a default alias only affects the current PHP process.

### Check an alias

```php
TimezoneCanonicalizer::isAlias('Asia/Calcutta');
// true

TimezoneCanonicalizer::isAlias('Asia/Kolkata');
// false
```

`isAlias()` only checks the current registry. It does not validate the timezone identifier and does not throw for unknown identifiers.

### Get registered aliases

```php
$aliases = TimezoneCanonicalizer::aliases();

echo $aliases['Asia/Calcutta'];
// Asia/Kolkata
```

The returned array contains the current alias registry, including runtime changes.

## Reset

Runtime additions, overrides, and removals can be discarded with `reset()`:

```php
TimezoneCanonicalizer::remove('Asia/Calcutta');
TimezoneCanonicalizer::add('US/Pacific', 'America/Vancouver');

TimezoneCanonicalizer::reset();

echo TimezoneCanonicalizer::resolve('Asia/Calcutta');
// Asia/Kolkata

echo TimezoneCanonicalizer::resolve('US/Pacific');
// America/Los_Angeles
```

`reset()` restores the bundled default alias map.

## API

All methods are static.

| Method | Return | Description |
| --- | --- | --- |
| `resolve(DateTimeZone\|string $timezone)` | `string` | Resolve an alias or return the valid identifier unchanged. |
| `add(string $alias, string $timezone)` | `void` | Add or replace an alias. |
| `remove(string $alias)` | `bool` | Remove an alias. |
| `isAlias(string $timezone)` | `bool` | Check whether an identifier is registered as an alias. |
| `aliases()` | `array<string, string>` | Return the current alias registry. |
| `reset()` | `void` | Restore the bundled default aliases. |

## Data version

The bundled alias data is based on IANA Time Zone Database version `2022.7`.

The package validates timezone identifiers using the timezone database available to the PHP runtime, while the alias map is bundled with the package. These versions can differ.

The bundled data version is available through:

```php
TimezoneCanonicalizer::VERSION;
// 2022.7
```

The PHP timezone database version can be checked with:

```php
echo timezone_version_get();
```

The bundled alias data is a snapshot and may differ from newer IANA releases.

## Testing

Install the development dependencies:

## Testing

Install the development dependencies and run the PHPUnit suite:

```bash
composer test
```

Other useful commands:

```bash
composer analyse        # PHPStan static analysis (level 8)
composer check          # static analysis + tests
```

## License

MIT
