<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\Draw\RevealedDraw;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;

/**
 * The draw of one unit of an option on an order line: committed when the order is placed — its secret seed kept, its
 * commitment shown —, revealed once the order is paid, or entered from physical dice at a register.
 *
 * Its identifier is derived from the order, the line, the option and the unit, so that each unit has one draw. The
 * roll comes from the seed alone: the client seed the library mixes in is this identifier, public and fixed before the
 * seed was drawn. The seed stays secret until the reveal, then is published once in the proof, so that anyone may roll
 * the draw again. A draw of a text keeps the list it is drawn from as it was when committed.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'tilttek_loot_draw_order', columns: ['order_number'])]
final class LootDraw
{
    public const string TABLE = 'tilttek_loot_draw';

    /** @var array{source: string, faces: list<int>}|null */
    #[ORM\Column(name: 'result', type: 'json', nullable: true)]
    private ?array $result = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'proof', type: 'json', nullable: true)]
    private ?array $proof = null;

    #[ORM\Column(name: 'drawn', type: 'text', nullable: true)]
    private ?string $drawn = null;

    #[ORM\Column(name: 'revealed_at', nullable: true)]
    private ?DateTimeImmutable $revealedAt = null;

    /**
     * @param list<string>|null $texts
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 32)]
        private readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        private readonly string $orderNumber,
        #[ORM\Column(name: 'line', length: 180)]
        private readonly string $line,
        #[ORM\Column(name: 'option_code', length: 32)]
        private readonly string $option,
        #[ORM\Column(name: 'unit', type: 'integer')]
        private readonly int $unit,
        #[ORM\Column(name: 'drawer', length: 64)]
        private readonly string $drawer,
        #[ORM\Column(name: 'seed', length: 64, nullable: true)]
        private readonly ?string $seed,
        #[ORM\Column(name: 'commitment', length: 64, nullable: true)]
        private readonly ?string $commitment,
        #[ORM\Column(name: 'texts', type: 'json', nullable: true)]
        private readonly ?array $texts,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * The identifier of the draw of one unit: 32 hexadecimal digits.
     */
    public static function idOf(string $order, string $line, string $option, int $unit): string
    {
        return substr(hash('sha256', json_encode([$order, $line, $option, $unit], \JSON_THROW_ON_ERROR)), 0, 32);
    }

    /**
     * A draw committed: its seed kept secret until it is revealed, its commitment shown at once.
     *
     * @param PendingDraw       $pending its seed and nonce
     * @param list<string>|null $texts   the list a text is drawn from, for a text
     */
    public static function commit(string $order, string $line, string $option, int $unit, string $drawer, PendingDraw $pending, ?array $texts, DateTimeImmutable $at): self
    {
        return new self(self::idOf($order, $line, $option, $unit), $order, $line, $option, $unit, $drawer, $pending->seed->hex(), $pending->commitment()->hex, $texts, $at);
    }

    /**
     * A draw of physical dice, entered as they fell, never committed.
     */
    public static function physical(string $order, string $line, string $option, int $unit, string $drawer, LootResult $result, string $drawn, DateTimeImmutable $at): self
    {
        $draw = new self(self::idOf($order, $line, $option, $unit), $order, $line, $option, $unit, $drawer, null, null, null, $at);
        $draw->enter($result, $drawn, $at);

        return $draw;
    }

    /**
     * The client seed the reveal rolls with: the identifier of the draw, public and fixed before its seed was drawn.
     */
    public function clientSeedOfReveal(): string
    {
        return $this->id;
    }

    /**
     * Records the reveal of the committed draw, with its proof and what the order line keeps of it.
     *
     * @throws LootDrawRefused when the draw was revealed already, was never committed, or was rolled with another
     *                         seed or another client seed
     */
    public function reveal(RevealedDraw $revealed, string $drawn, DateTimeImmutable $at): void
    {
        $this->checkOpen();

        if ($revealed->roll->seed->hex() !== $this->seed || $revealed->roll->clientSeed !== $this->id) {
            throw LootDrawRefused::notCommitted($this->orderNumber, $this->line, $this->option, $this->unit);
        }

        $this->result = ['source' => DrawSource::Verifiable->value, 'faces' => $revealed->result->faces()];
        $this->proof = $revealed->proof();
        $this->drawn = $drawn;
        $this->revealedAt = $at;
    }

    /**
     * Records physical dice in place of the committed draw.
     *
     * @throws LootDrawRefused when the draw was revealed already
     */
    public function enter(LootResult $result, string $drawn, DateTimeImmutable $at): void
    {
        $this->checkOpen();
        $this->result = ['source' => DrawSource::Physical->value, 'faces' => $result->faces()];
        $this->drawn = $drawn;
        $this->revealedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function orderNumber(): string
    {
        return $this->orderNumber;
    }

    public function line(): string
    {
        return $this->line;
    }

    public function option(): string
    {
        return $this->option;
    }

    public function unit(): int
    {
        return $this->unit;
    }

    public function drawer(): string
    {
        return $this->drawer;
    }

    /**
     * The commitment shown before the draw, or `null` for physical dice.
     */
    public function commitment(): ?string
    {
        return $this->commitment;
    }

    /**
     * The pending draw to reveal, with `clientSeedOfReveal()`.
     *
     * @throws LootDrawRefused for physical dice, never committed
     */
    public function pending(): PendingDraw
    {
        return null === $this->seed
            ? throw LootDrawRefused::notCommitted($this->orderNumber, $this->line, $this->option, $this->unit) : PendingDraw::restore($this->seed);
    }

    /**
     * The seed of a verifiable draw once it is revealed, as 64 hexadecimal digits: its SHA-256 is the commitment shown
     * before the payment. `null` while it is secret, and for physical dice.
     */
    public function revealedSeed(): ?string
    {
        return null === $this->proof ? null : $this->seed;
    }

    /**
     * @return list<string>|null the list a text is drawn from
     */
    public function texts(): ?array
    {
        return $this->texts;
    }

    public function isRevealed(): bool
    {
        return $this->revealedAt instanceof DateTimeImmutable;
    }

    /**
     * The result of the draw in its box, once revealed or entered.
     */
    public function result(LootBox $box): ?LootResult
    {
        return null === $this->result ? null : LootResult::ofFaces($box, $this->result['faces'], DrawSource::from($this->result['source']));
    }

    /**
     * @return array<string, mixed>|null the published proof of a verifiable draw, once revealed
     */
    public function proof(): ?array
    {
        return $this->proof;
    }

    /**
     * What the order line keeps of the draw, once revealed or entered.
     */
    public function drawn(): ?string
    {
        return $this->drawn;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revealedAt(): ?DateTimeImmutable
    {
        return $this->revealedAt;
    }

    private function checkOpen(): void
    {
        if ($this->isRevealed()) {
            throw LootDrawRefused::alreadyRevealed($this->id);
        }
    }
}
