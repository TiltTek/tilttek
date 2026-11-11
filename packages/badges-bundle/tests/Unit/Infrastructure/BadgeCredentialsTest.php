<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tilttek\Badges\Exception\NotEarned;
use Tilttek\Badges\Exception\UnknownBadge;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;
use Tilttek\BadgesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\BadgesBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(BadgeCredentials::class)]
final class BadgeCredentialsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(Securing::class)) {
            self::markTestSkipped('phil/open-badges-bundle is not installed: the Open Badges credentials are an optional integration.');
        }
    }

    public function testACredentialIsIssuedForAnEarnedBadge(): void
    {
        $clock = new Clock();
        $badges = Kit::badges($clock);
        $member = ResourceReference::of('member', '1');
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer);
        $securing = new Securing($signer, 'https://example.org/issuer#key-1', Securing::JWT, $clock);
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['badges.organiser' => 'Organisateur', 'badges.commenter' => 'Commentateur', 'badges.commenter.description' => 'Trois commentaires.'], 'fr', 'trophies');

        $credentials = new BadgeCredentials($badges, $issuer, $securing, 'https://example.org/badges/{code}', 'trophies', $translator);
        $verifier = new JwtVerifier($signer->verificationKey());
        self::assertSame('application/vc+jwt', $credentials->mediaType());

        $badges->award($member, 'games');
        $clock->set('2026-11-08 12:00:00 UTC');
        $second = $badges->award($member, 'games');
        $clock->set('2026-11-09 12:00:00 UTC');
        $badges->award($member, 'organiser');
        $games = $verifier->verify($this->token($credentials->issue($member, 'games', Recipient::did('did:example:ada'), new DateTimeImmutable('2027-01-01 00:00:00 UTC'))));
        self::assertSame($second->awardedAt->getTimestamp(), $games['nbf'] ?? null);
        self::assertSame(new DateTimeImmutable('2027-01-01 00:00:00 UTC')->getTimestamp(), $games['exp'] ?? null);
        self::assertMatchesRegularExpression('/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', \is_string($games['jti'] ?? null) ? $games['jti'] : '');
        self::assertSame(['id' => 'https://example.org/badges/games', 'type' => ['Achievement'], 'name' => 'Games', 'description' => '', 'criteria' => ['narrative' => 'Games']], $this->achievement($games));

        $organiser = $verifier->verify($this->token($credentials->issue($member, 'organiser', Recipient::did('did:example:ada'))));
        self::assertSame(['id' => 'https://example.org/badges/organiser', 'type' => ['Achievement'], 'name' => 'Organisateur', 'description' => '', 'criteria' => ['narrative' => 'Organisateur']], $this->achievement($organiser));
        self::assertArrayNotHasKey('exp', $organiser);

        // The member's language, the description as criteria, an absolute image.
        $badges->award($member, 'commenter');
        $commenter = $verifier->verify($this->token($credentials->issue($member, 'commenter', Recipient::did('did:example:ada'), locale: 'en')));
        self::assertSame(['id' => 'https://example.org/badges/commenter', 'type' => ['Achievement'], 'name' => 'badges.commenter', 'description' => 'badges.commenter.description', 'criteria' => ['narrative' => 'badges.commenter.description'], 'image' => ['id' => 'https://example.org/commenter.png', 'type' => 'Image']], $this->achievement($commenter));
        self::assertSame('Trois commentaires.', $this->achievement($verifier->verify($this->token($credentials->issue($member, 'commenter', Recipient::did('did:example:ada')))))['description'] ?? null);

        // Without a translator, the texts as they are.
        $plain = new BadgeCredentials($badges, $issuer, $securing, 'https://example.org/b/{code}', 'trophies');
        self::assertSame('badges.organiser', $this->achievement($verifier->verify($this->token($plain->issue($member, 'organiser', Recipient::did('did:example:ada')))))['name'] ?? null);
        self::assertStringContainsString('organiser', Base64Url::decode(explode('.', $this->token($plain->issue($member, 'organiser', Recipient::did('did:example:ada'))))[1]));
    }

    public function testACredentialIsSecuredAsConfigured(): void
    {
        $clock = new Clock();
        $badges = Kit::badges($clock);
        $member = ResourceReference::of('member', '1');
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $securing = new Securing($signer, 'https://example.org/issuer#key-1', Securing::DATA_INTEGRITY, $clock);
        $credentials = new BadgeCredentials($badges, new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer), $securing, 'https://example.org/badges/{code}', 'badges');
        $badges->award($member, 'organiser');

        $document = $credentials->issue($member, 'organiser', Recipient::did('did:example:ada'));
        self::assertIsArray($document);
        self::assertSame('https://example.org/badges/organiser', $this->achievement($document)['id'] ?? null);
        self::assertSame(['type' => 'DataIntegrityProof', 'cryptosuite' => 'eddsa-rdfc-2022'], array_intersect_key(\is_array($document['proof'] ?? null) ? $document['proof'] : [], ['type' => true, 'cryptosuite' => true]));
        self::assertSame('application/vc', $credentials->mediaType());
    }

    public function testACredentialIsIssuedOnlyForAnEarnedBadge(): void
    {
        $this->expectExceptionObject(NotEarned::badge('organiser', 'member:1'));
        $this->credentials()->issue(ResourceReference::of('member', '1'), 'organiser', Recipient::did('did:example:ada'));
    }

    public function testACredentialIsIssuedOnlyForAKnownBadge(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('gold'));
        $this->credentials()->issue(ResourceReference::of('member', '1'), 'gold', Recipient::did('did:example:ada'));
    }

    private function token(mixed $credential): string
    {
        self::assertIsString($credential);

        return $credential;
    }

    private function credentials(): BadgeCredentials
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));

        return new BadgeCredentials(Kit::badges(), new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer), new Securing($signer, 'https://example.org/issuer#key-1', Securing::JWT, new Clock()), 'https://example.org/badges/{code}', 'badges');
    }

    /**
     * @param array<array-key, mixed> $claims
     *
     * @return array<array-key, mixed>
     */
    private function achievement(array $claims): array
    {
        $subject = $claims['credentialSubject'] ?? null;
        $achievement = \is_array($subject) ? $subject['achievement'] ?? null : null;

        return \is_array($achievement) ? $achievement : [];
    }
}
