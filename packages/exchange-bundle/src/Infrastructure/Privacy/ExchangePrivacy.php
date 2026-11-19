<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeErasure;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStore;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Exchange\Transfer\TransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;

/**
 * The exchanges' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export lists the member's reservations, transfers and offers; the
 * erasure deletes the reservations, withdraws the open offers, and writes `erased:member` in place of the member in
 * the transfers and the offers they had with other members. The points are erased by `tilttek/points-bundle`.
 */
final readonly class ExchangePrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_exchange';

    public function __construct(
        private ExchangeErasure $erasure,
        private ReservationStore $reservations,
        private string $memberKind,
        private ?TransferLog $transfers = null,
        private ?OfferStore $offers = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array<string, non-empty-list<array<string, mixed>>>
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);
        $export = [
            'reservations' => array_map(static fn (Reservation $reservation): array => $reservation->toArray(), $this->reservations->ofMember($member)),
            'transfers' => array_map(static fn (Transfer $transfer): array => $transfer->toArray(), $this->transfers?->ofMember($member) ?? []),
            'offers' => array_map(static fn (Offer $offer): array => $offer->toArray(), $this->offers?->ofMember($member) ?? []),
        ];

        return array_filter($export, static fn (array $rows): bool => [] !== $rows);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->erasure->erase($this->member($subject));
        $effects = [ExchangeTables::RESERVATIONS => ErasureEffect::Deleted];

        if ($this->transfers instanceof TransferLog) {
            $effects[ExchangeTables::TRANSFERS.'.from_member'] = ErasureEffect::Redacted;
            $effects[ExchangeTables::TRANSFERS.'.to_member'] = ErasureEffect::Redacted;
        }

        if ($this->offers instanceof OfferStore) {
            $effects[ExchangeTables::OFFERS.'.proposer'] = ErasureEffect::Redacted;
            $effects[ExchangeTables::OFFERS.'.recipient'] = ErasureEffect::Redacted;
        }

        return ErasureReport::of(self::MODULE, $effects);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
