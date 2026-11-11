<?php

declare(strict_types=1);

namespace Tilttek\Points;

use Phil\Resource\ResourceReference;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * An account of the ledger: its type and its name. A member's account is named by the member's opaque reference,
 * `member:44`; an issuance account by the rule or the source that issues, `games` or `manual`; a redemption account
 * by what the points pay for, `reward:12`. Written as a string, the type comes first: `member:member:44`,
 * `issuance:games`, `redemption:reward:12`, `expiry:expired`, `erased:member`.
 */
final readonly class Account
{
    /**
     * The longest account written as a string: a type, a colon and a member reference of 320 bytes.
     */
    public const int MAX_LENGTH = 340;

    private function __construct(
        public AccountType $type,
        public string $name,
    ) {
    }

    public static function member(ResourceReference $member): self
    {
        return new self(AccountType::Member, $member->toString());
    }

    /**
     * Where a rule or a source issues points from, named like `games`, `manual` or `campaign:2026-spring`.
     */
    public static function issuance(string $name): self
    {
        return new self(AccountType::Issuance, self::checked($name));
    }

    /**
     * Where spent points go, named by what they pay for, like `reward:12` or `order:2026-0193`.
     */
    public static function redemption(string $name): self
    {
        return new self(AccountType::Redemption, self::checked($name));
    }

    public static function expiry(): self
    {
        return new self(AccountType::Expiry, 'expired');
    }

    public static function erased(): self
    {
        return new self(AccountType::Erased, 'member');
    }

    /**
     * The account `toString()` wrote.
     */
    public static function fromString(string $account): self
    {
        [$type, $name] = explode(':', $account, 2) + [1 => ''];

        return match (AccountType::tryFrom($type)) {
            AccountType::Member => self::member(ResourceReference::fromString($name)),
            AccountType::Issuance => self::issuance($name),
            AccountType::Redemption => self::redemption($name),
            AccountType::Expiry => self::expiry(),
            AccountType::Erased => self::erased(),
            null => throw InvalidPoints::because(\sprintf('"%s" is not an account: a type among member, issuance, redemption, expiry and erased, a colon and a name.', $account)),
        };
    }

    /**
     * The member whose account this is; null for another type of account.
     */
    public function memberReference(): ?ResourceReference
    {
        return AccountType::Member === $this->type ? ResourceReference::fromString($this->name) : null;
    }

    public function isMember(): bool
    {
        return AccountType::Member === $this->type;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->name === $other->name;
    }

    public function toString(): string
    {
        return $this->type->value.':'.$this->name;
    }

    private static function checked(string $name): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.:-]{0,254}$/D', $name)) {
            throw InvalidPoints::because(\sprintf('"%s" is not an account name: 1 to 255 lower-case letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.', $name));
        }

        return $name;
    }
}
