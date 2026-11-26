<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * A scene that might happen: a title, what the game master needs to run it, the non-player characters and places it
 * features, by their keys in the preparation, and whether it was played or skipped.
 */
final readonly class Scene implements Part
{
    public const int MAX_TITLE = 200;

    public const int MAX_SUMMARY = 4_000;

    public const int MAX_CHARACTERS = 20;

    public const int MAX_PLACES = 10;

    public string $title;

    public string $summary;

    /** @var list<string> */
    public array $characters;

    /** @var list<string> */
    public array $places;

    /**
     * @param string       $title      one line, 1 to 200 characters
     * @param string       $summary    lines, at most 4000 characters
     * @param list<string> $characters the keys of the non-player characters it features, at most 20
     * @param list<string> $places     the keys of the places it happens in, at most 10
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $key,
        string $title,
        string $summary = '',
        array $characters = [],
        array $places = [],
        public SceneStatus $status = SceneStatus::Planned,
    ) {
        Identifier::key($key, 'a scene');
        $this->title = Text::line($title, self::MAX_TITLE, 'title of a scene');
        $this->summary = Text::block($summary, self::MAX_SUMMARY, 'summary of a scene');
        $this->characters = Identifier::keys($characters, self::MAX_CHARACTERS, "A scene's list of characters");
        $this->places = Identifier::keys($places, self::MAX_PLACES, "A scene's list of places");
    }

    public function withStatus(SceneStatus $status): self
    {
        return new self($this->key, $this->title, $this->summary, $this->characters, $this->places, $status);
    }

    /**
     * The scene without the character of the key among those it features.
     */
    public function withoutCharacter(string $key): self
    {
        return new self($this->key, $this->title, $this->summary, array_values(array_diff($this->characters, [$key])), $this->places, $this->status);
    }

    /**
     * The scene without the place of the key among those it happens in.
     */
    public function withoutPlace(string $key): self
    {
        return new self($this->key, $this->title, $this->summary, $this->characters, array_values(array_diff($this->places, [$key])), $this->status);
    }
}
