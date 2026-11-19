<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Workflow;

use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Domain\Model\Order;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;

/**
 * Commits the draws of an order as soon as it is placed: it listens to the `place` transition of the commerce core's
 * order workflow, completed, and calls `LootCommitments::commit()`, so that each commitment exists before the buyer is
 * asked to pay. `commit_on_place: false` leaves the call to the application.
 */
final readonly class CommitOnPlace
{
    /** The event of the workflow, once the order is placed. */
    public const string EVENT = 'workflow.'.OrderWorkflow::NAME.'.completed.'.OrderWorkflow::PLACE;

    public function __construct(
        private LootCommitments $commitments,
    ) {
    }

    /**
     * @throws LootDrawRefused when a text option of the order has no list a die draws from
     */
    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();

        if ($order instanceof Order) {
            $this->commitments->commit($order);
        }
    }
}
