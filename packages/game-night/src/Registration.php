<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use Tilttek\GameNight\Internal\Identifier;

/**
 * A member signed up for a night, alone or with one guest. The guest has no identifier: they take a place, and
 * nothing else of them is kept.
 */
final readonly class Registration
{
    /**
     * @param bool    $guest whether the member brings a guest, who takes a place
     * @param ?string $table the table the member and their guest sit at, once seated
     *
     * @throws Exception\InvalidNight when an identifier is not one
     */
    public function __construct(
        public string $member,
        public bool $guest = false,
        public RegistrationStatus $status = RegistrationStatus::Confirmed,
        public ?string $table = null,
    ) {
        Identifier::check($member, 'a member');

        if (null !== $table) {
            Identifier::check($table, 'a table');
        }
    }

    /**
     * The places the sign-up takes: 1, or 2 with a guest.
     */
    public function places(): int
    {
        return $this->guest ? 2 : 1;
    }

    public function isConfirmed(): bool
    {
        return RegistrationStatus::Confirmed === $this->status;
    }

    public function confirmed(): self
    {
        return new self($this->member, $this->guest, RegistrationStatus::Confirmed, $this->table);
    }

    public function seatedAt(?string $table): self
    {
        return new self($this->member, $this->guest, $this->status, $table);
    }
}
