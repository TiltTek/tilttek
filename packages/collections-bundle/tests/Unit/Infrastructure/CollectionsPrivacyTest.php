<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Source;
use Tilttek\CollectionsBundle\Infrastructure\Privacy\CollectionsPrivacy;
use Tilttek\CollectionsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(CollectionsPrivacy::class)]
final class CollectionsPrivacyTest extends TestCase
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
        $kit = Kit::standard();
        $privacy = new CollectionsPrivacy($kit->data, 'member');
        $copy = $kit->collections->grant(ResourceReference::fromString('member:7'), 'pirate', Source::Granted, 'k1');

        self::assertSame('tilttek_collections', $privacy->module()->value);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));
        self::assertSame(['copies' => [$copy->toArray()], 'completions' => [], 'openings' => [], 'choices' => []], $privacy->export(DataSubject::fromString('7')));
        self::assertSame([], new CollectionsPrivacy($kit->data, 'user')->export(DataSubject::fromString('7')));

        $report = $privacy->erase(DataSubject::fromString('7'));
        self::assertSame([
            'tilttek_collections_copy' => ErasureEffect::Deleted,
            'tilttek_collections_completion' => ErasureEffect::Deleted,
            'tilttek_collections_opening' => ErasureEffect::Deleted,
            'tilttek_collections_choice' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('7')));
    }

    public function testEachKindOfDataIsEnoughToExport(): void
    {
        $kit = Kit::standard();
        $privacy = new CollectionsPrivacy($kit->data, 'member');
        $member = ResourceReference::fromString('member:3');
        $copy = $kit->collections->grant($member, 'ember-d6', Source::Granted, 'k1');
        $kit->dice->choose($member, $copy->id);
        $kit->packs->open($kit->packs->commit(ResourceReference::fromString('member:4'), 'booster', 'shop:1'), 'mine');
        $kit->copies->eraseOwner(ResourceReference::fromString('member:4'));
        $kit->copies->eraseOwner($member);

        self::assertCount(1, $privacy->export(DataSubject::fromString('3'))['choices'] ?? []);
        self::assertCount(1, $privacy->export(DataSubject::fromString('4'))['openings'] ?? []);
    }
}
