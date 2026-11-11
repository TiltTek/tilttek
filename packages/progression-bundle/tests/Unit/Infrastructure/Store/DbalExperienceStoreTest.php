<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalExperienceStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalExperienceStore::class)]
#[CoversClass(Rows::class)]
final class DbalExperienceStoreTest extends ExperienceStoreTestCase
{
    protected function store(): ExperienceStore
    {
        return new DbalExperienceStore(Database::connection());
    }
}
