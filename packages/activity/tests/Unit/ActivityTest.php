<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Appended;
use Tilttek\Activity\Context;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\Erasure;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Totals;
use Tilttek\Activity\Verb;
use Tilttek\Activity\VerbPattern;

#[CoversClass(Activity::class)]
#[CoversClass(IdempotencyKey::class)]
#[CoversClass(Context::class)]
#[CoversClass(Entry::class)]
#[CoversClass(EntryKind::class)]
#[CoversClass(Appended::class)]
#[CoversClass(Totals::class)]
#[CoversClass(Erasure::class)]
#[CoversClass(Filter::class)]
final class ActivityTest extends TestCase
{
    public function testAnIdempotencyKey(): void
    {
        self::assertSame('comment:4512', IdempotencyKey::fromString('comment:4512')->value);
        self::assertSame(str_repeat('k', 255), IdempotencyKey::fromString(str_repeat('k', 255))->value);
        self::assertSame('k', IdempotencyKey::fromString('k')->value);
        self::assertTrue(IdempotencyKey::fromString('a')->equals(IdempotencyKey::fromString('a')));
        self::assertFalse(IdempotencyKey::fromString('a')->equals(IdempotencyKey::fromString('b')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => ['', 'An idempotency key is 1 to 255 bytes; this one is 0.'];
        yield 'too long' => [str_repeat('k', 256), 'An idempotency key is 1 to 255 bytes; this one is 256.'];
        yield 'space' => ['order 12', 'An idempotency key cannot contain whitespace or control characters.'];
        yield 'newline' => ["order:12\n", 'An idempotency key cannot contain whitespace or control characters.'];
        yield 'delete' => ["order\x7F", 'An idempotency key cannot contain whitespace or control characters.'];
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidKeyIsRefused(string $key, string $message): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains($message);

        IdempotencyKey::fromString($key);
    }

    public function testAContextIsSortedAndBounded(): void
    {
        $context = new Context(['system' => 'dnd5e', 'first_time' => true, 'level' => 3]);

        self::assertSame(['first_time' => true, 'level' => 3, 'system' => 'dnd5e'], $context->values);
        self::assertSame('dnd5e', $context->get('system'));
        self::assertNull($context->get('missing'));
        self::assertTrue($context->equals(new Context(['level' => 3, 'system' => 'dnd5e', 'first_time' => true])));
        self::assertFalse($context->equals(new Context(['level' => '3', 'system' => 'dnd5e', 'first_time' => true])));
        self::assertSame([], new Context()->values);

        $sixteen = [];
        foreach (range(1, 16) as $i) {
            $sixteen['k'.$i] = $i;
        }

        self::assertCount(16, new Context($sixteen)->values);
        self::assertSame(str_repeat('x', 100), new Context(['a' => str_repeat('x', 100), str_repeat('b', 64) => 1])->get('a'));
    }

    /**
     * @return iterable<string, array{array<string, bool|int|string>, string}>
     */
    public static function invalidContexts(): iterable
    {
        $seventeen = [];
        foreach (range(1, 17) as $i) {
            $seventeen['k'.$i] = $i;
        }

        yield 'too many' => [$seventeen, 'A context has at most 16 entries; this one has 17.'];
        yield 'upper case name' => [['System' => 'x'], '"System" is not a context entry name: 1 to 64 lower-case letters, digits and "_", starting with a letter.'];
        yield 'dotted name' => [['a.b' => 'x'], '"a.b" is not a context entry name'];
        yield 'name too long' => [[str_repeat('a', 65) => 1], 'is not a context entry name'];
        yield 'name with a newline' => [["a\n" => 1], 'is not a context entry name'];
        yield 'string too long' => [['note' => str_repeat('x', 101)], 'The context entry "note" is at most 100 bytes; it is 101.'];
    }

    /**
     * @param array<string, bool|int|string> $values
     */
    #[DataProvider('invalidContexts')]
    public function testAnInvalidContextIsRefused(array $values, string $message): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Context($values);
    }

    public function testAnActivityFromPlainValues(): void
    {
        $at = new DateTimeImmutable('2026-11-07 10:00:00+00:00');
        $activity = Activity::of('game:812:player:44', 'member:44', 'game.played', $at, 'game:812', 2, ['system' => 'dnd5e']);

        self::assertSame([
            'key' => 'game:812:player:44',
            'member' => 'member:44',
            'verb' => 'game.played',
            'occurred_at' => '2026-11-07T10:00:00+00:00',
            'object' => 'game:812',
            'quantity' => 2,
            'context' => ['system' => 'dnd5e'],
        ], $activity->toArray());
        self::assertSame($at, $activity->occurredAt);

        $plain = Activity::of('k', 'member:1', 'news.read', $at);
        self::assertNull($plain->object);
        self::assertSame(1, $plain->quantity);
        self::assertSame([], $plain->context->values);
        self::assertNull($plain->toArray()['object']);
    }

    public function testAnActivityBuiltDirectly(): void
    {
        $activity = new Activity(IdempotencyKey::fromString('k'), ResourceReference::of('member', '1'), Verb::fromString('news.read'), new DateTimeImmutable('2026-11-07'));

        self::assertSame([null, 1, []], [$activity->object, $activity->quantity, $activity->context->values]);
    }

    public function testTheMomentIsBetweenTheYears1970And9999(): void
    {
        self::assertSame('1970-01-01T00:00:00+00:00', Activity::of('k', 'member:1', 'a', new DateTimeImmutable('1970-01-01 00:00:00 UTC'))->occurredAt->format(\DATE_ATOM));
        self::assertSame('9999-12-31T23:59:59+00:00', Activity::of('k', 'member:1', 'a', new DateTimeImmutable('9999-12-31 23:59:59 UTC'))->occurredAt->format(\DATE_ATOM));

        foreach (['1969-12-31 23:59:59 UTC', '+10000-01-01 00:00:00 UTC', '+100000000-01-01 00:00:00 UTC', '-1000000-01-01 00:00:00 UTC'] as $moment) {
            $at = new DateTimeImmutable($moment);

            try {
                Activity::of('k', 'member:1', 'a', $at);
                self::fail('The moment '.$moment.' was accepted.');
            } catch (InvalidActivity $exception) {
                self::assertSame('An activity happens between the years 1970 and 9999; this one is dated '.$at->format(\DATE_ATOM).'.', $exception->getMessage());
            }
        }
    }

    public function testTheQuantityIsBounded(): void
    {
        $at = new DateTimeImmutable('2026-11-07');
        self::assertSame(1_000_000_000, Activity::of('k', 'member:1', 'a', $at, quantity: 1_000_000_000)->quantity);

        foreach ([0, -1, 1_000_000_001] as $quantity) {
            try {
                Activity::of('k', 'member:1', 'a', $at, quantity: $quantity);
                self::fail('The quantity '.$quantity.' was accepted.');
            } catch (InvalidActivity $exception) {
                self::assertSame('The quantity of an activity is 1 to 1000000000; this one is '.$quantity.'.', $exception->getMessage());
            }
        }
    }

    public function testTheSameGestureLeavesTheMomentOut(): void
    {
        $activity = Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), 'game:1', 1, ['a' => 1]);

        self::assertTrue($activity->sameGestureAs(Activity::of('other', 'member:1', 'game.played', new DateTimeImmutable('2026-11-08'), 'game:1', 1, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:2', 'game.played', new DateTimeImmutable('2026-11-07'), 'game:1', 1, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:1', 'game.won', new DateTimeImmutable('2026-11-07'), 'game:1', 1, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), 'game:2', 1, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), null, 1, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), 'game:1', 2, ['a' => 1])));
        self::assertFalse($activity->sameGestureAs(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), 'game:1', 1, ['a' => 2])));

        self::assertFalse(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), null, 1, ['a' => 1])->sameGestureAs($activity));
        self::assertTrue(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'))->sameGestureAs(Activity::of('j', 'member:1', 'game.played', new DateTimeImmutable('2026-11-08'))));

        $detached = $activity->withoutObject();
        self::assertNull($detached->object);
        self::assertSame(['k', 'member:1', 'game.played', 1, ['a' => 1]], [$detached->key->value, $detached->member->toString(), $detached->verb->value, $detached->quantity, $detached->context->values]);
        self::assertSame($activity->occurredAt, $detached->occurredAt);
    }

    public function testAnEntry(): void
    {
        $activity = Activity::of('k', 'member:1', 'a', new DateTimeImmutable('2026-11-07'));
        $at = new DateTimeImmutable('2026-11-07 01:00');
        $entry = new Entry(1, EntryKind::Recorded, $activity, $at);

        self::assertSame([1, EntryKind::Recorded, $activity, $at], [$entry->position, $entry->kind, $entry->activity, $entry->at]);
        self::assertFalse($entry->isCancellation());
        self::assertTrue(new Entry(2, EntryKind::Cancelled, $activity, $at)->isCancellation());
        self::assertSame(['recorded', 'cancelled'], array_map(static fn (EntryKind $kind): string => $kind->value, EntryKind::cases()));

        $appended = new Appended($entry, true);
        self::assertSame([$entry, true], [$appended->entry, $appended->new]);
        self::assertSame([0, 0], [new Totals()->count, new Totals()->quantity]);
        self::assertSame([0, 0], [new Erasure()->deleted, new Erasure()->detached]);

        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('A position in the journal starts at 1; 0 is not one.');

        new Entry(0, EntryKind::Recorded, $activity, $at);
    }

    public function testAFilter(): void
    {
        $activity = Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 10:00:00 UTC'), 'game:1');
        $member = ResourceReference::of('member', '1');

        self::assertTrue(Filter::all()->matches($activity));
        self::assertTrue(Filter::all()->member($member)->matches($activity));
        self::assertFalse(Filter::all()->member(ResourceReference::of('member', '2'))->matches($activity));
        self::assertTrue(Filter::all()->verbs('news.*', 'game.*')->matches($activity));
        self::assertFalse(Filter::all()->verbs('news.*')->matches($activity));
        self::assertTrue(Filter::all()->verbs()->matches($activity));
        self::assertTrue(Filter::all()->verbs(...['games' => 'game.*'])->matches($activity));
        self::assertSame([0], array_keys(Filter::all()->verbs(...['games' => 'game.*'])->verbs->patterns));
        self::assertTrue(Filter::all()->object(ResourceReference::of('game', '1'))->matches($activity));
        self::assertFalse(Filter::all()->object(ResourceReference::of('game', '2'))->matches($activity));
        self::assertFalse(Filter::all()->object($member)->matches(Activity::of('k', 'member:1', 'a', new DateTimeImmutable())));
        self::assertTrue(Filter::all()->since(new DateTimeImmutable('2026-11-07 10:00:00 UTC'))->matches($activity));
        self::assertFalse(Filter::all()->since(new DateTimeImmutable('2026-11-07 10:00:01 UTC'))->matches($activity));
        self::assertTrue(Filter::all()->until(new DateTimeImmutable('2026-11-07 10:00:01 UTC'))->matches($activity));
        self::assertFalse(Filter::all()->until(new DateTimeImmutable('2026-11-07 10:00:00 UTC'))->matches($activity));

        $since = new DateTimeImmutable('2026-01-01');
        $until = new DateTimeImmutable('2027-01-01');
        $object = ResourceReference::of('game', '1');
        $filter = Filter::all()->member($member)->verbs('game.*')->object($object)->since($since)->until($until);
        self::assertSame([$member, ['game.*'], $object, $since, $until], [$filter->member, array_map(static fn (VerbPattern $pattern): string => $pattern->value, $filter->verbs->patterns), $filter->object, $filter->since, $filter->until]);
        $reordered = Filter::all()->until($until)->since($since)->object($object)->verbs('game.*')->member($member);
        self::assertSame([$member, ['game.*'], $object, $since, $until], [$reordered->member, array_map(static fn (VerbPattern $pattern): string => $pattern->value, $reordered->verbs->patterns), $reordered->object, $reordered->since, $reordered->until]);
        self::assertTrue($filter->matches($activity));
        self::assertNull(Filter::all()->member);
    }
}
