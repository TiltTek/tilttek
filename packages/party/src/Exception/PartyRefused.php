<?php

declare(strict_types=1);

namespace Tilttek\Party\Exception;

use DomainException;
use Tilttek\Party\Refusal;

/**
 * An act the party's rules refuse, and why.
 */
final class PartyRefused extends DomainException implements PartyException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::AlreadyFormed => 'A party already holds this reference.',
            Refusal::AlreadyMember => 'This member already belongs to the party.',
            Refusal::NotMember => 'This member does not belong to the party.',
            Refusal::Full => 'The party holds as many members as allowed.',
            Refusal::TooManyParties => 'This member belongs to as many parties as allowed.',
            Refusal::LootExists => 'The party already holds loot under this key.',
            Refusal::TooMuchLoot => 'The party holds as many loot entries as allowed.',
            Refusal::NotEnoughLoot => 'The party holds fewer of this loot than asked.',
            Refusal::NoteExists => 'The party already holds a note under this key.',
            Refusal::TooManyNotes => 'The party holds as many notes as allowed.',
            Refusal::NotEnoughFunds => 'The purse holds less of this currency than asked.',
            Refusal::NoOneToShare => 'No member is there to receive a share.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
