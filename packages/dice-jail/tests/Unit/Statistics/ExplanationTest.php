<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\FitResult;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Language;
use Tilttek\DiceJail\Statistics\Origin;
use Tilttek\DiceJail\Statistics\Tally;
use Tilttek\DiceJail\Statistics\Verdict;

#[CoversClass(Explanation::class)]
#[CoversClass(Language::class)]
final class ExplanationTest extends TestCase
{
    private const string NBSP = "\u{a0}";

    private const string NNBSP = "\u{202f}";

    public function testNothingUnusualInEnglish(): void
    {
        self::assertSame(
            'Nothing unusual in 30 rolls: a fair die spreads its faces at least this unevenly 8.2% of the time (p = 0.0821). Average roll: 1.67, against 2.00 for a fair die.',
            new Explanation()->explain($this->fit([15, 10, 5])),
        );
    }

    public function testNothingUnusualInFrench(): void
    {
        self::assertSame(
            'Rien d’inhabituel sur 30 lancers'.self::NBSP.': un dé équilibré répartit ses faces au moins aussi inégalement 8,2'.self::NBSP.'% du temps (p = 0,0821). Moyenne des lancers'.self::NBSP.': 1,67, contre 2,00 pour un dé équilibré.',
            new Explanation()->explain($this->fit([15, 10, 5]), Language::French),
        );
    }

    public function testALargeShareIsWrittenWithoutDecimals(): void
    {
        self::assertStringContainsString('at least this unevenly 100% of the time (p = 1.0000)', new Explanation()->explain($this->fit([10, 10, 10])));
        self::assertStringContainsString('inégalement 100'.self::NBSP.'% du temps', new Explanation()->explain($this->fit([10, 10, 10]), Language::French));
        // Two degrees: e^(−2.1) is 12.2 %.
        self::assertStringContainsString('unevenly 12% of the time', new Explanation()->explain($this->fit([14, 11, 5])));
    }

    public function testASharePastNineAndNineTenthsLosesItsDecimal(): void
    {
        self::assertStringContainsString('unevenly 10% of the time', new Explanation()->explain($this->withPValue(Verdict::NothingUnusual, 0.1)));
        self::assertStringContainsString('unevenly 9.9% of the time', new Explanation()->explain($this->withPValue(Verdict::NothingUnusual, 0.099)));
    }

    public function testTheSeriesCountIsRoundedToTheNearestWhole(): void
    {
        // 1 / 0.0055 is 181.8, 1 / 0.0058 is 172.4.
        self::assertStringContainsString('about once in 182 series', new Explanation()->explain($this->withPValue(Verdict::SomewhatUnusual, 0.0055)));
        self::assertStringContainsString('about once in 172 series', new Explanation()->explain($this->withPValue(Verdict::SomewhatUnusual, 0.0058)));
    }

    public function testSomewhatUnusual(): void
    {
        self::assertSame(
            'Somewhat unusual in 30 rolls: a fair die spreads its faces this unevenly about once in 181 series (p = 0.0055). It proves nothing; roll it again and judge the new rolls alone. Average roll: 1.53, against 2.00 for a fair die.',
            new Explanation()->explain($this->fit([18, 8, 4])),
        );
        self::assertSame(
            'Un peu inhabituel sur 30 lancers'.self::NBSP.': un dé équilibré répartit ses faces aussi inégalement environ une série sur 181 (p = 0,0055). Cela ne prouve rien'.self::NNBSP.'; relancez-le et jugez les nouveaux lancers seuls. Moyenne des lancers'.self::NBSP.': 1,53, contre 2,00 pour un dé équilibré.',
            new Explanation()->explain($this->fit([18, 8, 4]), Language::French),
        );
    }

    public function testVeryUnusualAsksForALookAtAPhysicalDieNeverCallingItLoaded(): void
    {
        self::assertSame(
            'Very unusual in 30 rolls: a fair die spreads its faces this unevenly fewer than once in 1,000 series (p = 0.0004). It is a reason to look at the die — its edges, its balance, how it is thrown — not proof that it is loaded. Average roll: 1.43, against 2.00 for a fair die.',
            new Explanation()->explain($this->fit([20, 7, 3])),
        );
        self::assertSame(
            'Très inhabituel sur 30 lancers'.self::NBSP.': un dé équilibré répartit ses faces aussi inégalement moins d’une série sur 1'.self::NNBSP.'000 (p = 0,0004). C’est une raison d’examiner le dé — ses arêtes, son équilibre, la façon de le lancer —, pas la preuve qu’il est pipé. Moyenne des lancers'.self::NBSP.': 1,43, contre 2,00 pour un dé équilibré.',
            new Explanation()->explain($this->fit([20, 7, 3]), Language::French),
        );
    }

    public function testDigitalRollsAreSaidToComeFromAGeneratorThatServesEveryDieAlike(): void
    {
        self::assertSame(
            'Very unusual in 30 rolls: a fair die spreads its faces this unevenly fewer than once in 1,000 series (p = 0.0004). Test new rolls before suspecting anything: chance remains the likeliest cause. Average roll: 1.43, against 2.00 for a fair die. These rolls come from a random generator that serves every die alike: no digital die is luckier than another, and the jail is only a game.',
            new Explanation()->explain($this->fit([20, 7, 3]), origin: Origin::Digital),
        );
        self::assertStringEndsWith(
            'Ces lancers viennent d’un générateur aléatoire qui sert tous les dés de la même façon'.self::NBSP.': aucun dé numérique n’est plus chanceux qu’un autre, et la prison n’est qu’un jeu.',
            new Explanation()->explain($this->fit([15, 10, 5]), Language::French, Origin::Digital),
        );
        self::assertStringContainsString(
            'Testez de nouveaux lancers avant de soupçonner quoi que ce soit'.self::NBSP.': le hasard reste la cause la plus probable.',
            new Explanation()->explain($this->fit([20, 7, 3]), Language::French, Origin::Digital),
        );
    }

    public function testATinyPValueIsWrittenAsLessThanItsSmallestValue(): void
    {
        self::assertStringContainsString('(p < 0.0001)', new Explanation()->explain($this->fit([60, 20, 10])));
        self::assertStringContainsString('(p < 0,0001)', new Explanation()->explain($this->fit([60, 20, 10]), Language::French));
        self::assertSame('p = 0.0001', new Explanation()->pValue(0.0001));
        self::assertSame('p < 0.0001', new Explanation()->pValue(0.0000999));
        self::assertSame('p = 0,5000', new Explanation()->pValue(0.5, Language::French));
    }

    public function testNotEnoughRolls(): void
    {
        $empty = new GoodnessOfFit()->test(Tally::of([]), ExpectedFaces::uniform(6));

        self::assertSame(
            'Not enough rolls to judge this die yet: 0 recorded, 30 needed. Until then, a streak is only chance.',
            new Explanation()->explain($empty),
        );
        self::assertSame(
            'Pas encore assez de lancers pour juger ce dé'.self::NBSP.': 0 notés, 30 nécessaires. D’ici là, une série n’est que le hasard.',
            new Explanation()->explain($empty, Language::French),
        );
        self::assertSame(
            'Not enough rolls to judge this die yet: 2 recorded, 30 needed. Until then, a streak is only chance. Average roll: 1.00, against 3.50 for a fair die.',
            new Explanation()->explain(new GoodnessOfFit()->test(Tally::of([1, 1]), ExpectedFaces::uniform(6))),
        );
    }

    public function testThousandsAreSeparatedTheWayOfEachLanguage(): void
    {
        $result = $this->fit([400, 400, 400]);

        self::assertStringStartsWith('Nothing unusual in 1,200 rolls', new Explanation()->explain($result));
        self::assertStringStartsWith('Rien d’inhabituel sur 1'.self::NNBSP.'200 lancers', new Explanation()->explain($result, Language::French));
    }

    public function testALocaleNamesItsLanguage(): void
    {
        self::assertSame(Language::French, Language::ofLocale('fr'));
        self::assertSame(Language::French, Language::ofLocale('fr_CA'));
        self::assertSame(Language::French, Language::ofLocale('FR-be'));
        self::assertSame(Language::English, Language::ofLocale('en_GB'));
        self::assertSame(Language::English, Language::ofLocale('de'));
        self::assertSame(Language::English, Language::ofLocale(''));
    }

    private function withPValue(Verdict $verdict, float $pValue): FitResult
    {
        return new FitResult([], 30, 2, 1.0, $pValue, 30, $verdict, 2.0, 2.0);
    }

    /**
     * @param list<int> $counts the count of each face of a three-faced die
     */
    private function fit(array $counts): FitResult
    {
        $faces = [];

        foreach ($counts as $index => $count) {
            array_push($faces, ...array_fill(0, $count, $index + 1));
        }

        return new GoodnessOfFit()->test(Tally::of($faces), ExpectedFaces::uniform(3));
    }
}
