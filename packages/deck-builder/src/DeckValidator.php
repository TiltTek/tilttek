<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\DeckBuilder\Contract\CardCatalog;
use Tilttek\DeckBuilder\Contract\FormatCatalog;
use Tilttek\DeckBuilder\Contract\OwnedCards;
use Tilttek\DeckBuilder\Exception\UnknownFormat;

/**
 * Checks a deck against the rules of its format on a day — sizes, copies, cards banned or restricted, the taboo list
 * that applies — and, when a collection is given, against the copies it owns. Every violation is returned, none
 * stops the others. The validator keeps nothing between two calls.
 */
final readonly class DeckValidator
{
    public function __construct(
        private CardCatalog $cards,
        private FormatCatalog $formats,
    ) {
    }

    /**
     * @param DateTimeInterface $on    the day whose rules apply, in its own time zone: a tournament's day, or today
     * @param OwnedCards|null   $owned the collection the deck is limited to; null for no limit
     *
     * @throws UnknownFormat when the format catalog does not hold the deck's format
     */
    public function validate(Deck $deck, DateTimeInterface $on, ?OwnedCards $owned = null): Validation
    {
        $format = $this->formats->find($deck->format) ?? throw UnknownFormat::named($deck->format);
        $ruling = $format->rulingOn($on);
        $violations = $this->sizes($deck, $format);

        foreach ($deck->cards as $id => $quantity) {
            $id = (string) $id;
            $card = $this->cards->find($id);

            if (!$card instanceof Card) {
                $violations[] = new Violation(ViolationType::UnknownCard, \sprintf('The card "%s" is not in the card catalog.', $id), $id, ['card' => $id, 'quantity' => $quantity]);

                continue;
            }

            $violation = $this->copies($card, $quantity, $ruling);

            if ($violation instanceof Violation) {
                $violations[] = $violation;
            }

            $copies = $owned instanceof OwnedCards ? max(0, $owned->copiesOf($id)) : $quantity;

            if ($quantity > $copies) {
                $violations[] = new Violation(ViolationType::NotOwned, \sprintf('The deck has %s of %s; the collection owns %d.', $this->count($quantity, 'copy', 'copies'), $card->label(), $copies), $id, ['card' => $id, 'quantity' => $quantity, 'owned' => $copies]);
            }
        }

        return new Validation($deck, $ruling, DateTimeImmutable::createFromInterface($on), $violations);
    }

    /**
     * @return list<Violation>
     */
    private function sizes(Deck $deck, Format $format): array
    {
        $size = $deck->size();

        if ($size < $format->minSize) {
            return [new Violation(ViolationType::TooFewCards, \sprintf('The deck has %s; the format "%s" needs at least %d.', $this->count($size, 'card', 'cards'), $format->code, $format->minSize), null, ['size' => $size, 'limit' => $format->minSize])];
        }

        if (null !== $format->maxSize && $size > $format->maxSize) {
            return [new Violation(ViolationType::TooManyCards, \sprintf('The deck has %d cards; the format "%s" allows at most %d.', $size, $format->code, $format->maxSize), null, ['size' => $size, 'limit' => $format->maxSize])];
        }

        return [];
    }

    private function copies(Card $card, int $quantity, Ruling $ruling): ?Violation
    {
        $bannedBy = $ruling->bannedBy($card->id);

        if (null !== $bannedBy) {
            return new Violation(ViolationType::Banned, \sprintf('%s is banned by %s.', $card->label(), $bannedBy), $card->id, ['card' => $card->id, 'quantity' => $quantity, 'by' => $bannedBy]);
        }

        $limit = $ruling->copyLimit($card);

        if ($quantity <= $limit) {
            return null;
        }

        $restrictedBy = $ruling->restrictedBy($card);
        $values = ['card' => $card->id, 'quantity' => $quantity, 'limit' => $limit];

        if (null !== $restrictedBy) {
            return new Violation(ViolationType::Restricted, \sprintf('The deck has %d copies of %s; %s restricts it to %d.', $quantity, $card->label(), $restrictedBy, $limit), $card->id, $values + ['by' => $restrictedBy]);
        }

        return new Violation(ViolationType::TooManyCopies, \sprintf('The deck has %d copies of %s; at most %d are allowed.', $quantity, $card->label(), $limit), $card->id, $values);
    }

    /**
     * A number with its noun: "1 card", "0 cards", "2 cards".
     */
    private function count(int $count, string $one, string $many): string
    {
        return \sprintf('%d %s', $count, 1 === $count ? $one : $many);
    }
}
