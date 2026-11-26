<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Rule\RuleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgCalendarEvents\Entry;
use Tilttek\RpgCalendarEvents\Exception\InvalidEntry;
use Tilttek\RpgCalendarEvents\Status;

#[CoversClass(Entry::class)]
#[CoversClass(InvalidEntry::class)]
#[CoversClass(Status::class)]
final class EntryTest extends TestCase
{
    public function testAnEntryKeepsWhatItIsGiven(): void
    {
        $verified = new DateTimeImmutable('2026-11-21', new DateTimeZone('UTC'));
        $entry = new Entry('towel-day', ['en' => 'Towel Day', 'fr' => 'Journée de la serviette'], RuleParser::parse('05-25'), 'https://example.org/towel', 'readers', $verified, ['books'], 2, Status::ToVerify);

        self::assertSame('towel-day', $entry->id);
        self::assertSame('05-25', (string) $entry->rule);
        self::assertSame('https://example.org/towel', $entry->source);
        self::assertSame('readers', $entry->community);
        self::assertSame($verified, $entry->verified);
        self::assertSame(['books'], $entry->tags);
        self::assertSame(2, $entry->days);
        self::assertSame(Status::ToVerify, $entry->status);
        self::assertSame('verified', Status::Verified->value);
        self::assertSame('to-verify', Status::ToVerify->value);
    }

    public function testItDefaultsToOneVerifiedDayWithoutTags(): void
    {
        $entry = new Entry('sample', ['en' => 'Sample day', 'fr' => 'Jour exemple'], RuleParser::parse('05-25'), 'https://example.org/sample', 'worldwide', new DateTimeImmutable('2026-11-21'));

        self::assertSame([], $entry->tags);
        self::assertSame(1, $entry->days);
        self::assertSame(Status::Verified, $entry->status);
        self::assertFalse($entry->prompts);
    }

    public function testAPeriodOfTwoDaysOrMoreHasAPromptADay(): void
    {
        $verified = new DateTimeImmutable('2026-11-21');

        self::assertTrue(new Entry('month', ['en' => 'Month', 'fr' => 'Mois'], RuleParser::parse('08-01'), 'https://example.org', 'rpg', $verified, days: 31, prompts: true)->prompts);
        self::assertTrue(new Entry('pair', ['en' => 'Pair', 'fr' => 'Paire'], RuleParser::parse('08-01'), 'https://example.org', 'rpg', $verified, days: 2, prompts: true)->prompts);

        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIsOrContains('The entry "day" has a prompt a day over 2 days or more: a single day is one prompt, not a themed period.');

        new Entry('day', ['en' => 'Day', 'fr' => 'Jour'], RuleParser::parse('08-01'), 'https://example.org', 'rpg', $verified, prompts: true);
    }

    public function testItsDefinitionIsWhatTheAlmanacComputes(): void
    {
        $entry = $this->entry(tags: ['books', 'humour'], days: 3);
        $definition = $entry->definition();

        self::assertSame('sample', $definition->key);
        self::assertSame('Sample day', $definition->title);
        self::assertSame($entry->rule, $definition->rule);
        self::assertSame(3, $definition->days);
        self::assertSame(['books', 'humour'], $definition->tags);
        self::assertTrue($definition->allDay);
        self::assertNull($definition->until);
    }

    public function testItIsNamedInTheClosestLocale(): void
    {
        $entry = $this->entry(names: ['en' => 'Day', 'fr' => 'Jour', 'fr_CA' => 'Journée', 'pt_BR' => 'Dia']);

        self::assertSame('Journée', $entry->name('fr_CA'));
        self::assertSame('Jour', $entry->name('fr_FR'));
        self::assertSame('Jour', $entry->name('fr-BE'));
        self::assertSame('Jour', $entry->name('FR_ch'));
        self::assertSame('Jour', $entry->name('fr'));
        self::assertSame('Day', $entry->name('de_DE'));
        self::assertSame('Day', $entry->name('pt'));
        self::assertSame('Day', $entry->name(''));
    }

    public function testItKnowsItsTags(): void
    {
        $entry = $this->entry(tags: ['books']);

        self::assertTrue($entry->hasTag('books'));
        self::assertFalse($entry->hasTag('book'));
    }

    public function testItsBoundsAreIncluded(): void
    {
        $tags = array_map(static fn (int $n): string => 'tag-'.$n, range(1, 16));
        $names = ['en' => str_repeat('é', 200), 'fr' => 'Jour'];

        foreach (\array_slice(self::locales(), 0, 30) as $locale) {
            $names[$locale] = 'x';
        }

        $entry = $this->entry(id: 'a'.str_repeat('b', 63), names: $names, source: 'https://a.example/'.str_repeat('b', 2030), tags: $tags, days: 366, community: '0'.str_repeat('-', 63));

        self::assertCount(32, $entry->names);
        self::assertCount(16, $entry->tags);
        self::assertSame(366, $entry->days);
        self::assertSame(2048, \strlen($entry->source));
        self::assertSame(64, \strlen($entry->id));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'a host' => ['https://example.org', 'https://example.org/'];
        yield 'a path' => ['https://example.org/wiki/Day', 'https://example.org/wiki/Day'];
        yield 'a query' => ['https://example.org?day=1', 'https://example.org/?day=1'];
        yield 'a fragment' => ['https://example.org#day', 'https://example.org/#day'];
        yield 'a host in capitals' => ['HTTPS://Example.ORG/Day', 'https://example.org/Day'];
        yield 'dot segments' => ['https://example.org/wiki/../Day', 'https://example.org/Day'];
        yield 'the default port' => ['https://example.org:443/Day', 'https://example.org/Day'];
    }

    #[DataProvider('sources')]
    public function testItKeepsAnHttpsSourceInItsCanonicalForm(string $source, string $kept): void
    {
        self::assertSame($kept, $this->entry(source: $source)->source);
    }

    /**
     * @return iterable<string, array{array{id?: string, names?: array<string, string>, source?: string, tags?: list<string>, days?: int, community?: string}, string}>
     */
    public static function refusals(): iterable
    {
        $identifier = 'has no identifier: a lowercase letter or digit, then up to 63 of them or "-".';
        $source = 'has no source: an https address on a host name, of at most 2048 bytes, without spaces.';
        $community = 'has no community: a lowercase letter or digit, then up to 63 of them or "-".';
        $names = 'is named in English and French, in at most 32 locales.';

        yield 'an identifier with a capital' => [['id' => 'Towel'], 'The entry "Towel" '.$identifier];
        yield 'an identifier starting with a dash' => [['id' => '-towel'], 'The entry "-towel" '.$identifier];
        yield 'an identifier with a dot' => [['id' => 'towel.day'], 'The entry "towel.day" '.$identifier];
        yield 'an identifier too long' => [['id' => 'a'.str_repeat('b', 64)], 'The entry "a'.str_repeat('b', 64).'" '.$identifier];
        yield 'an identifier ending with a line break' => [['id' => "towel\n"], "The entry \"towel\n\" ".$identifier];
        yield 'an identifier cut at 100 characters' => [['id' => 'X'.str_repeat('é', 119)], 'The entry "X'.str_repeat('é', 99).'" '.$identifier];
        yield 'no French name' => [['names' => ['en' => 'Day']], 'The entry "sample" '.$names];
        yield 'no English name' => [['names' => ['fr' => 'Jour']], 'The entry "sample" '.$names];
        yield 'too many locales' => [['names' => ['en' => 'Day', 'fr' => 'Jour'] + array_fill_keys(\array_slice(self::locales(), 0, 31), 'x')], 'The entry "sample" '.$names];
        yield 'a locale with a dash' => [['names' => ['en' => 'Day', 'fr' => 'Jour', 'fr-CA' => 'Jour']], 'The entry "sample" names itself in "fr-CA", which is not a locale such as "fr" or "fr_CA".'];
        yield 'a locale in capitals' => [['names' => ['en' => 'Day', 'fr' => 'Jour', 'FR' => 'Jour']], 'The entry "sample" names itself in "FR", which is not a locale such as "fr" or "fr_CA".'];
        yield 'a locale of four letters' => [['names' => ['en' => 'Day', 'fr' => 'Jour', 'fran' => 'Jour']], 'The entry "sample" names itself in "fran", which is not a locale such as "fr" or "fr_CA".'];
        yield 'a locale with a line break' => [['names' => ['en' => 'Day', 'fr' => 'Jour', "de\n" => 'Tag']], "The entry \"sample\" names itself in \"de\n\", which is not a locale such as \"fr\" or \"fr_CA\"."];
        yield 'a locale cut at 20 characters' => [['names' => ['en' => 'Day', 'fr' => 'Jour', 'y'.str_repeat('é', 29) => 'x']], 'The entry "sample" names itself in "y'.str_repeat('é', 19).'", which is not a locale such as "fr" or "fr_CA".'];
        yield 'a blank name' => [['names' => ['en' => 'Day', 'fr' => ' ']], 'The entry "sample" has a name in "fr" of 1 to 200 characters, not blank.'];
        yield 'a name too long' => [['names' => ['en' => str_repeat('é', 201), 'fr' => 'Jour']], 'The entry "sample" has a name in "en" of 1 to 200 characters, not blank.'];
        yield 'a source over http' => [['source' => 'http://example.org'], 'The entry "sample" '.$source];
        yield 'a source without host' => [['source' => 'https://'], 'The entry "sample" '.$source];
        yield 'a source starting with a path' => [['source' => 'https:///path'], 'The entry "sample" '.$source];
        yield 'a source with a space' => [['source' => 'https://example.org/a b'], 'The entry "sample" '.$source];
        yield 'a source ending with a line break' => [['source' => "https://example.org\n"], 'The entry "sample" '.$source];
        yield 'a source not at the start' => [['source' => ' https://example.org'], 'The entry "sample" '.$source];
        yield 'a source too long' => [['source' => 'https://a.example/'.str_repeat('b', 2031)], 'The entry "sample" '.$source];
        yield 'a source too long once escaped' => [['source' => 'https://a.example/'.str_repeat('b', 2028).'é'], 'The entry "sample" '.$source];
        yield 'a source on a single name' => [['source' => 'https://wiki/Day'], 'The entry "sample" '.$source];
        yield 'a source on an IP address' => [['source' => 'https://192.0.2.1/Day'], 'The entry "sample" '.$source];
        yield 'a source with a user name' => [['source' => 'https://user@example.org/Day'], 'The entry "sample" '.$source];
        yield 'a source that is not an address' => [['source' => 'towel day'], 'The entry "sample" '.$source];
        yield 'a community with a capital' => [['community' => 'Readers'], 'The entry "sample" '.$community];
        yield 'a community too long' => [['community' => 'a'.str_repeat('b', 64)], 'The entry "sample" '.$community];
        yield 'too many tags' => [['tags' => array_map(static fn (int $n): string => 'tag-'.$n, range(1, 17))], 'The entry "sample" carries at most 16 tags, each once.'];
        yield 'a tag twice' => [['tags' => ['books', 'books']], 'The entry "sample" carries at most 16 tags, each once.'];
        yield 'a tag with a capital' => [['tags' => ['Books']], 'The entry "sample" carries the tag "Books": a lowercase letter or digit, then up to 63 of them or "-".'];
        yield 'a tag cut at 64 characters' => [['tags' => ['Z'.str_repeat('é', 69)]], 'The entry "sample" carries the tag "Z'.str_repeat('é', 63).'": a lowercase letter or digit, then up to 63 of them or "-".'];
        yield 'no day' => [['days' => 0], 'The entry "sample" lasts 1 to 366 days.'];
        yield 'too many days' => [['days' => 367], 'The entry "sample" lasts 1 to 366 days.'];
    }

    /**
     * @param array{id?: string, names?: array<string, string>, source?: string, tags?: list<string>, days?: int, community?: string} $arguments
     */
    #[DataProvider('refusals')]
    public function testItRefusesWhatIsNotAnEntry(array $arguments, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->entry(...$arguments);
    }

    public function testTheMessageIsExact(): void
    {
        try {
            $this->entry(days: 0);
            self::fail('No exception.');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame('The entry "sample" lasts 1 to 366 days.', $invalidEntry->getMessage());
        }
    }

    /**
     * Three-letter codes that are locales by their form: `qaa`, `qab`….
     *
     * @return list<string>
     */
    private static function locales(): array
    {
        $locales = [];

        foreach (['a', 'b'] as $second) {
            foreach (range('a', 'z') as $third) {
                $locales[] = 'q'.$second.$third;
            }
        }

        return $locales;
    }

    /**
     * @param array<string, string> $names
     * @param list<string>          $tags
     */
    private function entry(string $id = 'sample', array $names = ['en' => 'Sample day', 'fr' => 'Jour exemple'], string $source = 'https://example.org/sample', array $tags = [], int $days = 1, string $community = 'worldwide'): Entry
    {
        return new Entry($id, $names, RuleParser::parse('05-25'), $source, $community, new DateTimeImmutable('2026-11-21'), $tags, $days);
    }
}
