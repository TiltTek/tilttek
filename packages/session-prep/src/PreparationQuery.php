<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;

/**
 * Which preparations to read: of a campaign, of a game master, for a session, in a status; a criterion left null
 * matches every preparation.
 */
final readonly class PreparationQuery
{
    /**
     * @throws InvalidPreparation when an identifier is not one
     */
    public function __construct(
        public ?string $campaign = null,
        public ?string $gameMaster = null,
        public ?string $session = null,
        public ?PreparationStatus $status = null,
    ) {
        foreach (['a campaign' => $campaign, 'a member' => $gameMaster, 'a session' => $session] as $what => $id) {
            if (null !== $id) {
                Identifier::check($id, $what);
            }
        }
    }

    public function matches(Preparation $preparation): bool
    {
        return (null === $this->campaign || $this->campaign === $preparation->campaign)
            && (null === $this->gameMaster || $this->gameMaster === $preparation->gameMaster)
            && (null === $this->session || $this->session === $preparation->session)
            && (!$this->status instanceof PreparationStatus || $this->status === $preparation->status);
    }
}
