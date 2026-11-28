<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Exception;

use DomainException;
use Tilttek\Inventory\Refusal;

/**
 * An act the inventory's rules refuse, and why.
 */
final class InventoryRefused extends DomainException implements InventoryException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::AlreadyOpen => 'An inventory already holds this reference.',
            Refusal::TooManyInventories => 'This owner holds as many inventories as allowed.',
            Refusal::ItemExists => 'The inventory already holds an item under this key.',
            Refusal::TooManyItems => 'The inventory holds as many items as allowed.',
            Refusal::NotEnough => 'The inventory holds fewer of this item than asked.',
            Refusal::NotAContainer => 'This item is not a container.',
            Refusal::ContainerCycle => 'A container cannot go inside itself or what it holds.',
            Refusal::TooDeep => 'Containers would nest deeper than allowed.',
            Refusal::ContainerFull => 'The container cannot hold this much.',
            Refusal::ContainerNotEmpty => 'The container still holds items.',
            Refusal::SingleContainer => 'A container is always a single item.',
            Refusal::NotCarried => 'Only an item a carrier holds in hand or on them can be equipped.',
            Refusal::OverCapacity => 'The carrier cannot carry this much.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
