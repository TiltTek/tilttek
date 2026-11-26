<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Eligibility\AnyNominee;
use Tilttek\FanAwards\Eligibility\AnyVoter;
use Tilttek\FanAwards\Exception\AwardRefused;
use Tilttek\FanAwards\Exception\InvalidAward;
use Tilttek\FanAwards\Exception\UnknownAward;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Phase;
use Tilttek\FanAwards\PhaseCount;
use Tilttek\FanAwards\Refusal;
use Tilttek\FanAwards\Results;

#[CoversClass(Nominee::class)]
#[CoversClass(Category::class)]
#[CoversClass(Edition::class)]
#[CoversClass(Phase::class)]
#[CoversClass(Results::class)]
#[CoversClass(PhaseCount::class)]
#[CoversClass(AwardRefused::class)]
#[CoversClass(InvalidAward::class)]
#[CoversClass(UnknownAward::class)]
#[CoversClass(AnyNominee::class)]
#[CoversClass(AnyVoter::class)]
final class ValuesTest extends TestCase
{
    public function testANomineeKeepsItsValues(): void
    {
        $nominee = new Nominee(str_repeat('a', 63).'Z', str_repeat('é', 200), ResourceReference::of('book', '12'));
        $plain = new Nominee('a', 'A');

        self::assertSame([str_repeat('a', 63).'Z', str_repeat('é', 200), 'book:12'], [$nominee->key, $nominee->title, $nominee->reference?->toString()]);
        self::assertNull($plain->reference);
        self::assertSame('no-award', Nominee::NO_AWARD);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidNominees(): iterable
    {
        $key = 'A nominee key is 1 to 64 ASCII letters, digits, "_" or "-", and not "no-award"; ';
        $title = 'A nominee title is 1 to 200 characters of UTF-8 without control characters nor surrounding spaces.';

        yield 'an empty key' => ['', 'Title', $key.'"" is not.'];
        yield 'a key too long' => [str_repeat('a', 65), 'Title', $key];
        yield 'a key with a dot' => ['a.b', 'Title', $key.'"a.b" is not.'];
        yield 'the key of no award' => ['no-award', 'Title', $key.'"no-award" is not.'];
        yield 'the key of no award in capitals' => ['No-Award', 'Title', $key.'"No-Award" is not.'];
        yield 'an empty title' => ['a', '', $title];
        yield 'a title too long' => ['a', str_repeat('a', 201), $title];
        yield 'a title with a space around' => ['a', ' Title', $title];
        yield 'a title with a trailing space' => ['a', 'Title ', $title];
        yield 'a title with a control character' => ['a', "Ti\ttle", $title];
        yield 'a key ending with a line break' => ["a\n", 'Title', $key."\"a\n\" is not."];
    }

    #[DataProvider('invalidNominees')]
    public function testANomineeOutOfBoundsIsRefused(string $key, string $title, string $error): void
    {
        $this->expectException(InvalidAward::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Nominee($key, $title);
    }

    public function testACategoryHasDefaultsAndBounds(): void
    {
        $defaults = new Category('novel', 'Best novel');
        $lowest = new Category('a', 'A', 1, 2, 2, 'borda', false);
        $highest = new Category(str_repeat('a', 64), str_repeat('é', 100), 10, 20, 20, 'schulze');

        self::assertSame([5, 10, 5, 'instant_runoff', true], [$defaults->nominations, $defaults->semifinalists, $defaults->finalists, $defaults->method, $defaults->noAward]);
        self::assertSame([1, 2, 2, 'borda', false], [$lowest->nominations, $lowest->semifinalists, $lowest->finalists, $lowest->method, $lowest->noAward]);
        self::assertSame([10, 20, 20, 'schulze'], [$highest->nominations, $highest->semifinalists, $highest->finalists, $highest->method]);
    }

    /**
     * @return iterable<string, array{string, string, int, int, int, string}>
     */
    public static function invalidCategories(): iterable
    {
        $name = 'A category name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.';

        yield 'an empty key' => ['', 'Name', 5, 10, 5, 'A category key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "" is not.'];
        yield 'a key with a capital' => ['Novel', 'Name', 5, 10, 5, '"Novel" is not.'];
        yield 'a key starting with a digit' => ['1novel', 'Name', 5, 10, 5, '"1novel" is not.'];
        yield 'a key ending with a line break' => ["novel\n", 'Name', 5, 10, 5, 'is not.'];
        yield 'a key too long' => [str_repeat('a', 65), 'Name', 5, 10, 5, 'A category key is'];
        yield 'an empty name' => ['novel', '', 5, 10, 5, $name];
        yield 'a name too long' => ['novel', str_repeat('a', 101), 5, 10, 5, $name];
        yield 'a name with a space around' => ['novel', 'Name ', 5, 10, 5, $name];
        yield 'a name with a control character' => ['novel', "Na\nme", 5, 10, 5, $name];
        yield 'no nomination' => ['novel', 'Name', 0, 10, 5, 'A nomination ballot holds 1 to 10 nominees; not 0.'];
        yield 'too many nominations' => ['novel', 'Name', 11, 10, 5, 'A nomination ballot holds 1 to 10 nominees; not 11.'];
        yield 'a semifinal of one' => ['novel', 'Name', 5, 1, 1, 'A semifinal takes 2 to 20 nominees; not 1.'];
        yield 'a semifinal too large' => ['novel', 'Name', 5, 21, 5, 'A semifinal takes 2 to 20 nominees; not 21.'];
        yield 'a final of one' => ['novel', 'Name', 5, 10, 1, 'A final takes 2 nominees to the 10 of the semifinal; not 1.'];
        yield 'a final larger than the semifinal' => ['novel', 'Name', 5, 4, 5, 'A final takes 2 nominees to the 4 of the semifinal; not 5.'];
    }

    #[DataProvider('invalidCategories')]
    public function testACategoryOutOfBoundsIsRefused(string $key, string $name, int $nominations, int $semifinalists, int $finalists, string $error): void
    {
        $this->expectException(InvalidAward::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Category($key, $name, $nominations, $semifinalists, $finalists);
    }

    public function testAnEditionKeepsItsCategoriesByKey(): void
    {
        $since = new DateTimeImmutable('2027-01-10 10:00:00');
        $edition = new Edition('fan.awards:2027_A-'.str_repeat('k', 81), str_repeat('é', 100), [new Category('novel', 'Novel'), new Category('game', 'Game')], true, Phase::Draft, $since);

        self::assertSame(['novel', 'game'], array_keys($edition->categories));
        self::assertSame('Game', $edition->category('game')->name);

        $moved = $edition->in(Phase::Nomination, new DateTimeImmutable('2027-02-01 00:00:00'));

        self::assertSame([$edition->key, $edition->name, true, Phase::Nomination, '2027-02-01'], [$moved->key, $moved->name, $moved->semifinal, $moved->phase, $moved->since->format('Y-m-d')]);
        self::assertSame(['novel', 'game'], array_keys($moved->categories));
        self::assertSame($since, $edition->since);

        $this->expectException(UnknownAward::class);
        $this->expectExceptionMessageIsOrContains('The edition "'.$edition->key.'" holds no category "comic".');
        $edition->category('comic');
    }

    public function testFiftyCategoriesAreAccepted(): void
    {
        $categories = array_map(static fn (int $i): Category => new Category('c'.$i, 'Category '.$i), range(1, 50));

        self::assertCount(50, new Edition('e', 'E', $categories, false, Phase::Draft, new DateTimeImmutable())->categories);
    }

    /**
     * @return iterable<string, array{string, string, list<Category>, string}>
     */
    public static function invalidEditions(): iterable
    {
        $name = 'An edition name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.';
        $one = [new Category('novel', 'Novel')];

        yield 'an empty key' => ['', 'Name', $one, 'An edition key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; "" is not.'];
        yield 'a key too long' => [str_repeat('k', 101), 'Name', $one, 'An edition key is'];
        yield 'a key with a space' => ['a b', 'Name', $one, '"a b" is not.'];
        yield 'a key ending with a line break' => ["e\n", 'Name', $one, 'is not.'];
        yield 'an empty name' => ['e', '', $one, $name];
        yield 'a name too long' => ['e', str_repeat('a', 101), $one, $name];
        yield 'a name with a space around' => ['e', ' Name', $one, $name];
        yield 'a name with a control character' => ['e', "Na\0me", $one, $name];
        yield 'no category' => ['e', 'Name', [], 'An edition holds 1 to 50 categories, not 0.'];
        yield 'too many categories' => ['e', 'Name', array_map(static fn (int $i): Category => new Category('c'.$i, 'C'), range(1, 51)), 'An edition holds 1 to 50 categories, not 51.'];
        yield 'a category twice' => ['e', 'Name', [new Category('novel', 'Novel'), new Category('novel', 'Other')], 'The category "novel" is declared twice.'];
    }

    /**
     * @param list<Category> $categories
     */
    #[DataProvider('invalidEditions')]
    public function testAnEditionOutOfBoundsIsRefused(string $key, string $name, array $categories, string $error): void
    {
        $this->expectException(InvalidAward::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Edition($key, $name, $categories, true, Phase::Draft, new DateTimeImmutable());
    }

    public function testThePhasesFollowEachOther(): void
    {
        $with = [];
        $without = [];

        foreach (Phase::cases() as $phase) {
            $with[$phase->value] = $phase->next(true)?->value;
            $without[$phase->value] = $phase->next(false)?->value;
        }

        self::assertSame(['draft' => 'nomination', 'nomination' => 'semifinal', 'semifinal' => 'final', 'final' => 'counted', 'counted' => 'announced', 'announced' => null], $with);
        self::assertSame('final', $without['nomination']);
        self::assertSame(['nomination', 'semifinal', 'final'], array_values(array_map(static fn (Phase $phase): string => $phase->value, array_filter(Phase::cases(), static fn (Phase $phase): bool => $phase->isVoting()))));
        self::assertSame(['nomination', 'semifinal'], array_values(array_map(static fn (Phase $phase): string => $phase->value, array_filter(Phase::cases(), static fn (Phase $phase): bool => $phase->takesWriteIns()))));
    }

    public function testResultsFindTheCountOfAPhase(): void
    {
        $nomination = new PhaseCount(Phase::Nomination, [], null, []);
        $final = new PhaseCount(Phase::Final, [], null, []);
        $results = new Results(new Category('novel', 'Novel'), [], false, [$nomination, $final]);

        self::assertSame($nomination, $results->count(Phase::Nomination));
        self::assertSame($final, $results->count(Phase::Final));
        self::assertNull($results->count(Phase::Semifinal));
    }

    public function testEachRefusalSaysWhy(): void
    {
        $messages = [];

        foreach (Refusal::cases() as $refusal) {
            $refused = AwardRefused::because($refusal);
            self::assertSame($refusal, $refused->refusal);
            self::assertNull($refused->reason);
            $messages[$refusal->value] = $refused->getMessage();
        }

        self::assertSame([
            'edition_exists' => 'An edition already holds this key.',
            'wrong_phase' => 'The edition is not in a phase that allows this act.',
            'voter_not_eligible' => 'This member may not vote in this edition.',
            'ineligible' => 'This nominee is not eligible in this category.',
            'too_many_candidates' => 'The category holds as many nominees as a ballot may offer.',
            'not_counted' => 'The edition is not counted yet.',
            'not_announced' => 'The results are published once the winners are announced.',
        ], $messages);

        $reasoned = AwardRefused::because(Refusal::Ineligible, 'Too old.');
        self::assertSame(['This nominee is not eligible in this category. Too old.', 'Too old.'], [$reasoned->getMessage(), $reasoned->reason]);
        self::assertSame('No edition is known as "2027".', UnknownAward::edition('2027')->getMessage());
    }

    public function testTheDefaultsLetEveryoneIn(): void
    {
        $edition = new Edition('e', 'E', [new Category('novel', 'Novel')], true, Phase::Draft, new DateTimeImmutable());

        self::assertNull(new AnyNominee()->refusal($edition, $edition->category('novel'), new Nominee('a', 'A')));
        self::assertTrue(new AnyVoter()->mayVote(ResourceReference::of('member', '1'), $edition));
    }
}
