<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badges;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesTables;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;
use Tilttek\BadgesBundle\Tests\Functional\App\Recorder;
use Tilttek\BadgesBundle\Tests\Functional\App\TestKernel;
use Tilttek\BadgesBundle\TilttekBadgesBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the badges awarded live by the journal's
 * projections and announced as events, a revocable badge withdrawn, a badge awarded by its command, the showcase
 * rendered by its template, a credential issued and verified when phil/open-badges-bundle is installed, and a member
 * exported and erased through the privacy bundle when it is installed.
 */
#[CoversClass(TilttekBadgesBundle::class)]
final class BadgesBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);

        foreach ([BadgesTables::CONTRIBUTIONS, BadgesTables::AWARDS, BadgesTables::PROFILES, BadgesTables::GRANTS] as $table) {
            self::assertTrue($schema->hasTable($table));
        }

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityEarnsBadgesThatAreShownAndIssued(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $badges = $this->service(Badges::class, Badges::class);
        $recorder = $this->service(Recorder::class, Recorder::class);
        $member = ResourceReference::of('member', '1');

        $journal->record(Activity::of('c1', 'member:1', 'news.commented', new DateTimeImmutable('2026-11-04 20:00:00 UTC')));
        $journal->record(Activity::of('c2', 'member:1', 'news.commented', new DateTimeImmutable('2026-11-05 20:00:00 UTC')));
        $journal->record(Activity::of('v1', 'member:1', 'site.visited', new DateTimeImmutable('2026-11-05 20:00:00 UTC')));
        $journal->record(Activity::of('v2', 'member:1', 'site.visited', new DateTimeImmutable('2026-11-06 20:00:00 UTC')));
        self::assertSame(['commenter', 'regular'], array_map(static fn (Award $award): string => $award->badge, $badges->awards($member)));

        $journal->cancel('c2');
        self::assertSame(['regular'], array_map(static fn (Award $award): string => $award->badge, $badges->awards($member)));
        self::assertSame(['awarded commenter member:1', 'awarded regular member:1', 'revoked commenter member:1'], $recorder->events);

        $tester = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:badges:award'));
        self::assertSame(0, $tester->execute(['member' => 'member:1', 'badge' => 'organiser']));

        $badges->feature($member, ['organiser', 'regular']);
        $badges->entitle($member, 'regular');

        $html = $this->service('twig', Environment::class)->render('@TilttekBadges/showcase.html.twig', ['showcase' => $badges->showcase($member)]);
        self::assertStringContainsString('<p class="tilttek-badges__title">Regular</p>', $html);
        self::assertStringContainsString('<img src="https://example.org/organiser.png" alt="" width="48" height="48">', $html);
        $overview = $this->service('twig', Environment::class)->render('@TilttekBadges/overview.html.twig', ['standings' => $badges->overview($member)]);
        self::assertStringContainsString('<progress max="2" value="1">1 / 2</progress>', $overview);
        self::assertStringContainsString('is-earned', $overview);
    }

    public function testABadgeIsIssuedAsAnOpenBadgesCredential(): void
    {
        if (!class_exists(PhilOpenBadgesBundle::class)) {
            self::markTestSkipped('phil/open-badges-bundle is not installed: the Open Badges credentials are an optional integration.');
        }

        $member = ResourceReference::of('member', '1');
        $tester = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:badges:award'));
        self::assertSame(0, $tester->execute(['member' => 'member:1', 'badge' => 'organiser']));

        $token = $this->service(BadgeCredentials::class, BadgeCredentials::class)->issue($member, 'organiser', Recipient::did('did:example:ada'));
        self::assertIsString($token);
        $claims = new JwtVerifier(Ed25519Signer::fromSeed(str_repeat("\1", 32))->verificationKey())->verify($token);
        self::assertSame('https://example.org/issuer', $claims['iss'] ?? null);
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $badges = $this->service(Badges::class, Badges::class);
        $member = ResourceReference::of('member', '7');
        $badges->award($member, 'organiser');
        $badges->show($member, 'organiser');

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $awards = $export['tilttek_badges']['awards'] ?? null;
        self::assertIsArray($awards);
        self::assertSame(['organiser'], array_column($awards, 'badge'));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        self::assertSame(0, $this->service('doctrine.dbal.default_connection', Connection::class)->fetchOne('SELECT COUNT(*) FROM tilttek_badges_award'));
        self::assertTrue($badges->profile($member)->isEmpty());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
