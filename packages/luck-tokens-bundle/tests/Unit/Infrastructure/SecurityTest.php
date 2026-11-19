<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControl;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter;
use Tilttek\LuckTokensBundle\Infrastructure\Security\SecurityHolderAccess;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

#[CoversClass(HolderControl::class)]
#[CoversClass(HolderControlVoter::class)]
#[CoversClass(SecurityHolderAccess::class)]
final class SecurityTest extends TestCase
{
    public function testTheVoterGrantsEachHolderTheirOwnTokensAndAbstainsOtherwise(): void
    {
        $voter = new HolderControlVoter();
        $token = new NullToken();
        $ana = Table::ref('member:ana');
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, new HolderControl($ana, Table::ref('member:ana')), ['OTHER', HolderControlVoter::CONTROL], $vote));
        self::assertSame(['Each holder controls their own tokens.'], $vote->reasons);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, new HolderControl($ana, $ana), [HolderControlVoter::CONTROL]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new HolderControl($ana, Table::ref('character:9')), [HolderControlVoter::CONTROL]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new HolderControl($ana, $ana), ['OTHER']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new HolderControl($ana, $ana), [true]), 'An attribute is compared strictly.');
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $ana, [HolderControlVoter::CONTROL]));
        self::assertSame('TILTTEK_LUCK_TOKENS_CONTROL', HolderControlVoter::CONTROL);
        self::assertTrue($voter->supportsAttribute(HolderControlVoter::CONTROL));
        self::assertFalse($voter->supportsAttribute('ROLE_USER'));
        self::assertTrue($voter->supportsType(HolderControl::class));
        self::assertFalse($voter->supportsType('string'));
    }

    public function testTheAccessIsAskedOfTheVotersAndTheApplicationGrantsTheRest(): void
    {
        $characters = new class implements VoterInterface {
            #[Override]
            public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
            {
                return $subject instanceof HolderControl && 'member:ana' === $subject->actor->toString() && 'character:9' === $subject->holder->toString() ? self::ACCESS_GRANTED : self::ACCESS_ABSTAIN;
            }
        };
        $access = new SecurityHolderAccess(new AuthorizationChecker(new TokenStorage(), new AccessDecisionManager([new HolderControlVoter(), $characters], new UnanimousStrategy())));
        $ana = Table::ref('member:ana');

        self::assertTrue($access->controls($ana, $ana));
        self::assertTrue($access->controls($ana, Table::ref('character:9')), 'The voter of the bundle abstains: a unanimous decision still grants.');
        self::assertFalse($access->controls($ana, Table::ref('character:10')));
        self::assertFalse($access->controls(Table::ref('member:bo'), Table::ref('character:9')));
    }
}
