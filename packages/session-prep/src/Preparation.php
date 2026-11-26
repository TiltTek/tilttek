<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Parts;
use Tilttek\SessionPrep\Internal\Text;

/**
 * The game master's preparation of one session of a campaign: a title, the session it is for, by its identifier only,
 * the scenes that might happen, the secrets and their clues, the non-player characters, the places, free notes and a
 * checklist. Each list keeps the order the game master gives it.
 *
 * A preparation is a value: each `with…()` returns a new one, checked as a whole. The facts the table brings — a secret
 * revealed, a clue found, a scene played or skipped, an item checked — are set by `SessionPreparer`, which tells them
 * to its observer.
 */
final readonly class Preparation
{
    public const int MAX_TITLE = 200;

    public const int MAX_SCENES = 50;

    public const int MAX_SECRETS = 50;

    public const int MAX_CHARACTERS = 100;

    public const int MAX_PLACES = 50;

    public const int MAX_NOTES = 100;

    public const int MAX_CHECKLIST = 50;

    public string $title;

    /** @var list<Scene> */
    public array $scenes;

    /** @var list<Secret> */
    public array $secrets;

    /** @var list<NonPlayerCharacter> */
    public array $characters;

    /** @var list<Place> */
    public array $places;

    /** @var list<Note> */
    public array $notes;

    /** @var list<ChecklistItem> */
    public array $checklist;

    /**
     * @param string                   $id         the preparation's identifier
     * @param string                   $campaign   the campaign's identifier
     * @param string                   $gameMaster the member who prepares and changes it
     * @param string                   $title      one line, 1 to 200 characters
     * @param ?string                  $session    the identifier of the session it is for, opaque to the library, or null
     * @param list<Scene>              $scenes     at most 50; the characters and places they feature are in this preparation
     * @param list<Secret>             $secrets    at most 50
     * @param list<NonPlayerCharacter> $characters at most 100
     * @param list<Place>              $places     at most 50
     * @param list<Note>               $notes      at most 100
     * @param list<ChecklistItem>      $checklist  at most 50
     * @param int                      $version    0 for a new preparation; the store's version otherwise
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds, a key comes twice in a list, or a scene
     *                            features a character or a place the preparation does not hold
     */
    public function __construct(
        public string $id,
        public string $campaign,
        public string $gameMaster,
        string $title,
        public ?string $session = null,
        array $scenes = [],
        array $secrets = [],
        array $characters = [],
        array $places = [],
        array $notes = [],
        array $checklist = [],
        public PreparationStatus $status = PreparationStatus::Draft,
        public int $version = 0,
    ) {
        Identifier::check($id, 'a preparation');
        Identifier::check($campaign, 'a campaign');
        Identifier::check($gameMaster, 'a member');

        if (null !== $session) {
            Identifier::check($session, 'a session');
        }

        if ($version < 0) {
            throw InvalidPreparation::because('The version of a preparation is 0 or more.');
        }

        $this->title = Text::line($title, self::MAX_TITLE, 'title of a preparation');
        $this->scenes = Parts::distinct($scenes, self::MAX_SCENES, 'scenes');
        $this->secrets = Parts::distinct($secrets, self::MAX_SECRETS, 'secrets');
        $this->characters = Parts::distinct($characters, self::MAX_CHARACTERS, 'characters');
        $this->places = Parts::distinct($places, self::MAX_PLACES, 'places');
        $this->notes = Parts::distinct($notes, self::MAX_NOTES, 'notes');
        $this->checklist = Parts::distinct($checklist, self::MAX_CHECKLIST, 'items of the checklist');

        $characterKeys = array_flip(array_map(static fn (NonPlayerCharacter $character): string => $character->key, $this->characters));
        $placeKeys = array_flip(array_map(static fn (Place $place): string => $place->key, $this->places));

        foreach ($this->scenes as $scene) {
            foreach ($scene->characters as $character) {
                if (!isset($characterKeys[$character])) {
                    throw InvalidPreparation::because(\sprintf('The scene %s features the character %s, which the preparation does not hold.', $scene->key, $character));
                }
            }

            foreach ($scene->places as $place) {
                if (!isset($placeKeys[$place])) {
                    throw InvalidPreparation::because(\sprintf('The scene %s happens in the place %s, which the preparation does not hold.', $scene->key, $place));
                }
            }
        }
    }

    public function scene(string $key): ?Scene
    {
        return Parts::find($this->scenes, $key);
    }

    public function secret(string $key): ?Secret
    {
        return Parts::find($this->secrets, $key);
    }

    public function character(string $key): ?NonPlayerCharacter
    {
        return Parts::find($this->characters, $key);
    }

    public function place(string $key): ?Place
    {
        return Parts::find($this->places, $key);
    }

    public function note(string $key): ?Note
    {
        return Parts::find($this->notes, $key);
    }

    public function item(string $key): ?ChecklistItem
    {
        return Parts::find($this->checklist, $key);
    }

    /**
     * The secrets not revealed yet, in their order.
     *
     * @return list<Secret>
     */
    public function unrevealedSecrets(): array
    {
        return array_values(array_filter($this->secrets, static fn (Secret $secret): bool => !$secret->revealed));
    }

    /**
     * The keys of the required items not checked yet, in their order: while there is one, the preparation is not ready.
     *
     * @return list<string>
     */
    public function missingItems(): array
    {
        return array_values(array_map(
            static fn (ChecklistItem $item): string => $item->key,
            array_filter($this->checklist, static fn (ChecklistItem $item): bool => $item->required && !$item->checked),
        ));
    }

    /**
     * @throws InvalidPreparation when the title is out of its shape or its bounds
     */
    public function withTitle(string $title): self
    {
        return $this->copy(['title' => $title]);
    }

    /**
     * @throws InvalidPreparation when the identifier is not one
     */
    public function withGameMaster(string $gameMaster): self
    {
        return $this->copy(['gameMaster' => $gameMaster]);
    }

    /**
     * @param ?string $session the identifier of the session, or null for none
     *
     * @throws InvalidPreparation when the identifier is not one
     */
    public function withSession(?string $session): self
    {
        return $this->copy(['session' => $session]);
    }

    public function withStatus(PreparationStatus $status): self
    {
        return $this->copy(['status' => $status]);
    }

    /**
     * @throws InvalidPreparation when the version is below 0
     */
    public function withVersion(int $version): self
    {
        return $this->copy(['version' => $version]);
    }

    /**
     * The scene in place of the scene of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many, or it features a character or a place not held
     */
    public function withScene(Scene $scene): self
    {
        return $this->copy(['scenes' => Parts::put($this->scenes, $scene)]);
    }

    /**
     * @throws InvalidPreparation when no scene has the key
     */
    public function withoutScene(string $key): self
    {
        return $this->copy(['scenes' => Parts::remove($this->scenes, $key, 'scene')]);
    }

    /**
     * The scene of the key moved just before another, or after every other one when `$before` is null.
     *
     * @throws InvalidPreparation when no scene has a key, or the scene is to move before itself
     */
    public function moveScene(string $key, ?string $before = null): self
    {
        return $this->copy(['scenes' => Parts::move($this->scenes, $key, $before, 'scene')]);
    }

    /**
     * @throws InvalidPreparation when no scene has the key
     */
    public function withSceneStatus(string $key, SceneStatus $status): self
    {
        return $this->withScene(Parts::get($this->scenes, $key, 'scene')->withStatus($status));
    }

    /**
     * The secret in place of the secret of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many
     */
    public function withSecret(Secret $secret): self
    {
        return $this->copy(['secrets' => Parts::put($this->secrets, $secret)]);
    }

    /**
     * @throws InvalidPreparation when no secret has the key
     */
    public function withoutSecret(string $key): self
    {
        return $this->copy(['secrets' => Parts::remove($this->secrets, $key, 'secret')]);
    }

    /**
     * @throws InvalidPreparation when no secret has the key
     */
    public function withSecretRevealed(string $key, bool $revealed): self
    {
        return $this->withSecret(Parts::get($this->secrets, $key, 'secret')->withRevealed($revealed));
    }

    /**
     * @throws InvalidPreparation when no secret has the key, or no clue of it has the clue's key
     */
    public function withClueFound(string $secret, string $clue, bool $found): self
    {
        return $this->withSecret(Parts::get($this->secrets, $secret, 'secret')->withClueFound($clue, $found));
    }

    /**
     * The character in place of the character of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many
     */
    public function withCharacter(NonPlayerCharacter $character): self
    {
        return $this->copy(['characters' => Parts::put($this->characters, $character)]);
    }

    /**
     * The preparation without the character, nor any scene featuring them.
     *
     * @throws InvalidPreparation when no character has the key
     */
    public function withoutCharacter(string $key): self
    {
        return $this->copy([
            'characters' => Parts::remove($this->characters, $key, 'character'),
            'scenes' => array_map(static fn (Scene $scene): Scene => $scene->withoutCharacter($key), $this->scenes),
        ]);
    }

    /**
     * The place in place of the place of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many
     */
    public function withPlace(Place $place): self
    {
        return $this->copy(['places' => Parts::put($this->places, $place)]);
    }

    /**
     * The preparation without the place, nor any scene happening in it.
     *
     * @throws InvalidPreparation when no place has the key
     */
    public function withoutPlace(string $key): self
    {
        return $this->copy([
            'places' => Parts::remove($this->places, $key, 'place'),
            'scenes' => array_map(static fn (Scene $scene): Scene => $scene->withoutPlace($key), $this->scenes),
        ]);
    }

    /**
     * The note in place of the note of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many
     */
    public function withNote(Note $note): self
    {
        return $this->copy(['notes' => Parts::put($this->notes, $note)]);
    }

    /**
     * @throws InvalidPreparation when no note has the key
     */
    public function withoutNote(string $key): self
    {
        return $this->copy(['notes' => Parts::remove($this->notes, $key, 'note')]);
    }

    /**
     * The item in place of the item of its key, or after the others.
     *
     * @throws InvalidPreparation when there would be too many
     */
    public function withItem(ChecklistItem $item): self
    {
        return $this->copy(['checklist' => Parts::put($this->checklist, $item)]);
    }

    /**
     * @throws InvalidPreparation when no item has the key
     */
    public function withoutItem(string $key): self
    {
        return $this->copy(['checklist' => Parts::remove($this->checklist, $key, 'item of the checklist')]);
    }

    /**
     * The item of the key moved just before another, or after every other one when `$before` is null.
     *
     * @throws InvalidPreparation when no item has a key, or the item is to move before itself
     */
    public function moveItem(string $key, ?string $before = null): self
    {
        return $this->copy(['checklist' => Parts::move($this->checklist, $key, $before, 'item of the checklist')]);
    }

    /**
     * @throws InvalidPreparation when no item has the key
     */
    public function withItemChecked(string $key, bool $checked): self
    {
        return $this->withItem(Parts::get($this->checklist, $key, 'item of the checklist')->withChecked($checked));
    }

    /**
     * The preparation with the facts of the other one: each secret revealed, clue found, scene played or skipped and
     * item checked as the part of its key is there, and every part the other one does not hold at its start —
     * hidden, not found, planned, not checked. Null starts every part.
     */
    public function withFactsOf(?self $before): self
    {
        return $this->copy([
            'scenes' => array_map(static fn (Scene $scene): Scene => $scene->withStatus($before?->scene($scene->key)->status ?? SceneStatus::Planned), $this->scenes),
            'secrets' => array_map(static fn (Secret $secret): Secret => $secret->withFactsOf($before?->secret($secret->key)), $this->secrets),
            'checklist' => array_map(static fn (ChecklistItem $item): ChecklistItem => $item->withChecked($before?->item($item->key)->checked ?? false), $this->checklist),
        ]);
    }

    /**
     * @param array{title?: string, gameMaster?: string, session?: ?string, scenes?: list<Scene>, secrets?: list<Secret>, characters?: list<NonPlayerCharacter>, places?: list<Place>, checklist?: list<ChecklistItem>, notes?: list<Note>, status?: PreparationStatus, version?: int} $changes
     */
    private function copy(array $changes): self
    {
        $values = $changes + [
            'title' => $this->title,
            'gameMaster' => $this->gameMaster,
            'session' => $this->session,
            'scenes' => $this->scenes,
            'secrets' => $this->secrets,
            'characters' => $this->characters,
            'places' => $this->places,
            'notes' => $this->notes,
            'checklist' => $this->checklist,
            'status' => $this->status,
            'version' => $this->version,
        ];

        return new self(
            $this->id,
            $this->campaign,
            $values['gameMaster'],
            $values['title'],
            $values['session'],
            $values['scenes'],
            $values['secrets'],
            $values['characters'],
            $values['places'],
            $values['notes'],
            $values['checklist'],
            $values['status'],
            $values['version'],
        );
    }
}
