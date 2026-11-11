<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Profile;

/**
 * The profiles in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryProfileStore implements ProfileStore
{
    /**
     * @var array<string, Profile>
     */
    private array $profiles = [];

    #[Override]
    public function get(ResourceReference $member): Profile
    {
        return $this->profiles[$member->toString()] ?? new Profile($member);
    }

    #[Override]
    public function save(Profile $profile): void
    {
        if ($profile->isEmpty()) {
            unset($this->profiles[$profile->member->toString()]);

            return;
        }

        $this->profiles[$profile->member->toString()] = $profile;
    }

    #[Override]
    public function erase(ResourceReference $member): bool
    {
        $known = isset($this->profiles[$member->toString()]);
        unset($this->profiles[$member->toString()]);

        return $known;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->profiles = [];
    }
}
