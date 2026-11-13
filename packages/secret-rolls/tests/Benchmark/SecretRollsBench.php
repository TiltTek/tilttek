<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Benchmark;

use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Journal\SceneJournal;
use Tilttek\SecretRolls\RollVerifier;
use Tilttek\SecretRolls\SecretRolls;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;

/**
 * What hidden rolls cost: one roll committed — every roll the game master hides —, one revealed roll checked — what a
 * player's page does —, and the export of a scene of 500 rolls, all revealed, read and checked whole.
 */
final class SecretRollsBench
{
    private readonly SecretRolls $rolls;

    private readonly HiddenRoll $hidden;

    private readonly string $export;

    private readonly RollVerifier $verifier;

    private int $scene = 0;

    public function __construct()
    {
        $this->rolls = new SecretRolls(new InMemorySecretRollStore());
        $this->verifier = new RollVerifier();
        $this->hidden = $this->rolls->roll('bench', '4d6kh3+2');

        $long = new SecretRolls(new InMemorySecretRollStore());

        for ($roll = 0; $roll < 500; ++$roll) {
            $long->roll('long', '1d20+5');
        }

        $long->closeRevealingAll('long');
        $this->export = $long->journal('long')->toJson();
    }

    public function benchRollOutOfSight(): void
    {
        $this->rolls->roll('scene-'.++$this->scene, '1d20+5');
    }

    public function benchVerifyARevealedRoll(): void
    {
        $this->verifier->verify($this->hidden->opening, $this->hidden->commitment());
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchCheckTheExportOf500Rolls(): void
    {
        SceneJournal::fromJson($this->export)->problems($this->verifier);
    }
}
