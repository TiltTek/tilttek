<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\TableRules\Acceptance;
use Tilttek\TableRules\AcceptanceState;
use Tilttek\TableRules\Decision;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Internal\Identifier;
use Tilttek\TableRules\Internal\Text;
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\Rulebook;
use Tilttek\TableRules\RulebookVersion;

#[CoversClass(Rule::class)]
#[CoversClass(RulebookVersion::class)]
#[CoversClass(Acceptance::class)]
#[CoversClass(Rulebook::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidRules::class)]
final class ValuesTest extends TestCase
{
    public function testARuleKeepsItsValuesNormalised(): void
    {
        $rule = new Rule('phones', "  No phones at the table \t", "\n\nPhones stay in the bag.  \r\nUnless on call.\rThat is all.\t\n\n", 'table-talk');

        self::assertSame('phones', $rule->id);
        self::assertSame('No phones at the table', $rule->title);
        self::assertSame("Phones stay in the bag.\nUnless on call.\nThat is all.", $rule->text);
        self::assertSame('table-talk', $rule->category);
        self::assertSame(['Phones stay in the bag.', 'Unless on call.', 'That is all.'], $rule->lines());
        self::assertSame(['id' => 'phones', 'title' => 'No phones at the table', 'text' => "Phones stay in the bag.\nUnless on call.\nThat is all.", 'category' => 'table-talk'], $rule->toArray());
        self::assertNull(new Rule('a', 'A', 'B')->category);
    }

    public function testARuleKeepsTheIndentOfItsFirstLine(): void
    {
        self::assertSame("  - one\n  - two", new Rule('a', 'A', "  - one\n  - two")->text);
    }

    public function testRulesAreEqualOnlyWithEveryValueTheSame(): void
    {
        $rule = new Rule('a', 'Title', 'Text', 'c');

        self::assertTrue($rule->equals(new Rule('a', 'Title', 'Text', 'c')));
        self::assertFalse($rule->equals(new Rule('b', 'Title', 'Text', 'c')));
        self::assertFalse($rule->equals(new Rule('a', 'Other', 'Text', 'c')));
        self::assertFalse($rule->equals(new Rule('a', 'Title', 'Other', 'c')));
        self::assertFalse($rule->equals(new Rule('a', 'Title', 'Text', 'd')));
        self::assertFalse($rule->equals(new Rule('a', 'Title', 'Text')));
    }

    public function testARuleTakesTheLongestTitleTextAndLines(): void
    {
        self::assertSame(200, Text::length(new Rule('a', str_repeat('é', 200), 'x')->title));
        self::assertSame(10_000, Text::length(new Rule('a', 'A', str_repeat('é', 10_000))->text));
        self::assertCount(100, new Rule('a', 'A', implode("\n", array_fill(0, 100, 'x')))->lines());
        self::assertSame(128, \strlen(new Rule('a'.str_repeat('b', 127), 'A', 'x')->id));
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'empty identifier' => ['', 'A', 'x', null, 'The identifier of a rule is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'identifier with a digit first' => ['1a', 'A', 'x', null, 'The identifier of a rule'];
        yield 'identifier with an address' => ['a@b', 'A', 'x', null, 'The identifier of a rule'];
        yield 'identifier with a line feed after' => ["a\n", 'A', 'x', null, 'The identifier of a rule'];
        yield 'identifier too long' => ['a'.str_repeat('b', 128), 'A', 'x', null, 'The identifier of a rule'];
        yield 'category' => ['a', 'A', 'x', 'a b', 'The identifier of a category'];
        yield 'empty title' => ['a', " \t", 'x', null, 'The title of a rule is empty.'];
        yield 'title on two lines' => ['a', "A\nB", 'x', null, 'The title of a rule is one line.'];
        yield 'title too long' => ['a', str_repeat('é', 201), 'x', null, 'The title of a rule is 200 characters at most.'];
        yield 'title with a control character' => ['a', "A\x07", 'x', null, 'The title of a rule holds a control character.'];
        yield 'title with a bidirectional override' => ['a', "A\u{202E}B", 'x', null, 'The title of a rule holds a control character.'];
        yield 'title with an isolate' => ['a', "A\u{2066}B", 'x', null, 'The title of a rule holds a control character.'];
        yield 'title with a delete' => ['a', "A\x7F", 'x', null, 'The title of a rule holds a control character.'];
        yield 'title not UTF-8' => ['a', "A\xC3", 'x', null, 'The title of a rule is not valid UTF-8.'];
        yield 'empty text' => ['a', 'A', "\n \n\t", null, 'The text of a rule is empty.'];
        yield 'text too long' => ['a', 'A', str_repeat('é', 10_001), null, 'The text of a rule is 10000 characters at most.'];
        yield 'text of too many lines' => ['a', 'A', implode("\n", array_fill(0, 101, 'x')), null, 'The text of a rule is 100 lines at most.'];
        yield 'text with a null' => ['a', 'A', "x\x00", null, 'The text of a rule holds a control character.'];
        yield 'text with a vertical tab' => ['a', 'A', "x\x0By", null, 'The text of a rule holds a control character.'];
        yield 'text with an escape' => ['a', 'A', "x\x1By", null, 'The text of a rule holds a control character.'];
    }

    #[DataProvider('invalidRules')]
    public function testARuleRefusesAValueOutOfItsShape(string $id, string $title, string $text, ?string $category, string $message): void
    {
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Rule($id, $title, $text, $category);
    }

    public function testATextKeepsTabs(): void
    {
        self::assertSame("a\tb", new Rule('a', "A\tB", "a\tb")->text);
        self::assertSame("A\tB", new Rule('a', "A\tB", 'x')->title);
    }

    public function testTheLengthCountsCodePoints(): void
    {
        self::assertSame(0, Text::length(''));
        self::assertSame(3, Text::length("é\nè"));
    }

    public function testAVersionKeepsItsValues(): void
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00+00:00');
        $version = new RulebookVersion(2, [$this->rule('b'), $this->rule('a')], $at, 'gm-1', ['flanking', 'crits'], 'srd-5', "  Flanking added.  \n", false);

        self::assertSame(2, $version->number);
        self::assertSame(['b', 'a'], array_map(static fn (Rule $rule): string => $rule->id, $version->rules));
        self::assertSame(['crits', 'flanking'], $version->variants);
        self::assertSame('srd-5', $version->system);
        self::assertSame('  Flanking added.', $version->note);
        self::assertFalse($version->requiresAcceptance);
        self::assertSame('gm-1', $version->publishedBy);
        self::assertSame($at, $version->publishedAt);
        self::assertSame('b', $version->rule('b')?->id);
        self::assertNull($version->rule('c'));
        self::assertSame([
            'number' => 2,
            'rules' => [$this->rule('b')->toArray(), $this->rule('a')->toArray()],
            'variants' => ['crits', 'flanking'],
            'system' => 'srd-5',
            'note' => '  Flanking added.',
            'publishedAt' => '2027-01-01T12:00:00+00:00',
            'publishedBy' => 'gm-1',
            'requiresAcceptance' => false,
        ], $version->toArray());
    }

    public function testAVersionRequiresAcceptanceByDefaultAndReadsAnEmptyNoteAsNone(): void
    {
        $version = new RulebookVersion(1, [$this->rule('a')], new DateTimeImmutable(), 'gm-1', note: " \n ");

        self::assertTrue($version->requiresAcceptance);
        self::assertNull($version->note);
        self::assertNull($version->system);
        self::assertSame([], $version->variants);
        self::assertNull(new RulebookVersion(1, [$this->rule('a')], new DateTimeImmutable(), 'gm-1')->note);
    }

    public function testAVersionMayHoldOnlyVariants(): void
    {
        self::assertSame([], new RulebookVersion(1, [], new DateTimeImmutable(), 'gm-1', ['crits'])->rules);
    }

    public function testAVersionTakesItsBounds(): void
    {
        $at = new DateTimeImmutable();
        $rules = array_map(fn (int $i): Rule => $this->rule('r'.$i), range(1, 200));
        $variants = array_map(static fn (int $i): string => 'v'.$i, range(1, 100));
        $long = array_map(static fn (int $i): Rule => new Rule('r'.$i, 'A', str_repeat('é', 10_000)), range(1, 10));

        self::assertCount(200, new RulebookVersion(10_000, $rules, $at, 'gm-1', $variants)->rules);
        self::assertCount(100, new RulebookVersion(1, $rules, $at, 'gm-1', $variants)->variants);
        self::assertCount(10, new RulebookVersion(1, $long, $at, 'gm-1')->rules);
        self::assertSame(2_000, Text::length((string) new RulebookVersion(1, [$this->rule('a')], $at, 'gm-1', note: str_repeat('é', 2_000))->note));
        self::assertSame(49, substr_count((string) new RulebookVersion(1, [$this->rule('a')], $at, 'gm-1', note: implode("\n", array_fill(0, 50, 'x')))->note, "\n"));
    }

    /**
     * @return iterable<string, array{Closure(): RulebookVersion, string}>
     */
    public static function invalidVersions(): iterable
    {
        $at = new DateTimeImmutable();
        $rule = new Rule('a', 'A', 'x');

        yield 'number 0' => [static fn (): RulebookVersion => new RulebookVersion(0, [$rule], $at, 'gm-1'), 'The number of a version is 1 to 10000.'];
        yield 'number too high' => [static fn (): RulebookVersion => new RulebookVersion(10_001, [$rule], $at, 'gm-1'), 'The number of a version is 1 to 10000.'];
        yield 'first without acceptance' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', requiresAcceptance: false), 'The first version of the rules requires acceptance.'];
        yield 'author' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm@site'), 'The identifier of who published the rules'];
        yield 'system' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', system: '5e'), 'The identifier of a game system'];
        yield 'nothing' => [static fn (): RulebookVersion => new RulebookVersion(1, [], $at, 'gm-1'), 'A version holds at least one rule or one variant.'];
        yield 'rule twice' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule, new Rule('a', 'B', 'y')], $at, 'gm-1'), 'The rule a is given twice.'];
        yield 'too many rules' => [static fn (): RulebookVersion => new RulebookVersion(1, array_map(static fn (int $i): Rule => new Rule('r'.$i, 'A', 'x'), range(1, 201)), $at, 'gm-1'), 'A version holds 200 rules at most.'];
        yield 'too many variants' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', array_map(static fn (int $i): string => 'v'.$i, range(1, 101))), 'A version holds 100 variants at most.'];
        yield 'variant' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', ['a b']), 'The identifier of a variant'];
        yield 'variant twice' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', ['v', 'w', 'v']), 'A variant is given twice.'];
        yield 'texts too long' => [static fn (): RulebookVersion => new RulebookVersion(1, [...array_map(static fn (int $i): Rule => new Rule('r'.$i, 'A', str_repeat('é', 10_000)), range(1, 10)), $rule], $at, 'gm-1'), 'The texts of the rules of a version are 100000 characters at most together.'];
        yield 'note too long' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', note: str_repeat('é', 2_001)), 'The note of a version is 2000 characters at most.'];
        yield 'note of too many lines' => [static fn (): RulebookVersion => new RulebookVersion(1, [$rule], $at, 'gm-1', note: implode("\n", array_fill(0, 51, 'x'))), 'The note of a version is 50 lines at most.'];
    }

    /**
     * @param Closure(): RulebookVersion $build
     */
    #[DataProvider('invalidVersions')]
    public function testAVersionRefusesAValueOutOfItsShape(Closure $build, string $message): void
    {
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testVersionsHoldTheSameRulesWhenTheirRulesVariantsAndSystemAreTheSame(): void
    {
        $at = new DateTimeImmutable();
        $version = new RulebookVersion(1, [$this->rule('a'), $this->rule('b')], $at, 'gm-1', ['x', 'y'], 'sys', 'One');

        self::assertTrue($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), $this->rule('b')], $at->modify('+1 day'), 'gm-2', ['y', 'x'], 'sys', 'Two', false)));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('b'), $this->rule('a')], $at, 'gm-1', ['x', 'y'], 'sys')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a')], $at, 'gm-1', ['x', 'y'], 'sys')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), $this->rule('b'), $this->rule('c')], $at, 'gm-1', ['x', 'y'], 'sys')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), new Rule('b', 'B', 'changed')], $at, 'gm-1', ['x', 'y'], 'sys')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), $this->rule('b')], $at, 'gm-1', ['x'], 'sys')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), $this->rule('b')], $at, 'gm-1', ['x', 'y'], 'other')));
        self::assertFalse($version->sameRulesAs(new RulebookVersion(2, [$this->rule('a'), $this->rule('b')], $at, 'gm-1', ['x', 'y'])));
    }

    public function testAnAcceptanceKeepsItsValues(): void
    {
        $at = new DateTimeImmutable();
        $acceptance = new Acceptance('p-1', 10_000, Decision::Declined, $at);

        self::assertSame('p-1', $acceptance->player);
        self::assertSame(10_000, $acceptance->version);
        self::assertSame(Decision::Declined, $acceptance->decision);
        self::assertSame($at, $acceptance->decidedAt);
        self::assertSame(1, new Acceptance('p-1', 1, Decision::Accepted, $at)->version);
    }

    public function testAnAcceptanceRefusesAPlayerOrAVersionOutOfItsShape(): void
    {
        $at = new DateTimeImmutable();
        foreach ([['p@x', 1, 'The identifier of a player'], ['p', 0, 'The number of a version is 1 to 10000.'], ['p', 10_001, 'The number of a version is 1 to 10000.']] as [$player, $version, $message]) {
            try {
                new Acceptance($player, $version, Decision::Accepted, $at);
                self::fail('The acceptance was built.');
            } catch (InvalidRules $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }

    public function testARulebookSaysWhereEachPlayerStands(): void
    {
        $at = new DateTimeImmutable();
        $current = new RulebookVersion(4, [$this->rule('a')], $at, 'gm-1', requiresAcceptance: false);
        $rulebook = new Rulebook('t-1', $current, 3, [
            'p-old' => new Acceptance('p-old', 2, Decision::Accepted, $at),
            'p-base' => new Acceptance('p-base', 3, Decision::Accepted, $at),
            'p-now' => new Acceptance('p-now', 4, Decision::Accepted, $at),
            'p-no-old' => new Acceptance('p-no-old', 2, Decision::Declined, $at),
            'p-no' => new Acceptance('p-no', 3, Decision::Declined, $at),
        ], 7);

        self::assertSame('t-1', $rulebook->table);
        self::assertSame(7, $rulebook->revision);
        self::assertSame(3, $rulebook->baseline);
        self::assertSame($current, $rulebook->current);
        self::assertSame(AcceptanceState::Outdated, $rulebook->state('p-old'));
        self::assertSame(AcceptanceState::Accepted, $rulebook->state('p-base'));
        self::assertSame(AcceptanceState::Accepted, $rulebook->state('p-now'));
        self::assertSame(AcceptanceState::Pending, $rulebook->state('p-no-old'));
        self::assertSame(AcceptanceState::Declined, $rulebook->state('p-no'));
        self::assertSame(AcceptanceState::Pending, $rulebook->state('p-new'));
        self::assertSame(2, $rulebook->acceptance('p-old')?->version);
        self::assertNull($rulebook->acceptance('p-new'));
        self::assertSame(8, $rulebook->withRevision(8)->revision);
        self::assertSame(3, $rulebook->withRevision(8)->baseline);
        self::assertSame($rulebook->acceptances, $rulebook->withRevision(8)->acceptances);
        self::assertSame($current, $rulebook->withRevision(8)->current);
        $changed = $rulebook->with(null, 0, []);
        self::assertSame(7, $changed->revision);
        self::assertNull($changed->current);
        self::assertSame([], $changed->acceptances);
        self::assertSame('t-1', $changed->table);
    }

    public function testARulebookStartsEmpty(): void
    {
        $rulebook = new Rulebook('t-1');

        self::assertNull($rulebook->current);
        self::assertSame(0, $rulebook->baseline);
        self::assertSame(0, $rulebook->revision);
        self::assertSame([], $rulebook->acceptances);
        self::assertSame(AcceptanceState::Pending, $rulebook->state('p-1'));
    }

    public function testARulebookKeepsTheMostPlayers(): void
    {
        $at = new DateTimeImmutable();
        $acceptances = [];
        foreach (range(1, 500) as $i) {
            $acceptances['p'.$i] = new Acceptance('p'.$i, 1, Decision::Accepted, $at);
        }

        self::assertCount(500, new Rulebook('t', new RulebookVersion(1, [$this->rule('a')], $at, 'gm'), 1, $acceptances)->acceptances);

        $acceptances['p501'] = new Acceptance('p501', 1, Decision::Accepted, $at);
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('A rulebook keeps the decisions of 500 players at most.');

        new Rulebook('t', new RulebookVersion(1, [$this->rule('a')], $at, 'gm'), 1, $acceptances);
    }

    /**
     * @return iterable<string, array{Closure(): Rulebook, string}>
     */
    public static function invalidRulebooks(): iterable
    {
        $at = new DateTimeImmutable();
        $version = new RulebookVersion(2, [new Rule('a', 'A', 'x')], $at, 'gm');

        yield 'table' => [static fn (): Rulebook => new Rulebook('t 1'), 'The identifier of a table'];
        yield 'baseline without version' => [static fn (): Rulebook => new Rulebook('t', null, 1), 'The baseline of a rulebook is a version up to the current one, and 0 before the first.'];
        yield 'version without baseline' => [static fn (): Rulebook => new Rulebook('t', $version, 0), 'The baseline of a rulebook'];
        yield 'baseline after the version' => [static fn (): Rulebook => new Rulebook('t', $version, 3), 'The baseline of a rulebook'];
        yield 'decision under another player' => [static fn (): Rulebook => new Rulebook('t', $version, 2, ['p-2' => new Acceptance('p-1', 1, Decision::Accepted, $at)]), 'The decision of p-1 is kept under its player and answers a published version.'];
        yield 'decision of a later version' => [static fn (): Rulebook => new Rulebook('t', $version, 2, ['p-1' => new Acceptance('p-1', 3, Decision::Accepted, $at)]), 'The decision of p-1'];
        yield 'decision without version' => [static fn (): Rulebook => new Rulebook('t', null, 0, ['p-1' => new Acceptance('p-1', 1, Decision::Accepted, $at)]), 'The decision of p-1'];
    }

    /**
     * @param Closure(): Rulebook $build
     */
    #[DataProvider('invalidRulebooks')]
    public function testARulebookRefusesAValueOutOfItsShape(Closure $build, string $message): void
    {
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testARulebookTakesABaselineUpToItsVersion(): void
    {
        $version = new RulebookVersion(2, [$this->rule('a')], new DateTimeImmutable(), 'gm');

        self::assertSame(2, new Rulebook('t', $version, 2)->baseline);
        self::assertSame(1, new Rulebook('t', $version, 1, ['p' => new Acceptance('p', 2, Decision::Accepted, new DateTimeImmutable())])->baseline);
    }

    private function rule(string $id): Rule
    {
        return new Rule($id, ucfirst($id), 'The rule '.$id.'.');
    }
}
