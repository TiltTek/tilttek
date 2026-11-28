<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RulesLookup\Exception\InvalidRule;

/**
 * The identifier of a rule: the system it belongs to, the version of that system, and its key in it. Written
 * `srd-5.2@5.2.1:condition.blinded`, or `lanterns:range.near` for a system without version.
 */
final readonly class RuleId
{
    private const string VERSION = '/^[A-Za-z0-9][A-Za-z0-9._+-]{0,31}$/D';

    /**
     * @param string  $system  the key of the system, `srd-5.2`
     * @param string  $key     the key of the rule in the system, `condition.blinded`
     * @param ?string $version the version of the system, `5.2.1`
     *
     * @throws InvalidRule
     */
    public function __construct(public string $system, public string $key, public ?string $version = null)
    {
        if (!Folding::isKey($system) || !Folding::isKey($key)) {
            throw InvalidRule::because(\sprintf('The system "%s" and the key "%s" of a rule must each be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.', $system, $key));
        }

        if (null !== $version && !self::isVersion($version)) {
            throw InvalidRule::because(\sprintf('The version "%s" of a rule must be 1 to 32 letters, digits, ".", "_", "+" or "-".', $version));
        }
    }

    /**
     * Whether a text is a version of a system: 1 to 32 letters, digits, `.`, `_`, `+` or `-`, starting with a letter or
     * a digit.
     */
    public static function isVersion(string $version): bool
    {
        return 1 === preg_match(self::VERSION, $version);
    }

    /**
     * An identifier written `system@version:key` or `system:key`.
     *
     * @throws InvalidRule
     */
    public static function fromString(string $id): self
    {
        if (1 !== preg_match('/^([^@:]+)(?:@([^@:]+))?:([^@:]+)$/D', $id, $parts)) {
            throw InvalidRule::because(\sprintf('"%s" is not a rule identifier such as "srd-5.2@5.2.1:condition.blinded".', $id));
        }

        return new self($parts[1], $parts[3], '' === $parts[2] ? null : $parts[2]);
    }

    /**
     * Another rule of the same system and version.
     *
     * @throws InvalidRule
     */
    public function sibling(string $key): self
    {
        return new self($this->system, $key, $this->version);
    }

    public function toString(): string
    {
        return null === $this->version
            ? \sprintf('%s:%s', $this->system, $this->key)
            : \sprintf('%s@%s:%s', $this->system, $this->version, $this->key);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }
}
