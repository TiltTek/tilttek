<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Application;

use ArrayObject;
use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Replay\ReplayableRoll;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\DiceBundle\Application\Accessibility;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Event\RollRecorded;
use Tilttek\DiceBundle\Application\Exception\UnknownRoll;
use Tilttek\DiceBundle\Application\NoDiceAppearance;
use Tilttek\DiceBundle\Application\Replay;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;
use Tilttek\DiceBundle\Tests\Unit\Fixture\Dice;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(DiceRolls::class)]
#[CoversClass(Replay::class)]
#[CoversClass(UnknownRoll::class)]
#[CoversClass(DieAppearance::class)]
#[CoversClass(NoDiceAppearance::class)]
#[CoversClass(RollRecorded::class)]
final class DiceRollsTest extends TestCase
{
    public function testInputsAreReadAndWrittenInTheReadersNotation(): void
    {
        $dice = Dice::rolls(ScriptedEngine::randomizer());
        $pool = $dice->parse('2n6+1d{coin}', 'fi');

        self::assertSame(['2d6+1d{coin}', '2n6+1n{coin}', '2W6+1W{coin}'], [$dice->write($pool), $dice->write($pool, 'fi'), $dice->write($pool, 'de')]);
        self::assertSame('/r 2d6', $dice->translate($dice->parse('2d6'), 'roll20')->text());
        self::assertSame('5d10!kh3', $dice->write($dice->readRolisteam('5D10K3')));
    }

    public function testAPlainRollComesFromTheSecureSource(): void
    {
        self::assertSame([5], Dice::rolls(ScriptedEngine::randomizer(1, 2))->roll('2d6')->totals());
        self::assertSame([7], Dice::rolls(ScriptedEngine::randomizer(5))->roll('1n6+1', 'fi')->totals());
        self::assertSame([2], Dice::rolls(ScriptedEngine::randomizer(1))->roll(new DiceParser()->parse('1d6'))->totals());
    }

    public function testAReplayableRollIsKeptAndReplayed(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $dice = Dice::rolls(ScriptedEngine::randomizer(...range(1, 24)), $journal);
        $member = ResourceReference::of('member', '1');

        $recorded = $dice->rollReplayable('4D6KH3', $member);
        $other = $dice->rollReplayable('1n6', null, 'fi');

        self::assertSame($recorded, $journal->get('01000000020000000300000004000000'));
        self::assertSame(['4d6kh3', 'member:1', '2026-11-07 03:00:00'], [$recorded->expression(), $recorded->owner()?->toString(), $recorded->rolledAt()->format('Y-m-d H:i:s')]);
        self::assertSame(['1d6', null], [$other->expression(), $other->owner()]);
        self::assertSame([$recorded], $dice->rollsOf($member, 50));
        self::assertSame([], $dice->rollsOf($member, 0));

        $replay = $dice->replay($recorded->id());
        self::assertSame($recorded, $replay->recorded);
        self::assertTrue($replay->matches());
        self::assertSame($recorded->result(), $replay->result->toArray());
        self::assertSame(1, $dice->eraseOwner($member));
        self::assertNull($journal->get($recorded->id()));
    }

    public function testARollKeepsTheRoundingItIsGiven(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $dice = Dice::rolls(ScriptedEngine::randomizer(...range(1, 40)), $journal);

        self::assertSame([1, 2], [Dice::rolls(ScriptedEngine::randomizer(2))->roll('1d6/2')->totals()[0], Dice::rolls(ScriptedEngine::randomizer(2))->roll('1d6/2', null, RoundingMode::PositiveInfinity)->totals()[0]]);

        $recorded = $dice->rollReplayable('repeat(1d6/4, 4)', null, null, RoundingMode::PositiveInfinity);
        $replay = $dice->replay($recorded->id());

        self::assertSame(RoundingMode::PositiveInfinity, $recorded->rounding());
        self::assertSame(RoundingMode::PositiveInfinity, $replay->result->rounding);
        self::assertTrue($replay->matches());

        $seed = $dice->newSeed();
        $roll = $dice->rollVerifiable('repeat(1d6/4, 4)', $seed, 'player', 1, null, RoundingMode::PositiveInfinity);
        self::assertSame(RoundingMode::PositiveInfinity, $roll->result->rounding);
        self::assertTrue($dice->verify($seed->commitment(1), $seed, 'player', 1, 'repeat(1d6/4, 4)', $roll->result->toArray(), RoundingMode::PositiveInfinity));
        self::assertSame(['1d6/2 is written rounded down; the roll rounds it PositiveInfinity.'], $dice->translate($dice->parse('1d6/2'), 'roll20', RoundingMode::PositiveInfinity)->notices);
        self::assertSame([], $dice->translate($dice->parse('1d6/2'), 'roll20')->notices);
    }

    public function testTheWholeLanguageIsKeptAndReplayed(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $dice = Dice::rolls(ScriptedEngine::randomizer(...range(1, 12)), $journal);
        $recorded = $dice->rollReplayable('8d10; @1c[>=6]; m[1]kh2; {2d6, 1d12}kh1; 3Lu[a,b[2],c]; 1d20i[>=10]{"hit $2"}{"miss"}; "%3"');

        self::assertTrue($dice->replay($recorded->id())->matches());
        self::assertCount(7, $recorded->result());
    }

    public function testATamperedRollDoesNotMatchItsReplay(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $pool = new DiceParser()->parse('3d6');
        $honest = new Replayer()->roll($pool, ScriptedEngine::randomizer(...range(1, 12)));
        $other = new Replayer()->roll($pool, ScriptedEngine::randomizer(...range(13, 24)));
        $journal->save(RecordedRoll::record(new ReplayableRoll($honest->id, $honest->seed, $other->result), null, new DateTimeImmutable()));

        self::assertFalse(Dice::rolls(ScriptedEngine::randomizer(), $journal)->replay($honest->id)->matches());
    }

    public function testAnUnknownRollCannotBeReplayed(): void
    {
        $this->expectExceptionObject(new UnknownRoll('No recorded roll has the identifier "nothing".'));
        Dice::rolls(ScriptedEngine::randomizer())->replay('nothing');
    }

    public function testAVerifiableRollIsCheckedAgainstItsCommitment(): void
    {
        $dice = Dice::rolls(ScriptedEngine::randomizer(1, 2, 3, 4, 5, 6, 7, 8));
        $seed = $dice->newSeed();

        self::assertSame('0100000002000000030000000400000005000000060000000700000008000000', $seed->hex());

        $roll = $dice->rollVerifiable('2n20', $seed, 'player', 4, 'fi');
        self::assertSame(['2d20', 'player', 4], [$roll->result->pool->canonical(), $roll->clientSeed, $roll->nonce]);
        self::assertTrue($dice->verify($seed->commitment(4), $seed, 'player', 4, '2d20', $roll->result->toArray()));
        self::assertFalse($dice->verify($seed->commitment(4), $seed, 'player', 5, '2d20', $roll->result->toArray()));
        self::assertTrue($dice->rollVerifiable('2d20', $seed)->result->equals($dice->rollVerifiable('2d20', $seed, '', 0)->result));
        self::assertTrue($dice->rollVerifiable(new DiceParser()->parse('2d20'), $seed, 'player', 4)->result->equals($roll->result));
        self::assertTrue($dice->verify(Seed::fromHex($seed->hex())->commitment(4), $seed, 'player', 4, new DiceParser()->parse('2d20'), $roll->result->toArray()));
    }

    public function testAMembersDiceAreDressedByTheExtensionPoint(): void
    {
        $member = ResourceReference::of('member', '1');

        self::assertNull(Dice::rolls(ScriptedEngine::randomizer())->appearance($member, 6));
        self::assertNull(new NoDiceAppearance()->appearance($member, 6));

        $appearance = Dice::rolls(ScriptedEngine::randomizer(), appearance: Dice::redDice())->appearance($member, 6);
        self::assertSame(['Ember', '#aa0000', 'resin', 'ember.png', null, 'dice/ember.webp'], [$appearance?->name, $appearance?->color, $appearance?->material, $appearance?->image, $appearance?->ink, $appearance?->texture]);
    }

    public function testTheViewersAccessibilityReachesTheExtensionPoint(): void
    {
        $member = ResourceReference::of('member', '1');
        $dice = Dice::rolls(ScriptedEngine::randomizer(), appearance: Dice::redDice());

        self::assertNull(new NoDiceAppearance()->appearance($member, 6, Accessibility::HighContrast));
        self::assertSame(['#000000', '#ffffff', null], [$dice->appearance($member, 6, Accessibility::HighContrast)?->color, $dice->appearance($member, 6, Accessibility::HighContrast)?->ink, $dice->appearance($member, 6, Accessibility::HighContrast)?->texture]);
        self::assertSame('#aa0000', $dice->appearance($member, 6, Accessibility::Standard)?->color);
        self::assertSame(['standard', 'high-contrast'], array_map(static fn (Accessibility $case): string => $case->value, Accessibility::cases()));
    }

    public function testTheInkIsTheOneGivenOrOneThatReadsOnTheColour(): void
    {
        self::assertSame('#ffff00', new DieAppearance('Ember', '#aa0000', ink: '#ffff00')->readableInk());
        self::assertSame('#ffffff', new DieAppearance('Ember', '#aa0000')->readableInk());
        self::assertSame('#000000', new DieAppearance('Moss', '#2e8b57')->readableInk());
        self::assertNull(new DieAppearance('Plain')->readableInk());
        self::assertSame('#123456', new DieAppearance('Plain', ink: '#123456')->readableInk());
        self::assertSame(
            ['name' => 'Ember', 'color' => '#aa0000', 'ink' => '#ffffff', 'texture' => 'dice/ember.webp', 'material' => 'resin', 'image' => 'ember.png'],
            new DieAppearance('Ember', '#aa0000', 'resin', 'ember.png', texture: 'dice/ember.webp')->toArray(),
        );
        self::assertSame(
            ['name' => 'Plain', 'color' => null, 'ink' => null, 'texture' => null, 'material' => null, 'image' => null],
            new DieAppearance('Plain')->toArray(),
        );
    }

    public function testATextureIsAKeyNeverAnAddress(): void
    {
        foreach (['a', '0', 'dice/marble-01.webp', 'dice/stone_2/grain.png', str_repeat('a', 128)] as $texture) {
            self::assertSame($texture, new DieAppearance('Stone', texture: $texture)->texture);
        }

        foreach (['', '.hidden', '/dice/stone', '-stone', '_stone', 'Dice/stone', 'dice/../secret', 'dice//stone', 'dice/', 'https://example.com/stone.png', 'dice\\stone', 'dice stone', "stone\n", str_repeat('a', 129)] as $texture) {
            try {
                new DieAppearance('Stone', texture: $texture);
                self::fail(\sprintf('The texture "%s" was accepted.', $texture));
            } catch (InvalidDefinition $exception) {
                self::assertSame(\sprintf('The texture of a die is a key of 1 to 128 characters among a-z, 0-9, ".", "_", "/" and "-", starting with a letter or a digit, without "..", "//" or a final "/"; "%s" is not.', $texture), $exception->getMessage());
            }
        }
    }

    public function testAnAppearanceHasANameAndAColourOfSixDigits(): void
    {
        self::assertSame([null, null, null, null, null], [new DieAppearance('Plain')->color, new DieAppearance('Plain')->material, new DieAppearance('Plain')->image, new DieAppearance('Plain')->ink, new DieAppearance('Plain')->texture]);
        self::assertSame(['#AbCdEf', '#00ff99'], [new DieAppearance('Ember', '#AbCdEf', ink: '#00ff99')->color, new DieAppearance('Ember', '#AbCdEf', ink: '#00ff99')->ink]);

        foreach ([['', null, 'The appearance of a die has a name.'], [' ', null, 'The appearance of a die has a name.'], ['Ember', '#fff', 'The colour of a die is written #rrggbb; "#fff" is not.'], ['Ember', 'x#ffffff', 'The colour of a die is written #rrggbb; "x#ffffff" is not.'], ['Ember', "#ffffff\n", "The colour of a die is written #rrggbb; \"#ffffff\n\" is not."]] as [$name, $color, $message]) {
            try {
                new DieAppearance($name, $color);
                self::fail('The appearance was accepted.');
            } catch (InvalidDefinition $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        foreach (['#fff', 'x#ffffff', "#ffffff\n", 'white'] as $ink) {
            try {
                new DieAppearance('Ember', '#aa0000', ink: $ink);
                self::fail('The ink was accepted.');
            } catch (InvalidDefinition $exception) {
                self::assertSame(\sprintf('The ink of a die is written #rrggbb; "%s" is not.', $ink), $exception->getMessage());
            }
        }
    }

    public function testARollIsKeptWithItsContextAndAnnounced(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $events = new EventDispatcher();
        $heard = [];
        $events->addListener(RollRecorded::class, static function (RollRecorded $event) use (&$heard, $journal): void {
            $heard[] = [$event->id, $event->owner?->toString(), $event->context?->toString(), $journal->get($event->id) instanceof RecordedRoll];
        });
        $dice = new DiceRolls(Dice::roller(), ScriptedEngine::randomizer(...range(1, 36)), $journal, new MockClock('2026-11-07 03:00:00 UTC'), new NoDiceAppearance(), $events);
        $table = ResourceReference::of('table', '7');

        $first = $dice->rollReplayable('1d6', ResourceReference::of('member', '1'), context: $table);
        $second = $dice->rollReplayable('1d8');
        $third = $dice->rollReplayable('1d10', null, null, RoundingMode::PositiveInfinity, $table);

        self::assertSame([[$first->id(), 'member:1', 'table:7', true], [$second->id(), null, null, true], [$third->id(), null, 'table:7', true]], $heard);
        self::assertSame(['table:7', null, 'table:7'], [$first->context()?->toString(), $second->context(), $third->context()?->toString()]);
        self::assertSame([$first, $third], $dice->rollsIn($table, 10));
        self::assertSame([$first], $dice->rollsIn($table, 1));
        self::assertSame([], $dice->rollsIn(ResourceReference::of('table', '8'), 10));
    }

    public function testRecordedRollsAreReadManyAtOnceWithinABound(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $dice = Dice::rolls(ScriptedEngine::randomizer(...range(1, 24)), $journal);
        $first = $dice->rollReplayable('1d6');
        $second = $dice->rollReplayable('1d6');

        self::assertSame([$second, $first], $dice->recorded([$second->id(), 'unknown', $first->id(), $second->id()]));
        self::assertSame([], $dice->recorded([]));

        /** @var ArrayObject<int, list<string>> $asked */
        $asked = new ArrayObject();
        $spy = new readonly class($asked) implements RecordedRollRepository {
            /** @param ArrayObject<int, list<string>> $asked */
            public function __construct(private ArrayObject $asked)
            {
            }

            #[Override]
            public function get(string $id): ?RecordedRoll
            {
                return null;
            }

            #[Override]
            public function getMany(array $ids): array
            {
                $this->asked->append($ids);

                return [];
            }

            #[Override]
            public function save(RecordedRoll $roll): void
            {
            }

            #[Override]
            public function ofOwner(ResourceReference $owner, int $limit): array
            {
                return [];
            }

            #[Override]
            public function ofContext(ResourceReference $context, int $limit): array
            {
                return [];
            }

            #[Override]
            public function eraseOwner(ResourceReference $owner): int
            {
                return 0;
            }
        };
        Dice::rolls(ScriptedEngine::randomizer(...range(1, 12)), $spy)->recorded(['a', 'a', 'b']);
        self::assertSame([['a', 'b']], $asked->getArrayCopy());

        self::assertCount(1, $dice->recorded([...array_fill(0, RecordedRollRepository::MANY + 1, $first->id())]));
        self::assertSame([$first], $dice->recorded([$first->id(), ...array_map(static fn (int $i): string => \sprintf('%032d', $i), range(1, RecordedRollRepository::MANY - 1))]));

        $this->expectExceptionObject(LimitExceeded::of('rolls read at once', 500, 501));
        $dice->recorded(array_map(static fn (int $i): string => \sprintf('%032d', $i), range(0, RecordedRollRepository::MANY)));
    }

    public function testAKeyedRollIsKeptOnceAndItsEventResumed(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $events = new EventDispatcher();
        /** @var ArrayObject<int, mixed> $heard */
        $heard = new ArrayObject();
        $failures = 1;
        $events->addListener(RollRecorded::class, static function (RollRecorded $event) use ($heard, &$failures): void {
            if ($failures > 0) {
                --$failures;

                throw new RuntimeException('The listener failed.');
            }

            $heard[] = $event->key();
        });
        $dice = new DiceRolls(Dice::roller(), ScriptedEngine::randomizer(...range(1, 60)), $journal, new MockClock('2026-11-07 03:00:00 UTC'), new NoDiceAppearance(), $events);
        $member = ResourceReference::of('member', '1');

        try {
            $dice->rollReplayable('3d6', $member, key: 'turn-4');
            self::fail('The listener did not fail.');
        } catch (RuntimeException) {
        }

        [$kept] = $dice->rollsOf($member, 10);
        self::assertFalse($kept->isDelivered());
        self::assertSame(substr(hash('sha256', '["member:1","turn-4"]'), 0, 32), $kept->id());

        // The same key again: the roll kept is not rolled again, and its event is dispatched now.
        $again = $dice->rollReplayable('3d6', $member, key: 'turn-4');
        self::assertSame($kept, $again);
        self::assertTrue($again->isDelivered());
        self::assertSame([$kept->id()], $heard->getArrayCopy());
        self::assertSame($again, $dice->rollReplayable('1d20', $member, key: 'turn-4'));
        self::assertSame([$kept->id()], $heard->getArrayCopy());
        self::assertCount(1, $dice->rollsOf($member, 10));
        self::assertTrue($dice->replay($kept->id())->matches());

        // A key is the owner's: another owner, or no owner, rolls anew under the same key.
        $other = $dice->rollReplayable('3d6', ResourceReference::of('member', '2'), key: 'turn-4');
        $nobody = $dice->rollReplayable('3d6', key: 'turn-4');
        self::assertCount(3, array_unique([$kept->id(), $other->id(), $nobody->id()]));
        self::assertMatchesRegularExpression(Replayer::ID_PATTERN, $other->id());
        self::assertSame($nobody, $dice->rollReplayable('3d6', key: 'turn-4'));
        $next = $dice->rollReplayable('3d6', $member, key: 'turn-5');
        self::assertNotSame($kept->id(), $next->id());
        self::assertSame([$kept->id(), $other->id(), $nobody->id(), $next->id()], $heard->getArrayCopy());
    }

    public function testTheEventOfARollIsResumedByDeliver(): void
    {
        $journal = new InMemoryRecordedRollRepository();
        $events = new EventDispatcher();
        /** @var ArrayObject<int, mixed> $heard */
        $heard = new ArrayObject();
        $failures = 1;
        $events->addListener(RollRecorded::class, static function (RollRecorded $event) use ($heard, &$failures): void {
            if ($failures > 0) {
                --$failures;

                throw new RuntimeException('The listener failed.');
            }

            $heard[] = [$event->id, $event->owner?->toString(), $event->context?->toString()];
        });
        $dice = new DiceRolls(Dice::roller(), ScriptedEngine::randomizer(...range(1, 36)), $journal, new MockClock('2026-11-07 03:00:00 UTC'), new NoDiceAppearance(), $events);
        $table = ResourceReference::of('table', '7');

        try {
            $dice->rollReplayable('1d6', ResourceReference::of('member', '1'), context: $table);
            self::fail('The listener did not fail.');
        } catch (RuntimeException) {
        }

        [$kept] = $dice->rollsIn($table, 1);
        self::assertSame([], $heard->getArrayCopy());
        self::assertTrue($dice->deliver($kept->id()));
        self::assertSame([[$kept->id(), 'member:1', 'table:7']], $heard->getArrayCopy());
        self::assertTrue($journal->get($kept->id())?->isDelivered());
        self::assertFalse($dice->deliver($kept->id()));
        self::assertCount(1, $heard);

        $this->expectExceptionObject(UnknownRoll::withId('00000000000000000000000000000000'));
        $dice->deliver('00000000000000000000000000000000');
    }

    public function testARollNotedDeliveredIsSavedSo(): void
    {
        // A journal that hands out copies, as a database does: the note is kept only once it is saved.
        $journal = new readonly class implements RecordedRollRepository {
            private InMemoryRecordedRollRepository $rolls;

            public function __construct()
            {
                $this->rolls = new InMemoryRecordedRollRepository();
            }

            public function get(string $id): ?RecordedRoll
            {
                $roll = $this->rolls->get($id);

                return $roll instanceof RecordedRoll ? clone $roll : null;
            }

            public function getMany(array $ids): array
            {
                return $this->rolls->getMany($ids);
            }

            public function save(RecordedRoll $roll): void
            {
                $this->rolls->save(clone $roll);
            }

            public function ofOwner(ResourceReference $owner, int $limit): array
            {
                return $this->rolls->ofOwner($owner, $limit);
            }

            public function ofContext(ResourceReference $context, int $limit): array
            {
                return $this->rolls->ofContext($context, $limit);
            }

            public function eraseOwner(ResourceReference $owner): int
            {
                return $this->rolls->eraseOwner($owner);
            }
        };
        $events = new EventDispatcher();
        $failures = 1;
        $events->addListener(RollRecorded::class, static function () use (&$failures): void {
            if ($failures > 0) {
                --$failures;

                throw new RuntimeException('The listener failed.');
            }
        });
        $dice = new DiceRolls(Dice::roller(), ScriptedEngine::randomizer(...range(1, 36)), $journal, new MockClock('2026-11-07 03:00:00 UTC'), new NoDiceAppearance(), $events);

        try {
            $dice->rollReplayable('1d6', key: 'failed');
            self::fail('The listener did not fail.');
        } catch (RuntimeException) {
        }

        $id = substr(hash('sha256', '[null,"failed"]'), 0, 32);
        self::assertFalse($journal->get($id)?->isDelivered());
        self::assertTrue($dice->deliver($id));
        $saved = $journal->get($id);
        self::assertInstanceOf(RecordedRoll::class, $saved);
        self::assertTrue($saved->isDelivered());
        self::assertFalse($dice->deliver($id));
        self::assertTrue($dice->rollReplayable('1d6', key: 'copied')->isDelivered());
        self::assertTrue($journal->get(substr(hash('sha256', '[null,"copied"]'), 0, 32))?->isDelivered());
    }

    public function testARollIsDeliveredAtOnceWithoutADispatcher(): void
    {
        $dice = Dice::rolls(ScriptedEngine::randomizer(...range(1, 36)));
        $roll = $dice->rollReplayable('1d6', key: 'once');

        self::assertTrue($roll->isDelivered());
        self::assertFalse($dice->deliver($roll->id()));
        self::assertSame($roll, $dice->rollReplayable('1d6', key: 'once'));
    }
}
