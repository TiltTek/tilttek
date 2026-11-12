<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\AccessibleDice\Application\RollAnnouncer;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Tests\Unit\Fixture\Dice;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Result\ExpressionResult;
use Tilttek\Dice\Result\Outputs;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Result\TextResult;

#[CoversClass(RollAnnouncer::class)]
final class RollAnnouncerTest extends TestCase
{
    public function testARollInOneSentence(): void
    {
        $result = Dice::roll('4d6kh3+1');
        $dice = $result->results[0]->result->sets()[0]->dice;
        $words = array_map(static fn (RolledDie $die): string => $die->kept ? (string) $die->value : $die->value.' not counted', $dice);
        $total = $result->totals()[0];
        self::assertNotNull($total);

        self::assertSame(\sprintf('4d6kh3+1: %s. Total %d.', implode(', ', $words), $total), new RollAnnouncer()->announce($result));
        self::assertSame(\sprintf('Aelis rolled. 4d6kh3+1: %s. Total %d.', implode(', ', $words), $total), new RollAnnouncer()->announce($result, 'Aelis'));
    }

    public function testCustomFacesAreSaidByTheirLabel(): void
    {
        $result = Dice::roll('2d{gem}; 3');
        $labels = array_map(static fn (RolledDie $die): string => $die->face->label ?? '', $result->results[0]->result->sets()[0]->dice);

        self::assertSame(\sprintf('2d{gem}: %1$s. Result: %1$s. 3: Total 3.', implode(', ', $labels)), new RollAnnouncer()->announce($result));
    }

    public function testTheSentenceIsTranslated(): void
    {
        $announcer = new RollAnnouncer(new Words(Dice::translator()));
        $coin = Dice::roll('1d{coin}');
        $face = $coin->results[0]->result->sets()[0]->dice[0]->face?->id;

        self::assertSame('heads' === $face ? '1d{coin} : Pile. Total 1.' : '1d{coin} : Face. Total 0.', $announcer->announce($coin));
        self::assertSame('Brom a lancé. 1d{coin} : '.('heads' === $face ? 'Pile. Total 1.' : 'Face. Total 0.'), $announcer->announce($coin, 'Brom'));
        self::assertSame('heads' === $face ? '1d{coin}: Heads. Total 1.' : '1d{coin}: Tails. Total 0.', $announcer->announce($coin, locale: 'en'));
    }

    public function testATextAListAndNothing(): void
    {
        self::assertSame('"fire": Result: fire.', new RollAnnouncer()->announce(Dice::roll('"fire"')));
        $list = Dice::roll('2L[coin,7]');
        $items = Outputs::items($list->results[0]->output());
        self::assertCount(2, $items);
        self::assertSame('2L[coin,7]: Result: '.implode(', ', $items).'.', new RollAnnouncer()->announce($list));
        self::assertSame('2L[coin,7] : Résultat : '.str_replace('coin', 'pièce', implode(', ', $items)).'.', new RollAnnouncer(new Words(Dice::translator()))->announce($list));

        $nothing = new PoolResult(new Pool(new Constant(0)), [new ExpressionResult(new Constant(0), new TextResult(''))]);
        self::assertSame('0: No result.', new RollAnnouncer()->announce($nothing));
        self::assertSame('0 : Aucun résultat.', new RollAnnouncer(new Words(Dice::translator()))->announce($nothing));
    }
}
