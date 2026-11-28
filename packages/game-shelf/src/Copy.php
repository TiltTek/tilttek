<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Internal\Identifier;
use Tilttek\GameShelf\Internal\Text;

/**
 * A box on a member's shelf: a game of the catalogue, its statuses, the edition the member names, where it is kept,
 * and whether it may leave the house for a game night. A member may hold several copies of one game — two editions,
 * one kept and one for trade —, each by its own identifier. A copy an import placed is marked imported: a synchronized
 * import may move it to previously owned, never a copy the member entered by hand.
 */
final readonly class Copy
{
    public const int MAX_EDITION = 100;

    public const int MAX_LOCATION = 100;

    /** @var non-empty-list<CopyStatus> in the order of the enumeration */
    public array $statuses;

    public ?string $edition;

    public ?string $location;

    /**
     * @param list<CopyStatus> $statuses at least one, each once; owned and previously owned do not go together, and a
     *                                   copy for trade is owned
     * @param ?string          $location private to the member
     * @param bool             $imported whether an import placed it, rather than the member by hand
     *
     * @throws InvalidShelf when a value is out of its shape or its bounds, or the statuses do not go together
     */
    public function __construct(
        public string $id,
        public string $game,
        array $statuses = [CopyStatus::Owned],
        ?string $edition = null,
        ?string $location = null,
        public bool $bringable = true,
        public bool $imported = false,
    ) {
        Identifier::game($id, 'a copy');
        Identifier::game($game);
        $this->statuses = $this->statuses($statuses);
        $this->edition = null === $edition ? null : Text::line($edition, self::MAX_EDITION, 'edition of a copy');
        $this->location = null === $location ? null : Text::line($location, self::MAX_LOCATION, 'location of a copy');
    }

    public function has(CopyStatus $status): bool
    {
        return \in_array($status, $this->statuses, true);
    }

    /**
     * Whether the member can bring it to a game night: they own it, and it may leave the house.
     */
    public function canBeBrought(): bool
    {
        return $this->bringable && $this->has(CopyStatus::Owned);
    }

    /**
     * @param list<CopyStatus> $statuses
     *
     * @throws InvalidShelf when the statuses are not valid
     */
    public function withStatuses(array $statuses): self
    {
        return new self($this->id, $this->game, $statuses, $this->edition, $this->location, $this->bringable, $this->imported);
    }

    /**
     * The copy marked as placed by an import, or as entered by hand.
     */
    public function withImported(bool $imported): self
    {
        return new self($this->id, $this->game, $this->statuses, $this->edition, $this->location, $this->bringable, $imported);
    }

    /**
     * The copy as a viewer other than its member sees it: without its location.
     */
    public function withoutPrivateDetails(): self
    {
        return new self($this->id, $this->game, $this->statuses, $this->edition, null, $this->bringable, $this->imported);
    }

    /**
     * @param list<CopyStatus> $statuses
     *
     * @return non-empty-list<CopyStatus>
     */
    private function statuses(array $statuses): array
    {
        $ordered = array_values(array_filter(
            CopyStatus::cases(),
            static fn (CopyStatus $status): bool => \in_array($status, $statuses, true),
        ));

        if ([] === $ordered || \count($ordered) !== \count($statuses)) {
            throw InvalidShelf::because('A copy holds at least one status, each once.');
        }

        if (\in_array(CopyStatus::Owned, $ordered, true) && \in_array(CopyStatus::PreviouslyOwned, $ordered, true)) {
            throw InvalidShelf::because('A copy is owned or previously owned, not both.');
        }

        if (\in_array(CopyStatus::ForTrade, $ordered, true) && !\in_array(CopyStatus::Owned, $ordered, true)) {
            throw InvalidShelf::because('A copy for trade is owned.');
        }

        return $ordered;
    }
}
