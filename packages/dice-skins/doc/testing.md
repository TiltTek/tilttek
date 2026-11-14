# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: colours read and their luminance and
contrast against known values, each side of the linear threshold of sRGB included; skins refused field by field and
read back as they were written; the base skins all readable; the order in which an appearance is found, a skin the
catalog no longer offers passed over, the high-contrast look at each of its three steps, and the owned dice read from a
real `tilttek/collections` shelf; the adapter of `tilttek/dice-bundle`, from the default skin to high contrast and
the numbers of sides it leaves plain. The adapter's tests are skipped, with a message that says so, when the installed
`tilttek/dice-bundle` is older than 0.2.0-alpha. In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/definitions.php` is the fuzz target of the skin definitions: any text gives readable skins that write back
as they were read, or is refused with `InvalidSkin`, never another exception. `composer fuzz` runs it.

`tests/Benchmark/DiceSkinsBench.php` measures the appearance of one die, in standard and in high contrast, and the
reading of a catalog of 256 skins.

## Testing an application

Give `DiceSkins` a `FixedSkinCatalog` and an `InMemorySkinChoiceStore`, and an `OwnedDice` of your own that returns
the dice the test needs:

```php
$skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore(), $ownedDice);
self::assertSame('#ffffff', $skins->appearance($member, 6, Accessibility::HighContrast)->look->face->hex);
```
