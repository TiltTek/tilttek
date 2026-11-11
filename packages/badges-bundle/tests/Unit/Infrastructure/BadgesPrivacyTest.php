<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\BadgesBundle\Infrastructure\Privacy\BadgesPrivacy;
use Tilttek\BadgesBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(BadgesPrivacy::class)]
final class BadgesPrivacyTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $badges = Kit::badges();
        $member = ResourceReference::of('user', '7');
        $privacy = new BadgesPrivacy($badges, 'user');

        self::assertSame('tilttek_badges', $privacy->module()->value);
        self::assertSame([], new BadgesPrivacy($badges, 'user')->export(DataSubject::fromString('7')));

        $badges->award($member, 'organiser', 'Ran the convention');
        $badges->entitle($member, 'organiser');
        $badges->grant($member, 'portfolio');

        $export = $privacy->export(DataSubject::fromString('7'));
        self::assertSame([
            'awards' => [['badge' => 'organiser', 'member' => 'user:7', 'number' => 1, 'awarded_at' => '2026-11-07T12:00:00+00:00', 'cause' => 'Ran the convention', 'manual' => true]],
            'shown' => ['organiser'],
            'featured' => [],
            'title' => 'organiser',
            'clients' => ['portfolio'],
        ], $export);

        $report = $privacy->erase(DataSubject::fromString('7'));
        self::assertSame('tilttek_badges', $report->module->value);
        self::assertSame([
            'tilttek_badges_award' => ErasureEffect::Deleted,
            'tilttek_badges_contribution' => ErasureEffect::Deleted,
            'tilttek_badges_profile' => ErasureEffect::Deleted,
            'tilttek_badges_grant' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], new BadgesPrivacy($badges, 'user')->export(DataSubject::fromString('7')));
    }

    public function testAMemberWhoStillShowsABadgeTheyLostIsExported(): void
    {
        $badges = Kit::badges();
        $member = ResourceReference::of('member', '9');
        $badges->award($member, 'organiser');
        $badges->show($member, 'organiser');
        $badges->revoke($member, 'organiser');

        self::assertSame(['awards' => [], 'shown' => ['organiser'], 'featured' => [], 'title' => null, 'clients' => []], new BadgesPrivacy($badges, 'member')->export(DataSubject::fromString('9')));
    }

    public function testAMemberWhoOnlyShowsOrAllowsIsExported(): void
    {
        $badges = Kit::badges();
        $member = ResourceReference::of('member', '8');
        $privacy = new BadgesPrivacy($badges, 'member');

        $badges->grant($member, 'portfolio');
        self::assertSame(['portfolio'], $privacy->export(DataSubject::fromString('8'))['clients'] ?? null);
    }
}
