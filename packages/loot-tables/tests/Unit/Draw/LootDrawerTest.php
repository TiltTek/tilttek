<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Draw;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\Draw\RevealedDraw;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(LootDrawer::class)]
#[CoversClass(RevealedDraw::class)]
final class LootDrawerTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testTheRevealRollsOneDiePerTableFromTheSeed(): void
    {
        $box = Loot::box([6, 6, 6, 6, 6, 6]);
        $pending = PendingDraw::restore(self::SEED);
        $drawer = new LootDrawer();
        $revealed = $drawer->reveal($box, $pending, 'order 12/line 1/unit 1');

        self::assertSame(DrawSource::Verifiable, $revealed->result->source);
        self::assertSame($box, $revealed->result->box);
        self::assertSame($revealed->roll->result->totals(), $revealed->result->faces());
        self::assertSame($pending->commitment()->hex, $revealed->roll->commitment->hex);
        self::assertSame($revealed->result->faces(), $drawer->reveal($box, $pending, 'order 12/line 1/unit 1')->result->faces());
        self::assertNotSame($revealed->result->faces(), $drawer->reveal($box, $pending, 'order 12/line 1/unit 2')->result->faces());
        self::assertSame([6, 5, 6, 4, 5, 4], $revealed->result->faces());
    }

    public function testTheBuyerGivesTheClientSeedAfterTheCommitmentIsShown(): void
    {
        $box = Loot::box([20, 8]);
        $drawer = new LootDrawer();
        $pending = PendingDraw::restore(self::SEED);
        $mine = $drawer->reveal($box, $pending, 'my lucky words');
        $theirs = $drawer->reveal($box, $pending, 'other words');

        self::assertSame('my lucky words', $mine->proof()['client_seed']);
        self::assertSame('other words', $theirs->proof()['client_seed']);
        self::assertNotSame($mine->result->faces(), $theirs->result->faces());
        self::assertTrue($drawer->verify($box, $mine->proof()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedClientSeeds(): iterable
    {
        yield 'none' => [''];
        yield 'too long' => [str_repeat('a', 257)];
        yield 'not text' => ["\xff\xfe"];
    }

    #[DataProvider('refusedClientSeeds')]
    public function testAClientSeedIsOneToTwoHundredFiftySixBytes(string $clientSeed): void
    {
        self::assertSame(256, \strlen(new LootDrawer()->reveal(Loot::box([6]), PendingDraw::restore(self::SEED), str_repeat('a', 256))->proof()['client_seed']));
        self::assertSame('a', new LootDrawer()->reveal(Loot::box([6]), PendingDraw::restore(self::SEED), 'a')->proof()['client_seed']);

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A client seed is 1 to 256 bytes of UTF-8 text.');

        new LootDrawer()->reveal(Loot::box([6]), PendingDraw::restore(self::SEED), $clientSeed);
    }

    public function testTheProofIsWhatAnyoneVerifies(): void
    {
        $box = Loot::box([20, 8]);
        $drawer = new LootDrawer();
        $revealed = $drawer->reveal($box, PendingDraw::restore(self::SEED, 4), 'buyer');
        $proof = $revealed->proof();

        self::assertSame('box', $proof['box']);
        self::assertSame([9, 7], $proof['faces']);
        self::assertSame(hash('sha256', (string) hex2bin(self::SEED)), $proof['commitment']);
        self::assertSame(self::SEED, $proof['seed']);
        self::assertSame('buyer', $proof['client_seed']);
        self::assertSame(4, $proof['nonce']);
        self::assertSame('1d20, 1d8', $proof['expression']);
        self::assertSame('NegativeInfinity', $proof['rounding']);
        self::assertSame($revealed->roll->result->toArray(), $proof['result']);
        self::assertTrue($drawer->verify($box, $proof));
        $decoded = json_decode(json_encode($proof, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertTrue($drawer->verify($box, $decoded));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function tampered(): iterable
    {
        yield 'another box' => ['box', 'other'];
        yield 'no box' => ['box', null];
        yield 'other faces' => ['faces', [1, 1]];
        yield 'no faces' => ['faces', null];
        yield 'another commitment' => ['commitment', str_repeat('0', 64)];
        yield 'a commitment that does not read' => ['commitment', 'not hex'];
        yield 'a commitment that is no text' => ['commitment', 12];
        yield 'another seed' => ['seed', str_repeat('1', 64)];
        yield 'a seed that does not read' => ['seed', 'not hex'];
        yield 'a seed that is no text' => ['seed', 12];
        yield 'another client seed' => ['client_seed', 'somebody else'];
        yield 'a client seed that is no text' => ['client_seed', 12];
        yield 'another nonce' => ['nonce', 5];
        yield 'a nonce that is no number' => ['nonce', '4'];
        yield 'another expression' => ['expression', '1d20, 1d6'];
        yield 'another rounding' => ['rounding', 'PositiveInfinity'];
        yield 'a result that is no list' => ['result', ['a' => []]];
        yield 'a result that is no array' => ['result', 'none'];
    }

    #[DataProvider('tampered')]
    public function testATamperedProofIsRefused(string $field, mixed $value): void
    {
        $box = Loot::box([20, 8]);
        $drawer = new LootDrawer();
        $proof = $drawer->reveal($box, PendingDraw::restore(self::SEED, 4), 'buyer')->proof();
        $proof[$field] = $value;

        self::assertFalse($drawer->verify($box, $proof));
    }

    public function testAResultRolledAgainDiffers(): void
    {
        $box = Loot::box([20, 8]);
        $drawer = new LootDrawer();
        $proof = $drawer->reveal($box, PendingDraw::restore(self::SEED, 4), 'buyer')->proof();
        $other = $drawer->reveal($box, PendingDraw::restore(self::SEED, 5), 'buyer')->proof();
        $proof['result'] = $other['result'];
        $proof['faces'] = $other['faces'];

        self::assertFalse($drawer->verify($box, $proof));
    }

    public function testAProofOfAnotherBoxOfTheSameKey(): void
    {
        $drawer = new LootDrawer();
        $proof = $drawer->reveal(Loot::box([20, 8]), PendingDraw::restore(self::SEED), 'buyer')->proof();

        self::assertFalse($drawer->verify(Loot::box([20, 6]), $proof));
        self::assertFalse($drawer->verify(Loot::box([20, 8], 'other'), $proof));
    }
}
