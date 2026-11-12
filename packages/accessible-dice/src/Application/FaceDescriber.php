<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * Describes the faces of the custom dice: each by its label, then its shape and its value in words — "Heads: a
 * circle, worth 1" —, never by its colour alone. A face with neither shape nor value is its label.
 */
final readonly class FaceDescriber
{
    public function __construct(
        private CustomDice $dice,
        private Words $words = new Words(),
    ) {
    }

    /**
     * @return list<FaceDescription>
     *
     * @throws CannotRoll when no custom die has this name
     */
    public function describe(string $die, ?string $locale = null): array
    {
        return array_map(fn (Face $face): FaceDescription => $this->face($face, $locale), $this->dice->get($die)->faces);
    }

    private function face(Face $face, ?string $locale = null): FaceDescription
    {
        $label = $this->words->label($face->label, $locale);
        $details = [];

        if ($face->shape instanceof Shape) {
            $details[] = $this->words->word('shape.'.$face->shape->value, [], $locale);
        }

        if (null !== $face->value) {
            $details[] = $this->words->word('face.value', ['%value%' => $face->value], $locale);
        }

        $description = [] === $details ? $label : $this->words->word('face.description', ['%label%' => $label, '%details%' => implode(', ', $details)], $locale);

        return new FaceDescription($face->id, $label, $description, $face->value, $face->shape?->value, $face->color, $face->image);
    }

    /**
     * The label of every face of every custom die, by die and by face, as the Stimulus controller names the faces of
     * the rolls it announces.
     *
     * @return array<string, array<string, string>>
     */
    public function labels(?string $locale = null): array
    {
        $labels = [];

        foreach ($this->dice->all() as $die) {
            foreach ($die->faces as $face) {
                $labels[$die->name][$face->id] = $this->words->label($face->label, $locale);
            }
        }

        return $labels;
    }
}
