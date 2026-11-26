<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Exception;

use DomainException;
use Tilttek\Guilds\Refusal;

/**
 * An act the guild's rules refuse, and why.
 */
final class GuildRefused extends DomainException implements GuildsException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::AlreadyFounded => 'A guild already holds this reference.',
            Refusal::AlreadyMember => 'This member already belongs to the guild.',
            Refusal::AlreadyRequested => 'This member already asked to join the guild.',
            Refusal::NotMember => 'This member does not belong to the guild.',
            Refusal::NoRequest => 'This member has no request to join the guild.',
            Refusal::Full => 'The guild holds as many members as its capacity.',
            Refusal::TooManyGuilds => 'This member belongs to, or asked to join, as many guilds as allowed.',
            Refusal::TooManyRequests => 'The guild holds as many waiting requests as allowed.',
            Refusal::NotAllowed => 'The role of this member does not allow this act.',
            Refusal::FounderStays => 'The founder hands the guild to another member before leaving it.',
            Refusal::BelowMembers => 'The capacity cannot go below the members the guild holds.',
            Refusal::GoalExists => 'A goal already holds this key.',
            Refusal::TooManyGoals => 'The guild holds as many open goals as allowed.',
            Refusal::GoalOver => 'The goal is reached, closed or past its deadline.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
