<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Handouts\Contract\HandoutObserver;
use Tilttek\Handouts\Contract\HandoutStore;
use Tilttek\Handouts\Exception\HandoutRefused;
use Tilttek\Handouts\Exception\InvalidHandout;
use Tilttek\Handouts\Exception\UnknownHandout;

/**
 * The handouts of the application's tables: prepared by the game master, revealed to everyone or to some players —
 * now, at a moment, or on a cue given during play —, concealed again, and what each player has seen of them.
 *
 * ```php
 * $handouts->create(ResourceReference::of('handout', '4'), $table, 'The miller\'s letter', 'Meet me at the mill.');
 * $handouts->reveal($handout, Audience::players($ada));
 * $handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'));
 * $handouts->cue($table, 'crypt.opened');                 // reveals what was staged on it
 * $handouts->markSeen($handout, $ada);
 * $handouts->unseen($table, $bo);                          // what Bo has not opened yet
 * ```
 *
 * The library decides no permission: the application checks who may act before it calls — the game master prepares
 * and reveals, a player marks what they opened — and gives the acting reference as `$by`, which the events carry.
 * Files are references to the application's storage, which serves them after it asks `canSee()`.
 */
final readonly class Handouts
{
    /** @var list<HandoutObserver> */
    private array $observers;

    /**
     * @param iterable<HandoutObserver> $observers
     */
    public function __construct(
        private HandoutStore $store,
        private ClockInterface $clock,
        private Limits $limits = new Limits(),
        private MediaTypes $mediaTypes = new MediaTypes(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Prepares a handout for a table, under the reference the application gives, concealed from every player until it
     * is revealed.
     *
     * @param list<Attachment> $attachments
     *
     * @throws InvalidHandout
     * @throws HandoutRefused
     *
     * @phpstan-impure
     */
    public function create(ResourceReference $handout, ResourceReference $table, string $title, ?string $text = null, array $attachments = [], ?ResourceReference $by = null): Handout
    {
        $created = new Handout($handout, $table, $title, $text, $attachments, $this->clock->now());
        $this->checkAttachments($created);

        if ($this->store->handout($handout->toString()) instanceof Handout) {
            throw HandoutRefused::because(Refusal::AlreadyCreated);
        }

        if ($this->store->countHandouts($table->toString()) >= $this->limits->handoutsPerTable) {
            throw HandoutRefused::because(Refusal::TooManyHandouts);
        }

        $this->store->saveHandout($created);
        $this->tell($created, Change::Created, null, $by);

        return $created;
    }

    /**
     * @throws UnknownHandout
     */
    public function handout(ResourceReference $handout): Handout
    {
        return $this->stored($handout->toString());
    }

    /**
     * Replaces the title, text and attachments, at the next revision: the players it is revealed to see the new one,
     * and those who saw the earlier one find it outdated.
     *
     * @param list<Attachment> $attachments
     *
     * @throws InvalidHandout
     * @throws HandoutRefused
     * @throws UnknownHandout
     *
     * @phpstan-impure
     */
    public function edit(ResourceReference $handout, string $title, ?string $text = null, array $attachments = [], ?ResourceReference $by = null): Handout
    {
        $edited = $this->handout($handout)->edited($title, $text, $attachments, $this->clock->now());
        $this->checkAttachments($edited);
        $this->store->saveHandout($edited);
        $this->tell($edited, Change::Edited, null, $by);

        return $edited;
    }

    /**
     * Removes the handout with its disclosures, staged reveals and sightings. The files stay in the application's
     * storage.
     *
     * @throws UnknownHandout
     */
    public function remove(ResourceReference $handout, ?ResourceReference $by = null): void
    {
        $removed = $this->handout($handout);
        $this->store->removeHandout($handout->toString());
        $this->tell($removed, Change::Removed, null, $by);
    }

    /**
     * The handouts of a table, revealed or not, the earliest created first: the game master's view.
     *
     * @return list<Handout>
     */
    public function handouts(ResourceReference $table): array
    {
        return $this->store->handouts($table->toString());
    }

    /**
     * Reveals the handout to the audience now, at its current revision. Revealing again to the same audience shows it
     * again, at the new moment and revision; a reveal staged for the same audience is done with.
     *
     * @return list<Disclosure> one per player, or one for everyone
     *
     * @throws UnknownHandout
     * @throws HandoutRefused
     *
     * @phpstan-impure
     */
    public function reveal(ResourceReference $handout, Audience $audience, ?ResourceReference $by = null): array
    {
        $revealed = $this->handout($handout);
        $this->checkAudience($audience);
        $this->checkRoom($revealed, $audience, $this->store->disclosures(...), Refusal::TooManyRevealed);

        return $this->disclose($revealed, $audience->members(), $by);
    }

    /**
     * Conceals the handout again: from everyone — every disclosure, to everyone and to players by name, is removed —,
     * or from the players named — a handout also revealed to everyone stays visible to them. Staged reveals stay.
     *
     * @throws UnknownHandout
     */
    public function conceal(ResourceReference $handout, Audience $audience, ?ResourceReference $by = null): void
    {
        $concealed = $this->handout($handout);
        $keys = $audience->isEveryone()
            ? array_map(static fn (Disclosure $disclosure): string => $disclosure->key(), $this->store->disclosures($handout->toString()))
            : $audience->keys();

        foreach ($keys as $key) {
            $disclosure = $this->store->disclosure($handout->toString(), $key);

            if ($disclosure instanceof Disclosure) {
                $this->store->removeDisclosure($handout->toString(), $key);
                $this->tell($concealed, Change::Concealed, $disclosure->player, $by);
            }
        }
    }

    /**
     * Whether the player sees the handout: it is revealed to everyone, or to them by name.
     *
     * @throws UnknownHandout
     */
    public function canSee(ResourceReference $handout, ResourceReference $player): bool
    {
        $this->handout($handout);

        return $this->store->disclosure($handout->toString(), Audience::EVERYONE) instanceof Disclosure
            || $this->store->disclosure($handout->toString(), $player->toString()) instanceof Disclosure;
    }

    /**
     * To whom the handout is revealed, the earliest first.
     *
     * @return list<Disclosure>
     *
     * @throws UnknownHandout
     */
    public function audience(ResourceReference $handout): array
    {
        $this->handout($handout);

        return $this->store->disclosures($handout->toString());
    }

    /**
     * Prepares a reveal to the audience, on a trigger: at a moment, which `releaseDue()` reveals once it has come, or
     * on a cue, which `cue()` reveals. Staging again for the same audience replaces the earlier trigger.
     *
     * @return list<StagedReveal> one per player, or one for everyone
     *
     * @throws UnknownHandout
     * @throws HandoutRefused
     *
     * @phpstan-impure
     */
    public function stage(ResourceReference $handout, Audience $audience, Trigger $trigger, ?ResourceReference $by = null): array
    {
        $staged = $this->handout($handout);
        $this->checkAudience($audience);
        $this->checkRoom($staged, $audience, $this->store->stagedOf(...), Refusal::TooManyStaged);

        $reveals = [];
        $now = $this->clock->now();

        foreach ($audience->members() as $player) {
            $reveal = new StagedReveal($staged->reference, $staged->table, $player, $trigger, $now, $by);
            $this->store->saveStaged($reveal);
            $this->tell($staged, Change::Staged, $player, $by);
            $reveals[] = $reveal;
        }

        return $reveals;
    }

    /**
     * Withdraws the reveals staged for the audience: for everyone — every staged reveal of the handout — or for the
     * players named.
     *
     * @throws UnknownHandout
     */
    public function unstage(ResourceReference $handout, Audience $audience, ?ResourceReference $by = null): void
    {
        $unstaged = $this->handout($handout);
        $keys = $audience->isEveryone()
            ? array_map(static fn (StagedReveal $staged): string => $staged->key(), $this->store->stagedOf($handout->toString()))
            : $audience->keys();

        foreach ($keys as $key) {
            $staged = $this->store->staged($handout->toString(), $key);

            if ($staged instanceof StagedReveal) {
                $this->store->removeStaged($handout->toString(), $key);
                $this->tell($unstaged, Change::Unstaged, $staged->player, $by);
            }
        }
    }

    /**
     * The reveals staged for the handout, the earliest staged first.
     *
     * @return list<StagedReveal>
     *
     * @throws UnknownHandout
     */
    public function staged(ResourceReference $handout): array
    {
        $this->handout($handout);

        return $this->store->stagedOf($handout->toString());
    }

    /**
     * Reveals the staged reveals of every table whose moment has come, the earliest first, at most the limit's release
     * batch: the application's scheduler calls it again while it reveals as many.
     *
     * @return list<Disclosure>
     *
     * @phpstan-impure
     */
    public function releaseDue(?ResourceReference $by = null): array
    {
        return $this->release($this->store->due($this->clock->now(), $this->limits->releaseBatch), $by);
    }

    /**
     * Reveals what was staged on the cue for the table's handouts, the earliest staged first, at most the limit's
     * release batch: what remains stays staged for the next call.
     *
     * @return list<Disclosure>
     *
     * @throws InvalidHandout
     *
     * @phpstan-impure
     */
    public function cue(ResourceReference $table, string $cue, ?ResourceReference $by = null): array
    {
        return $this->release($this->store->onCue($table->toString(), Text::key($cue, 'a cue'), $this->limits->releaseBatch), $by);
    }

    /**
     * Records that the player opened the handout, at its current revision. Told once per revision: opening it again
     * moves only the last moment.
     *
     * @throws UnknownHandout
     * @throws HandoutRefused when the handout is not revealed to the player
     *
     * @phpstan-impure
     */
    public function markSeen(ResourceReference $handout, ResourceReference $player, ?ResourceReference $by = null): Sighting
    {
        $seen = $this->handout($handout);

        if (!$this->canSee($handout, $player)) {
            throw HandoutRefused::because(Refusal::NotRevealed);
        }

        $now = $this->clock->now();
        $earlier = $this->store->sighting($handout->toString(), $player->toString());
        $sighting = $earlier?->seenAgain($seen->revision, $now) ?? new Sighting($handout, $player, $seen->revision, $now, $now);
        $this->store->saveSighting($sighting);

        if ($earlier?->revision !== $seen->revision) {
            $this->tell($seen, Change::Seen, $player, $by);
        }

        return $sighting;
    }

    /**
     * What each player has seen of the handout, the earliest first seen first: the game master's view.
     *
     * @return list<Sighting>
     *
     * @throws UnknownHandout
     */
    public function sightings(ResourceReference $handout): array
    {
        $this->handout($handout);

        return $this->store->sightings($handout->toString());
    }

    /**
     * The table's handouts the player sees, the earliest revealed first, with what they have seen of each: the
     * player's view.
     *
     * @return list<Visible>
     */
    public function visibleTo(ResourceReference $table, ResourceReference $player): array
    {
        $first = [];

        foreach ($this->store->disclosuresFor($table->toString(), $player->toString()) as $disclosure) {
            $first[$disclosure->handout->toString()] ??= $disclosure->revealedAt;
        }

        $visible = [];

        foreach ($this->store->handouts($table->toString()) as $handout) {
            $reference = $handout->reference->toString();

            if (isset($first[$reference])) {
                $sighting = $this->store->sighting($reference, $player->toString());
                $visible[] = new Visible($handout, $first[$reference], $this->freshness($handout, $sighting), $sighting);
            }
        }

        usort($visible, static function (Visible $a, Visible $b): int {
            $byTime = $a->revealedAt <=> $b->revealedAt;

            return 0 !== $byTime ? $byTime : strcmp($a->handout->reference->toString(), $b->handout->reference->toString());
        });

        return $visible;
    }

    /**
     * The table's handouts the player sees and has not opened at their current revision, the earliest revealed first.
     *
     * @return list<Visible>
     */
    public function unseen(ResourceReference $table, ResourceReference $player): array
    {
        return array_values(array_filter(
            $this->visibleTo($table, $player),
            static fn (Visible $visible): bool => Freshness::Seen !== $visible->freshness,
        ));
    }

    /**
     * Erases a player from every table: their disclosures by name, staged reveals and sightings. Handouts revealed to
     * everyone stay so.
     */
    public function forget(ResourceReference $player, ?ResourceReference $by = null): void
    {
        foreach ($this->store->forgetPlayer($player->toString()) as $reference) {
            $this->tell($this->stored($reference), Change::Forgotten, $player, $by);
        }
    }

    /**
     * @param list<StagedReveal> $due
     *
     * @return list<Disclosure>
     */
    private function release(array $due, ?ResourceReference $by): array
    {
        $disclosures = [];

        foreach ($due as $staged) {
            array_push($disclosures, ...$this->disclose($this->stored($staged->handout->toString()), [$staged->player], $by ?? $staged->by));
        }

        return $disclosures;
    }

    /**
     * @param non-empty-list<?ResourceReference> $players
     *
     * @return list<Disclosure>
     */
    private function disclose(Handout $handout, array $players, ?ResourceReference $by): array
    {
        $disclosures = [];
        $now = $this->clock->now();

        foreach ($players as $player) {
            $disclosure = new Disclosure($handout->reference, $handout->table, $player, $handout->revision, $now, $by);
            $this->store->saveDisclosure($disclosure);
            $this->store->removeStaged($handout->reference->toString(), $disclosure->key());
            $this->tell($handout, Change::Revealed, $player, $by);
            $disclosures[] = $disclosure;
        }

        return $disclosures;
    }

    /**
     * @throws UnknownHandout
     */
    private function stored(string $handout): Handout
    {
        return $this->store->handout($handout) ?? throw UnknownHandout::named($handout);
    }

    private function checkAttachments(Handout $handout): void
    {
        if (\count($handout->attachments) > $this->limits->attachmentsPerHandout) {
            throw HandoutRefused::because(Refusal::TooManyAttachments);
        }

        foreach ($handout->attachments as $attachment) {
            if (!$this->mediaTypes->allows($attachment->mediaType)) {
                throw HandoutRefused::because(Refusal::MediaTypeNotAllowed);
            }
        }
    }

    private function checkAudience(Audience $audience): void
    {
        if (\count($audience->players) > $this->limits->audiencePerHandout) {
            throw HandoutRefused::because(Refusal::AudienceTooLarge);
        }
    }

    /**
     * Refuses an audience whose new keys would hold the handout beyond its limit of audiences.
     *
     * @param callable(string): list<Disclosure|StagedReveal> $held
     */
    private function checkRoom(Handout $handout, Audience $audience, callable $held, Refusal $refusal): void
    {
        $keys = [];

        foreach ($held($handout->reference->toString()) as $entry) {
            $keys[$entry->key()] = $entry->key();
        }

        foreach ($audience->keys() as $key) {
            $keys[$key] = $key;
        }

        if (\count($keys) > $this->limits->audiencePerHandout) {
            throw HandoutRefused::because($refusal);
        }
    }

    private function freshness(Handout $handout, ?Sighting $sighting): Freshness
    {
        if (!$sighting instanceof Sighting) {
            return Freshness::Unseen;
        }

        return $sighting->revision < $handout->revision ? Freshness::Outdated : Freshness::Seen;
    }

    private function tell(Handout $handout, Change $change, ?ResourceReference $player, ?ResourceReference $by): void
    {
        $event = new HandoutChanged($handout->reference->toString(), $handout->table->toString(), $change, $player?->toString(), $by?->toString(), $this->clock->now());

        foreach ($this->observers as $observer) {
            $observer->changed($event);
        }
    }
}
