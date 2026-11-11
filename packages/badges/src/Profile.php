<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Phil\Resource\ResourceReference;
use Tilttek\Badges\Exception\InvalidBadge;

/**
 * What a member chose to show of their badges: the badges shown — every other badge they earned stays private —, the
 * showcase of their profile, in their order, and the title worn beside their name, one at a time. A featured badge
 * and the title are shown badges. It is immutable: each method returns a new profile.
 */
final readonly class Profile
{
    /**
     * @param list<string> $shown
     * @param list<string> $featured
     */
    public function __construct(
        public ResourceReference $member,
        public array $shown = [],
        public array $featured = [],
        public ?string $title = null,
    ) {
        if (array_unique($shown) !== $shown || array_unique($featured) !== $featured) {
            throw InvalidBadge::because(\sprintf('The profile of %s lists a badge twice.', $member->toString()));
        }

        if ([] !== array_diff($featured, $shown) || (null !== $title && !\in_array($title, $shown, true))) {
            throw InvalidBadge::because(\sprintf('The profile of %s features or wears a badge it does not show.', $member->toString()));
        }
    }

    public function shows(string $badge): bool
    {
        return \in_array($badge, $this->shown, true);
    }

    public function show(string $badge): self
    {
        return $this->shows($badge) ? $this : new self($this->member, [...$this->shown, $badge], $this->featured, $this->title);
    }

    /**
     * The badge made private again: taken out of the showcase, and off as a title.
     */
    public function hide(string $badge): self
    {
        return new self($this->member, $this->without($this->shown, $badge), $this->without($this->featured, $badge), $badge === $this->title ? null : $this->title);
    }

    /**
     * The showcase replaced by these badges, in this order, each shown.
     *
     * @param list<string> $badges
     */
    public function feature(array $badges): self
    {
        $profile = $this;

        foreach ($badges as $badge) {
            $profile = $profile->show($badge);
        }

        return new self($this->member, $profile->shown, $badges, $this->title);
    }

    /**
     * The title worn, shown; none when null.
     */
    public function entitle(?string $badge): self
    {
        $profile = null === $badge ? $this : $this->show($badge);

        return new self($this->member, $profile->shown, $this->featured, $badge);
    }

    /**
     * @param list<string> $badges
     *
     * @return list<string>
     */
    private function without(array $badges, string $badge): array
    {
        return array_values(array_diff($badges, [$badge]));
    }

    public function isEmpty(): bool
    {
        return [] === $this->shown;
    }
}
