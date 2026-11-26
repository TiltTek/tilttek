<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Contract\PreparationObserver;
use Tilttek\SessionPrep\Contract\PreparationStore;
use Tilttek\SessionPrep\Event\ClueMarked;
use Tilttek\SessionPrep\Event\ItemMarked;
use Tilttek\SessionPrep\Event\PreparationCarriedOver;
use Tilttek\SessionPrep\Event\PreparationChanged;
use Tilttek\SessionPrep\Event\PreparationClosed;
use Tilttek\SessionPrep\Event\PreparationCreated;
use Tilttek\SessionPrep\Event\PreparationDeleted;
use Tilttek\SessionPrep\Event\PreparationEvent;
use Tilttek\SessionPrep\Event\PreparationReady;
use Tilttek\SessionPrep\Event\PreparationReopened;
use Tilttek\SessionPrep\Event\SceneMarked;
use Tilttek\SessionPrep\Event\SecretMarked;
use Tilttek\SessionPrep\Event\SessionLinked;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Exception\PreparationConflict;
use Tilttek\SessionPrep\Exception\PreparationNotFound;
use Tilttek\SessionPrep\Exception\PreparationRefused;
use Tilttek\SessionPrep\Internal\Identifier;

/**
 * The service of the preparations: it creates and changes them, links them to a session, records what the table
 * brought out — a secret revealed, a clue found, a scene played or skipped, an item checked —, marks them ready,
 * closes, reopens and deletes them, and carries what was left over into the next one. It reads from the store,
 * changes, keeps, then tells the observer what happened, each fact once. It keeps no state between calls.
 *
 * Only a preparation's game master reads its changes through this service and makes them; a closed preparation is
 * changed by nothing but `reopen()` and `delete()`.
 */
final readonly class SessionPreparer
{
    /** The most preparations one call reads. */
    public const int MAX_READ = 1_000;

    public function __construct(private PreparationStore $store, private ?PreparationObserver $observer = null)
    {
    }

    /**
     * @throws InvalidPreparation  when the identifier is not one
     * @throws PreparationNotFound when no preparation has it
     */
    public function preparation(string $id): Preparation
    {
        return $this->store->preparation(Identifier::check($id, 'a preparation')) ?? throw PreparationNotFound::withId($id);
    }

    /**
     * The preparations the query matches, by identifier.
     *
     * @param int $limit 1 to 1000
     *
     * @return iterable<Preparation>
     *
     * @throws InvalidPreparation when the limit is out of its bounds
     */
    public function preparations(PreparationQuery $query, int $limit = self::MAX_READ): iterable
    {
        if ($limit < 1 || $limit > self::MAX_READ) {
            throw InvalidPreparation::because(\sprintf('A read of preparations is of 1 to %d.', self::MAX_READ));
        }

        return $this->store->preparations($query, $limit);
    }

    /**
     * Creates a new preparation, at version 0 and in draft. Its parts start as they are when written: every secret
     * hidden, every clue not found, every scene planned, every item not checked.
     *
     * @throws PreparationRefused  when the preparation is not at version 0 or not a draft, or a preparation has its
     *                             identifier
     * @throws PreparationConflict when a preparation of its identifier was kept meanwhile
     */
    public function create(Preparation $preparation): Preparation
    {
        if (0 !== $preparation->version || PreparationStatus::Draft !== $preparation->status) {
            throw PreparationRefused::because('A new preparation is a draft at version 0.');
        }

        if ($this->store->preparation($preparation->id) instanceof Preparation) {
            throw PreparationRefused::because(\sprintf('A preparation has the identifier %s.', $preparation->id));
        }

        $kept = $this->store->save($preparation->withFactsOf(null));
        $this->tell(new PreparationCreated($kept->id));

        return $kept;
    }

    /**
     * Replaces the parts of a preparation with its change, made on the version read, by its game master; they may hand
     * it to another. The session, the status and the facts of the table stay as they were — a part added starts as it
     * does in `create()` —: each is changed by its own method, which tells it.
     *
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, it is closed, or the change moves it to
     *                             another campaign
     * @throws PreparationConflict when it changed since it was read
     */
    public function change(Preparation $preparation, string $by): Preparation
    {
        $before = $this->open($preparation->id, $by);

        if ($preparation->campaign !== $before->campaign) {
            throw PreparationRefused::because('A preparation stays in its campaign.');
        }

        $kept = $this->store->save($preparation->withFactsOf($before)->withSession($before->session)->withStatus($before->status));
        $this->tell(new PreparationChanged($kept->id));

        return $kept;
    }

    /**
     * Links the preparation to a session, by its identifier, or to none.
     *
     * @throws InvalidPreparation  when the session's identifier is not one
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, or it is closed
     * @throws PreparationConflict when it changed since it was read
     */
    public function link(string $id, ?string $session, string $by): Preparation
    {
        $before = $this->open($id, $by);

        if ($session === $before->session) {
            return $before;
        }

        $kept = $this->store->save($before->withSession($session));
        $this->tell(new SessionLinked($kept->id, $session));

        return $kept;
    }

    /**
     * Marks a scene played, skipped, or planned again.
     *
     * @throws InvalidPreparation  when no scene has the key
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, or it is closed
     * @throws PreparationConflict when it changed since it was read
     */
    public function markScene(string $id, string $scene, SceneStatus $status, string $by): Preparation
    {
        $before = $this->open($id, $by);
        $after = $before->withSceneStatus($scene, $status);

        return $status === $before->scene($scene)?->status ? $before : $this->keep($after, new SceneMarked($id, $scene, $status));
    }

    /**
     * Marks a secret revealed, or hidden again.
     *
     * @throws InvalidPreparation  when no secret has the key
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, or it is closed
     * @throws PreparationConflict when it changed since it was read
     */
    public function markSecret(string $id, string $secret, bool $revealed, string $by): Preparation
    {
        $before = $this->open($id, $by);
        $after = $before->withSecretRevealed($secret, $revealed);

        return $revealed === $before->secret($secret)?->revealed ? $before : $this->keep($after, new SecretMarked($id, $secret, $revealed));
    }

    /**
     * Marks a clue of a secret found, or lost again. The secret is not revealed by it: the game master says when.
     *
     * @throws InvalidPreparation  when no secret has the key, or no clue of it has the clue's key
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, or it is closed
     * @throws PreparationConflict when it changed since it was read
     */
    public function markClue(string $id, string $secret, string $clue, bool $found, string $by): Preparation
    {
        $before = $this->open($id, $by);
        $after = $before->withClueFound($secret, $clue, $found);

        return $found === $before->secret($secret)?->clue($clue)?->found ? $before : $this->keep($after, new ClueMarked($id, $secret, $clue, $found));
    }

    /**
     * Marks an item of the checklist checked, or not.
     *
     * @throws InvalidPreparation  when no item has the key
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, or it is closed
     * @throws PreparationConflict when it changed since it was read
     */
    public function markItem(string $id, string $item, bool $checked, string $by): Preparation
    {
        $before = $this->open($id, $by);
        $after = $before->withItemChecked($item, $checked);

        return $checked === $before->item($item)?->checked ? $before : $this->keep($after, new ItemMarked($id, $item, $checked));
    }

    /**
     * Marks a draft ready for the table, once every required item of its checklist is checked.
     *
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master, it is closed, or a required item is not
     *                             checked
     * @throws PreparationConflict when it changed since it was read
     */
    public function ready(string $id, string $by): Preparation
    {
        $before = $this->open($id, $by);

        if (PreparationStatus::Ready === $before->status) {
            return $before;
        }

        $missing = $before->missingItems();

        if ([] !== $missing) {
            throw PreparationRefused::because(\sprintf('The preparation is ready once its required items are checked: %s.', implode(', ', $missing)));
        }

        return $this->keep($before->withStatus(PreparationStatus::Ready), new PreparationReady($id));
    }

    /**
     * Brings a ready or closed preparation back to a draft.
     *
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master
     * @throws PreparationConflict when it changed since it was read
     */
    public function reopen(string $id, string $by): Preparation
    {
        $before = $this->owned($id, $by);

        if (PreparationStatus::Draft === $before->status) {
            return $before;
        }

        return $this->keep($before->withStatus(PreparationStatus::Draft), new PreparationReopened($id));
    }

    /**
     * Closes a preparation, the session over: nothing changes it until it is reopened.
     *
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master
     * @throws PreparationConflict when it changed since it was read
     */
    public function close(string $id, string $by): Preparation
    {
        $before = $this->owned($id, $by);

        if (PreparationStatus::Closed === $before->status) {
            return $before;
        }

        return $this->keep($before->withStatus(PreparationStatus::Closed), new PreparationClosed($id));
    }

    /**
     * Carries into a preparation what another one of the same campaign left over: its secrets not revealed, with their
     * clues as they are, and its scenes still planned, with the characters and places they feature. A part whose key
     * the preparation holds already is left out, so that carrying again adds only what is new.
     *
     * @return Preparation the preparation carried into, unchanged when nothing was left over
     *
     * @throws InvalidPreparation  when the preparation would hold too many parts
     * @throws PreparationNotFound when no preparation has an identifier
     * @throws PreparationRefused  when the member is not the game master of both, the preparation carried into is
     *                             closed or is the other one, or they are of two campaigns
     * @throws PreparationConflict when the preparation carried into changed since it was read
     */
    public function carryOver(string $from, string $into, string $by): Preparation
    {
        $source = $this->owned($from, $by);
        $before = $this->open($into, $by);

        if ($source->id === $before->id || $source->campaign !== $before->campaign) {
            throw PreparationRefused::because('A preparation is carried into another one of its campaign.');
        }

        $secrets = $before->secrets;
        $scenes = $before->scenes;
        $characters = $before->characters;
        $places = $before->places;

        foreach ($source->unrevealedSecrets() as $secret) {
            if (!$before->secret($secret->key) instanceof Secret) {
                $secrets[] = $secret;
            }
        }

        foreach ($source->scenes as $scene) {
            if (SceneStatus::Planned !== $scene->status || $before->scene($scene->key) instanceof Scene) {
                continue;
            }

            $scenes[] = $scene;
            $characters = $this->carried($characters, $source->characters, $scene->characters);
            $places = $this->carried($places, $source->places, $scene->places);
        }

        if ([$secrets, $scenes] === [$before->secrets, $before->scenes]) {
            return $before;
        }

        $after = new Preparation($before->id, $before->campaign, $before->gameMaster, $before->title, $before->session, $scenes, $secrets, $characters, $places, $before->notes, $before->checklist, $before->status, $before->version);

        return $this->keep($after, new PreparationCarriedOver($into, $from));
    }

    /**
     * Deletes a preparation, in any status.
     *
     * @throws PreparationNotFound when no preparation has the identifier
     * @throws PreparationRefused  when the member is not its game master
     * @throws PreparationConflict when it changed since it was read
     */
    public function delete(string $id, string $by): void
    {
        $this->store->delete($this->owned($id, $by));
        $this->tell(new PreparationDeleted($id));
    }

    /**
     * The parts held, with those of the other preparation whose keys are named and not held yet, in their order.
     *
     * @template T of Part
     *
     * @param list<T>      $held
     * @param list<T>      $source
     * @param list<string> $named
     *
     * @return list<T>
     */
    private function carried(array $held, array $source, array $named): array
    {
        $keys = array_flip(array_map(static fn (Part $part): string => $part->key, $held));

        foreach ($source as $part) {
            if (\in_array($part->key, $named, true) && !isset($keys[$part->key])) {
                $held[] = $part;
            }
        }

        return $held;
    }

    /**
     * The preparation, when the member is its game master.
     */
    private function owned(string $id, string $by): Preparation
    {
        $preparation = $this->preparation($id);

        if ($by !== $preparation->gameMaster) {
            throw PreparationRefused::because('Only the game master of a preparation changes it.');
        }

        return $preparation;
    }

    /**
     * The preparation, when the member is its game master and it is not closed.
     */
    private function open(string $id, string $by): Preparation
    {
        $preparation = $this->owned($id, $by);

        if (PreparationStatus::Closed === $preparation->status) {
            throw PreparationRefused::because('A closed preparation is reopened before it changes.');
        }

        return $preparation;
    }

    private function keep(Preparation $preparation, PreparationEvent $event): Preparation
    {
        $kept = $this->store->save($preparation);
        $this->tell($event);

        return $kept;
    }

    private function tell(PreparationEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
