<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Benchmark;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

/**
 * The paths of a sale: a draw committed then revealed after payment, its proof verified, and the codes of the largest
 * box a shop writes — ten d20 tables — written and read back.
 */
final readonly class LootDrawerBench
{
    private LootBox $box;

    private LootDrawer $drawer;

    private PendingDraw $pending;

    /**
     * @var array<string, mixed>
     */
    private array $proof;

    private LootResult $result;

    public function __construct()
    {
        $entries = array_map(static fn (int $face): LootEntry => new LootEntry('e'.$face, 'Entry '.$face, '/loot/'.$face.'.png', 'slot'), range(1, 20));
        $this->box = new LootBox('bench', 'Bench', array_fill(0, 10, new LootTable('d20', 'D20', $entries)));
        $this->drawer = new LootDrawer();
        $this->pending = PendingDraw::open(new Randomizer(new Xoshiro256StarStar(42)));
        $revealed = $this->drawer->reveal($this->box, $this->pending, 'buyer');
        $this->proof = $revealed->proof();
        $this->result = $revealed->result;
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchReveal(): void
    {
        $this->drawer->reveal($this->box, $this->pending, 'buyer');
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchVerify(): void
    {
        $this->drawer->verify($this->box, $this->proof);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchShopCode(): void
    {
        ShopCode::read($this->box, ShopCode::write($this->result));
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchDrawText(): void
    {
        DrawText::read($this->box, DrawText::write($this->result), DrawSource::Verifiable);
    }
}
