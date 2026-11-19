<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\PhilCommerceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tilttek\LootTablesBundle\TilttekLootTablesBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    PhilCommerceBundle::class => ['all' => true],
    TilttekLootTablesBundle::class => ['all' => true],
];
