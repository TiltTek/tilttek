<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationQuery;
use Tilttek\SessionPrep\PreparationStatus;

#[CoversClass(PreparationQuery::class)]
final class PreparationQueryTest extends TestCase
{
    public function testEachCriterionNarrowsTheMatch(): void
    {
        $preparation = new Preparation('p', 'campaign-7', 'member-1', 'Title', 'session-3', status: PreparationStatus::Ready);

        self::assertTrue(new PreparationQuery()->matches($preparation));
        self::assertTrue(new PreparationQuery('campaign-7', 'member-1', 'session-3', PreparationStatus::Ready)->matches($preparation));
        self::assertFalse(new PreparationQuery(campaign: 'campaign-8')->matches($preparation));
        self::assertFalse(new PreparationQuery(gameMaster: 'member-2')->matches($preparation));
        self::assertFalse(new PreparationQuery(session: 'session-4')->matches($preparation));
        self::assertFalse(new PreparationQuery(status: PreparationStatus::Draft)->matches($preparation));
        self::assertFalse(new PreparationQuery(session: 'session-3')->matches($preparation->withSession(null)));

        $query = new PreparationQuery('campaign-7', 'member-1', 'session-3', PreparationStatus::Ready);
        self::assertSame(['campaign-7', 'member-1', 'session-3', PreparationStatus::Ready], [$query->campaign, $query->gameMaster, $query->session, $query->status]);
    }

    public function testACampaignIsAnIdentifier(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The identifier of a campaign is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        new PreparationQuery(campaign: '');
    }

    public function testAGameMasterIsAnIdentifier(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        new PreparationQuery(gameMaster: 'gm@example.org');
    }

    public function testASessionIsAnIdentifier(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The identifier of a session is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        new PreparationQuery(session: '1');
    }
}
