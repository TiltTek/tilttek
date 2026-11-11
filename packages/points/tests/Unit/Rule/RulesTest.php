<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\VerbPattern;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\EarningRule;
use Tilttek\Points\Rule\Period;
use Tilttek\Points\Rule\Rules;

#[CoversClass(EarningRule::class)]
#[CoversClass(Rules::class)]
final class RulesTest extends TestCase
{
    public function testARule(): void
    {
        $rule = new EarningRule('games', ['game.played', 'game.won'], 10, 'stars', new Cap(50, Period::Week), ['system' => 'dnd5e', 'ranked' => true]);

        self::assertSame(['games', 10, 'stars', 50, true], [$rule->name, $rule->points, $rule->currency, $rule->cap?->amount, $rule->perQuantity]);
        self::assertSame(['game.played', 'game.won'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $rule->verbs->patterns));
        self::assertSame(['ranked' => true, 'system' => 'dnd5e'], $rule->conditions->values);

        self::assertTrue($rule->matches($this->activity('game.won', 1, ['system' => 'dnd5e', 'ranked' => true, 'other' => 1])));
        self::assertFalse($rule->matches($this->activity('game.lost', 1, ['system' => 'dnd5e', 'ranked' => true])));
        self::assertFalse($rule->matches($this->activity('game.played', 1, ['system' => 'pf2e', 'ranked' => true])));
        self::assertFalse($rule->matches($this->activity('game.played', 1, ['system' => 'dnd5e'])));
        self::assertFalse($rule->matches($this->activity('game.played', 1, ['system' => 'dnd5e', 'ranked' => 1])));
        self::assertSame(30, $rule->pointsFor($this->activity('game.played', 3)));

        $once = new EarningRule('orders', ['order.*'], 5, perQuantity: false);
        self::assertTrue($once->matches($this->activity('order.paid', 42)));
        self::assertSame(5, $once->pointsFor($this->activity('order.paid', 42)));
        self::assertNull($once->currency);
        self::assertNull($once->cap);
        self::assertSame([], $once->conditions->values);
    }

    public function testRulesAreChecked(): void
    {
        foreach (['Games', '9games', '', str_repeat('a', 65), "games\n"] as $name) {
            try {
                new EarningRule($name, ['game.played'], 1);
                self::fail('A name that is no rule name was expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('"%s" is not a rule name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name), $invalidPoints->getMessage());
            }
        }

        self::assertSame(64, \strlen(new EarningRule('a'.str_repeat('-', 63), ['x'], 1)->name));
        self::assertSame(1_000_000, new EarningRule('a', ['x'], 1_000_000)->points);

        foreach ([0, 1_000_001] as $points) {
            try {
                new EarningRule('games', ['game.played'], $points);
                self::fail('Points out of bounds were expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('A rule gives 1 to 1000000 points; "games" gives %d.', $points), $invalidPoints->getMessage());
            }
        }

        try {
            new EarningRule('games', [], 1);
            self::fail('A rule without verbs was expected to be refused.');
        } catch (InvalidPoints $invalidPoints) {
            self::assertSame('The rule "games" reads at least one verb.', $invalidPoints->getMessage());
        }

        $this->expectException(InvalidActivity::class);
        new EarningRule('games', ['Game'], 1);
    }

    public function testTheRules(): void
    {
        $rules = new Rules(new DateTimeZone('America/Toronto'), new EarningRule('wins', ['game.won', 'game.*'], 5), new EarningRule('games', ['game.*'], 1), new EarningRule('comments', ['news.commented'], 2, context: ['first' => true]));

        self::assertSame('America/Toronto', $rules->timezone->getName());
        self::assertSame(['comments', 'games', 'wins'], array_map(static fn (EarningRule $rule): string => $rule->name, $rules->all()));
        self::assertSame(['news.commented', 'game.*', 'game.won'], $rules->patterns());
        self::assertSame(['games', 'wins'], array_map(static fn (EarningRule $rule): string => $rule->name, $rules->matching($this->activity('game.won'))));
        self::assertSame([], $rules->matching($this->activity('news.commented')));
        self::assertSame(['comments'], array_map(static fn (EarningRule $rule): string => $rule->name, $rules->matching($this->activity('news.commented', 1, ['first' => true]))));
        self::assertSame([], new Rules(new DateTimeZone('UTC'))->patterns());

        $this->expectExceptionObject(InvalidPoints::because('Two rules are named "games".'));
        new Rules(new DateTimeZone('UTC'), new EarningRule('games', ['game.*'], 1), new EarningRule('games', ['game.won'], 1));
    }

    public function testTheRulesFromPlainValues(): void
    {
        $rules = Rules::fromArray([
            'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
            'orders' => ['verbs' => ['order.paid'], 'points' => 1, 'currency' => 'stars', 'per_quantity' => false, 'cap' => null, 'context' => ['online' => true]],
        ], 'Europe/Paris');

        self::assertSame('Europe/Paris', $rules->timezone->getName());
        [$games, $orders] = $rules->all();
        self::assertSame(['games', 10, null, 50, Period::Week, true, []], [$games->name, $games->points, $games->currency, $games->cap?->amount, $games->cap?->period, $games->perQuantity, $games->conditions->values]);
        self::assertSame(['orders', 1, 'stars', null, false, ['online' => true]], [$orders->name, $orders->points, $orders->currency, $orders->cap, $orders->perQuantity, $orders->conditions->values]);
        self::assertSame('UTC', Rules::fromArray([])->timezone->getName());

        try {
            Rules::fromArray([], 'Mars/Olympus');
            self::fail('A timezone that is none was expected to be refused.');
        } catch (InvalidPoints $invalidPoints) {
            self::assertSame('"Mars/Olympus" is not a timezone.', $invalidPoints->getMessage());
        }

        $this->expectExceptionObject(InvalidPoints::because('"fortnight" is not a period: day, week, month, year or ever.'));
        Rules::fromArray(['games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'fortnight']]]);
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function activity(string $verb, int $quantity = 1, array $context = []): Activity
    {
        return Activity::of('k', 'member:1', $verb, new DateTimeImmutable('2026-11-07 12:00:00 UTC'), null, $quantity, $context);
    }
}
