<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\OpenBadges;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\Application\SecuredCredential;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\Resource\ResourceReference;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Exception\NotEarned;
use Tilttek\Badges\Exception\UnknownBadge;

/**
 * Issues a member's badge as an Open Badges 3.0 credential of `phil/open-badges`, at the member's request: the
 * achievement's identifier is the configured URL with the badge's code, its name and description are translated in the
 * member's language, its criteria are told by its description, its image is the badge's when that is an absolute URL,
 * the credential is valid from the moment the award with the highest number was earned, and it is secured as
 * `phil/open-badges-bundle` is configured to secure the issuer's credentials.
 */
final readonly class BadgeCredentials
{
    public function __construct(
        private Badges $badges,
        private CredentialIssuer $issuer,
        private Securing $securing,
        private string $achievementUrl,
        private string $domain,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * The credential of the badge's award with the highest number, secured: a compact JSON Web Token, or the JSON of
     * the document with its Data Integrity proof, with the media type and the file name of that content, for a
     * download.
     *
     * @throws UnknownBadge
     * @throws NotEarned
     */
    public function issue(ResourceReference $member, string $code, Recipient $recipient, ?DateTimeImmutable $validUntil = null, ?string $locale = null): SecuredCredential
    {
        $badge = $this->badges->catalog()->get($code);
        $award = $this->latest($member, $code) ?? throw NotEarned::badge($code, $member->toString());
        $name = $this->translate($badge->name, $locale);
        $description = '' === $badge->description ? '' : $this->translate($badge->description, $locale);
        $image = str_contains($badge->image ?? '', '://') ? $badge->image : null;

        return $this->securing->secure($this->issuer->credential(
            new Achievement(str_replace('{code}', rawurlencode($code), $this->achievementUrl), $name, $description, '' === $description ? $name : $description, $image),
            $recipient,
            'urn:uuid:'.Uuid::v4()->toRfc4122(),
            $award->awardedAt,
            $validUntil,
        ));
    }

    /**
     * The award of the badge with the highest number: the latest the member earned, whatever the moments of the awards
     * say, since two awards can share a moment and a manual award can bear an earlier one than the activity's.
     */
    private function latest(ResourceReference $member, string $code): ?Award
    {
        $latest = null;

        foreach ($this->badges->awards($member) as $award) {
            if ($award->badge === $code && (null === $latest || $award->number > $latest->number)) {
                $latest = $award;
            }
        }

        return $latest;
    }

    private function translate(string $text, ?string $locale): string
    {
        return $this->translator?->trans($text, [], $this->domain, $locale) ?? $text;
    }
}
