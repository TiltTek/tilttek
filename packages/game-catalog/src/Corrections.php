<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Psr\Clock\ClockInterface;
use Tilttek\GameCatalog\Contract\CatalogObserver;
use Tilttek\GameCatalog\Contract\CatalogStore;
use Tilttek\GameCatalog\Exception\CatalogConflict;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\CorrectionConflict;
use Tilttek\GameCatalog\Exception\CorrectionNotFound;
use Tilttek\GameCatalog\Exception\EntryNotFound;

/**
 * The corrections the members propose and the administration decides. A correction is kept beside the entry until it
 * is accepted; once accepted, its fields are kept beside the values of the source, which stay as the source sent them,
 * so that the source sending the entry again does not undo it, and the administration can return a field to the value
 * of the source.
 *
 * Accepting a correction saves the correction, then the entry: an application that keeps both in a database runs the
 * call in a transaction.
 */
final readonly class Corrections
{
    public const int MAX_PENDING = 20;

    public function __construct(
        private CatalogStore $store,
        private ClockInterface $clock,
        private ?CatalogObserver $observer = null,
    ) {
    }

    /**
     * Proposes a correction of the entry: the details as the member would have them, read from the details shown.
     *
     * @throws EntryNotFound   when no entry has the identifier
     * @throws CatalogRefused  when the proposed details change nothing, or the member has 20 corrections pending
     * @throws CatalogConflict when a correction has the identifier
     */
    public function propose(string $id, string $entry, string $member, Details $proposed): Correction
    {
        $current = $this->store->entry($entry);

        if (\count($this->store->corrections(member: $member, status: CorrectionStatus::Pending)) >= self::MAX_PENDING) {
            throw CatalogRefused::because(\sprintf('The member %s has %d corrections waiting for a decision.', $member, self::MAX_PENDING));
        }

        $correction = $this->store->saveCorrection(new Correction($id, $entry, $member, $current->details(), $proposed, $this->clock->now()));
        $this->observer?->correctionProposed($entry, $id);

        return $correction;
    }

    /**
     * Accepts the correction: its fields take the values the member proposed.
     *
     * @param bool $force accept even when a field it changes was changed on the entry since it was proposed
     *
     * @throws CorrectionNotFound when no correction has the identifier
     * @throws CatalogRefused     when the correction is already decided
     * @throws CorrectionConflict when a field it changes was changed on the entry since, and `$force` is false
     */
    public function accept(string $id, string $by, bool $force = false): Entry
    {
        $correction = $this->pending($id);
        $entry = $this->store->entry($correction->entry);
        $current = $entry->details();
        $changed = array_values(array_filter(
            $correction->fields,
            static fn (Field $field): bool => $current->value($field) !== $correction->base->value($field)
                && $current->value($field) !== $correction->proposed->value($field),
        ));

        if ([] !== $changed && !$force) {
            throw CorrectionConflict::on($id, $changed);
        }

        $this->store->saveCorrection($correction->decided(CorrectionStatus::Accepted, $by, $this->clock->now()));
        $entry = $this->store->save($entry->withCorrection($correction->proposed, $correction->fields));
        $this->store->record(new Change($entry->id, ChangeKind::Corrected, $this->clock->now(), $correction->fields, $by, $id));
        $this->observer?->correctionDecided($id, $correction->member, CorrectionStatus::Accepted);

        return $entry;
    }

    /**
     * Refuses the correction, with a note for the member.
     *
     * @throws CorrectionNotFound when no correction has the identifier
     * @throws CatalogRefused     when the correction is already decided
     */
    public function refuse(string $id, string $by, ?string $note = null): Correction
    {
        $correction = $this->store->saveCorrection($this->pending($id)->decided(CorrectionStatus::Refused, $by, $this->clock->now(), $note));
        $this->observer?->correctionDecided($id, $correction->member, CorrectionStatus::Refused);

        return $correction;
    }

    /**
     * Returns a corrected field of the entry to the value of its source.
     *
     * @throws EntryNotFound  when no entry has the identifier
     * @throws CatalogRefused when the field is not corrected
     */
    public function revert(string $entry, Field $field, string $by): Entry
    {
        $current = $this->store->entry($entry);

        if (!\in_array($field, $current->corrected, true)) {
            throw CatalogRefused::because(\sprintf('The field %s of the entry %s is not corrected.', $field->value, $entry));
        }

        $current = $this->store->save($current->withoutCorrection($field));
        $this->store->record(new Change($entry, ChangeKind::Reverted, $this->clock->now(), [$field], $by));

        return $current;
    }

    /**
     * The corrections waiting for a decision, of an entry or of all, in the order they were proposed.
     *
     * @return list<Correction>
     */
    public function waiting(?string $entry = null): array
    {
        return $this->store->corrections($entry, status: CorrectionStatus::Pending);
    }

    private function pending(string $id): Correction
    {
        $correction = $this->store->correction($id);

        if (!$correction->isPending()) {
            throw CatalogRefused::because(\sprintf('The correction %s is already %s.', $id, $correction->status->value));
        }

        return $correction;
    }
}
