<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;

/**
 * A calendar the site edits, kept by the ORM in `tilttek_advent_calendar`: its identifier, its title, its timezone,
 * its first date and closing, its opening policy, whether it is published, and its boxes' surprises in order, as JSON —
 * a box is read with its calendar, never alone. The fields are what an editing form changes; `toCalendar()` checks
 * them together, as the library does.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class CalendarRecord
{
    public const string TABLE = 'tilttek_advent_calendar';

    /**
     * @param array<int, array{kind: string, reference: string, quantity: int}> $surprises one per box, in the order of the days; a
     *                                                                                     form that removes one may leave a gap in the keys
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64)]
        public string $id = '',
        #[ORM\Column(name: 'title', length: 255)]
        public string $title = '',
        #[ORM\Column(name: 'timezone', length: 64)]
        public string $timezone = 'UTC',
        #[ORM\Column(name: 'first_date', length: 10)]
        public string $firstDate = '',
        #[ORM\Column(name: 'closing_date', length: 10, nullable: true)]
        public ?string $closingDate = null,
        #[ORM\Column(name: 'policy', length: 16, enumType: PolicyKind::class)]
        public PolicyKind $policy = PolicyKind::UntilClosing,
        #[ORM\Column(name: 'catch_up_days', type: 'smallint')]
        public int $catchUpDays = 0,
        #[ORM\Column(name: 'published')]
        public bool $published = false,
        #[ORM\Column(name: 'surprises', type: 'json')]
        public array $surprises = [],
    ) {
    }

    public static function fromCalendar(Calendar $calendar, string $title, bool $published = true): self
    {
        return new self(
            $calendar->id,
            $title,
            $calendar->timezone->getName(),
            $calendar->firstDate,
            $calendar->closingDate,
            $calendar->policy->kind,
            $calendar->policy->catchUpDays,
            $published,
            array_map(static fn (Surprise $surprise): array => $surprise->toArray(), $calendar->surprises),
        );
    }

    /**
     * The calendar of the library, checked as a whole.
     *
     * @throws InvalidCalendar when the fields do not hold together
     */
    public function toCalendar(): Calendar
    {
        return Calendar::of(
            $this->id,
            $this->timezone,
            $this->firstDate,
            array_values(array_map(static fn (array $surprise): Surprise => Surprise::of(SurpriseKind::tryFrom($surprise['kind']) ?? throw InvalidCalendar::because(\sprintf('"%s" is not a kind of surprise.', $surprise['kind'])), $surprise['reference'], $surprise['quantity']), $this->surprises)),
            self::policyOf($this->policy, $this->catchUpDays),
            $this->closingDate,
        );
    }

    /**
     * The opening policy of a kind; the days of a catch-up count only for one.
     */
    public static function policyOf(PolicyKind $kind, int $catchUpDays): OpeningPolicy
    {
        return match ($kind) {
            PolicyKind::UntilClosing => OpeningPolicy::untilClosing(),
            PolicyKind::SameDay => OpeningPolicy::sameDay(),
            PolicyKind::CatchUp => OpeningPolicy::catchUp($catchUpDays),
        };
    }
}
