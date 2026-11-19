<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Infrastructure\Twig\TowerView;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerView::class)]
final class TowerViewTest extends TestCase
{
    public function testAnEntryInWordsWithTheLabelsOfTheFaces(): void
    {
        $view = new TowerView(Tower::customDice());

        self::assertSame(['coin' => ['heads' => 'Heads', 'tails' => 'Tails']], $view->faces());
        self::assertSame(
            [
                'position' => 5,
                'roller' => 'member:1',
                'name' => 'Aelis',
                'results' => [
                    ['expression' => '2d6kh1', 'total' => 4, 'text' => null, 'dice' => [['label' => '4', 'kept' => true], ['label' => '2', 'kept' => false]]],
                    ['expression' => '2d{coin}', 'total' => null, 'text' => 'heads, 3', 'dice' => [['label' => 'Heads', 'kept' => true], ['label' => '0', 'kept' => true], ['label' => '7', 'kept' => true]]],
                    ['expression' => '1d{gem}', 'total' => null, 'text' => 'red', 'dice' => [['label' => '2', 'kept' => true]]],
                    ['expression' => '6', 'total' => null, 'text' => null, 'dice' => []],
                    ['expression' => '', 'total' => null, 'text' => null, 'dice' => []],
                ],
            ],
            $view->entry($this->entry('member:1'), ['member:1' => 'Aelis', 'member:2' => 'Brom']),
        );
        self::assertNull($view->entry($this->entry('member:3'), ['member:1' => 'Aelis'])['name']);
        self::assertNull($view->entry($this->entry(null), ['member:1' => 'Aelis'])['name']);
        self::assertNull($view->entry($this->entry(null))['roller']);
    }

    public function testEveryDieHasItsFaces(): void
    {
        $view = new TowerView(new CustomDice(
            new DieDefinition('coin', new Face('heads', 'Heads'), new Face('tails', 'Tails')),
            new DieDefinition('gem', new Face('red', 'Red'), new Face('blue', 'Blue')),
        ));

        self::assertSame(['coin' => ['heads' => 'Heads', 'tails' => 'Tails'], 'gem' => ['red' => 'Red', 'blue' => 'Blue']], $view->faces());
    }

    public function testTheLabelsAndTextsGoThroughTheTranslatorOfTheDice(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Heads' => 'Pile', 'heads' => 'pile'], 'fr', 'tilttek_dice');

        $view = new TowerView(Tower::customDice(), new DiceExtension(new Tower()->dice, $translator));

        self::assertSame(['coin' => ['heads' => 'Pile', 'tails' => 'Tails']], $view->faces());
        self::assertSame('pile, 3', $view->entry($this->entry(null))['results'][1]['text']);
        self::assertSame('Pile', $view->entry($this->entry(null))['results'][1]['dice'][0]['label']);
    }

    private function entry(?string $roller): TowerEntry
    {
        return new TowerEntry(5, 'table:7', 'abc', $roller, new DateTimeImmutable('2026-11-12'), [
            ['expression' => '2d6kh1', 'total' => 4, 'output' => 4, 'sets' => [['notation' => '2d6kh1', 'die' => null, 'dice' => [['value' => 4, 'face' => null, 'kept' => true], ['value' => 2, 'face' => null, 'kept' => false]]]]],
            ['expression' => '2d{coin}', 'total' => null, 'output' => ['heads', 3], 'sets' => [['notation' => '2d{coin}', 'die' => 'coin', 'dice' => [['value' => 1, 'face' => 'heads', 'kept' => true], ['value' => 0, 'face' => 'joker', 'kept' => true], ['value' => 7, 'face' => null, 'kept' => true]]]]],
            ['expression' => '1d{gem}', 'total' => null, 'output' => 'red', 'sets' => [['notation' => '1d{gem}', 'die' => 'gem', 'dice' => [['value' => 2, 'face' => 'red', 'kept' => true]]]]],
            ['expression' => '6', 'total' => null, 'output' => 6, 'sets' => []],
            ['expression' => '', 'total' => null, 'output' => [], 'sets' => []],
        ]);
    }
}
