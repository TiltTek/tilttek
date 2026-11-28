<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use DateTimeImmutable;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Identifier;
use Tilttek\GameCatalog\Internal\Text;

/**
 * A change of an entry a member proposes, on any field of its details, kept beside the entry until the administration
 * accepts or refuses it. It keeps the details the member read, so that a field changed on the entry since is seen when
 * the correction is accepted.
 */
final readonly class Correction
{
    public const int MAX_NOTE = 1_000;

    /** @var non-empty-list<Field> the fields the member changed, in the order of `Field` */
    public array $fields;

    public ?string $note;

    /**
     * @param Details $base     the details of the entry the member read
     * @param Details $proposed the details as the member would have them
     * @param ?string $note     why the administration refused it
     *
     * @throws InvalidEntry   when an identifier or the note is out of its shape
     * @throws CatalogRefused when the proposed details change nothing
     */
    public function __construct(
        public string $id,
        public string $entry,
        public string $member,
        public Details $base,
        public Details $proposed,
        public DateTimeImmutable $proposedAt,
        public CorrectionStatus $status = CorrectionStatus::Pending,
        public ?string $decidedBy = null,
        public ?DateTimeImmutable $decidedAt = null,
        ?string $note = null,
        public int $version = 0,
    ) {
        Identifier::check($id, 'a correction');
        Identifier::check($entry, 'an entry');
        Identifier::check($member, 'a member');

        if (null !== $decidedBy) {
            Identifier::check($decidedBy, 'a member');
        }

        $fields = $base->differences($proposed);

        if ([] === $fields) {
            throw CatalogRefused::because('The correction changes nothing of the entry.');
        }

        $this->fields = $fields;
        $this->note = null === $note ? null : Text::lines($note, self::MAX_NOTE, 'note of a correction');
    }

    public function isPending(): bool
    {
        return CorrectionStatus::Pending === $this->status;
    }

    public function decided(CorrectionStatus $status, string $by, DateTimeImmutable $at, ?string $note = null): self
    {
        return new self($this->id, $this->entry, $this->member, $this->base, $this->proposed, $this->proposedAt, $status, $by, $at, $note, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->entry, $this->member, $this->base, $this->proposed, $this->proposedAt, $this->status, $this->decidedBy, $this->decidedAt, $this->note, $version);
    }
}
