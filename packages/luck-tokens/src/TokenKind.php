<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

use Tilttek\LuckTokens\Exception\InvalidTokenRule;

/**
 * A kind of token a table hands out — inspiration, fate points, bennies, luck —: its key and its name, who holds it,
 * how many a holder keeps at most, who grants it, whether it passes from one holder to another, and what happens at
 * the start of a session.
 *
 * At the start of a session, a holder keeps what they hold, or at most `$carryOver` when it is not null — 0 keeps
 * nothing —; a kind refreshed each session then brings them up to `$refreshTo` when they hold less. Inspiration is
 * kept and never refreshed; three bennies come back each session and none is kept; fate points come back up to the
 * refresh and those above it are kept.
 */
final readonly class TokenKind
{
    public const int MAX_CAP = 1000;

    public const int MAX_NAME_LENGTH = 80;

    /**
     * @throws InvalidTokenRule when a value is not as described
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $cap,
        public HolderScope $scope = HolderScope::Character,
        public Refresh $refresh = Refresh::Never,
        public int $refreshTo = 0,
        public ?int $carryOver = null,
        public GrantPolicy $grants = GrantPolicy::GameMasters,
        public bool $transferable = false,
    ) {
        if (1 !== preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/D', $key)) {
            throw InvalidTokenRule::because(\sprintf('The key of a token is 1 to 64 characters among a-z, 0-9 and inner "-"; "%s" is not.', mb_substr($key, 0, 32)));
        }

        if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME_LENGTH || 1 === preg_match('/\p{Cc}/u', $name)) {
            throw InvalidTokenRule::because(\sprintf('The name of the token "%s" is 1 to %d characters, without control characters.', $key, self::MAX_NAME_LENGTH));
        }

        if ($cap < 1 || $cap > self::MAX_CAP) {
            throw InvalidTokenRule::because(\sprintf('A holder keeps 1 to %d "%s" at most; not %d.', self::MAX_CAP, $key, $cap));
        }

        if (Refresh::Never === $refresh && 0 !== $refreshTo) {
            throw InvalidTokenRule::because(\sprintf('The token "%s" is never refreshed: it has no refresh amount.', $key));
        }

        if (Refresh::EachSession === $refresh && ($refreshTo < 1 || $refreshTo > $cap)) {
            throw InvalidTokenRule::because(\sprintf('The token "%s" is refreshed each session to 1 to %d, its cap; not %d.', $key, $cap, $refreshTo));
        }

        if (null !== $carryOver && ($carryOver < 0 || $carryOver > $cap)) {
            throw InvalidTokenRule::because(\sprintf('A holder carries 0 to %d "%s" to the next session, or all of them; not %d.', $cap, $key, $carryOver));
        }
    }

    /**
     * What a holder of `$balance` holds once a session starts.
     */
    public function atSessionStart(int $balance): int
    {
        $kept = null === $this->carryOver ? $balance : min($balance, $this->carryOver);

        return max($kept, $this->refreshTo);
    }

    /**
     * @return array{key: string, name: string, cap: int, scope: string, refresh: string, refresh_to: int, carry_over: int|null, grants: string, transferable: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'cap' => $this->cap,
            'scope' => $this->scope->value,
            'refresh' => $this->refresh->value,
            'refresh_to' => $this->refreshTo,
            'carry_over' => $this->carryOver,
            'grants' => $this->grants->value,
            'transferable' => $this->transferable,
        ];
    }
}
