<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Mercure;

use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Tilttek\DiceTower\Application\Contract\TableAccess;
use Tilttek\DiceTower\Application\TowerTopics;

/**
 * The subscription of the current viewer to a table's tower: when the application's `TableAccess` allows it, the
 * authorization cookie of the current response is given the table's topic — and that topic only —, and the URL to
 * subscribe to on the hub is returned. A viewer who may not watch the table, or an application without a hub, gets no
 * URL.
 */
final readonly class TowerSubscriptions
{
    public function __construct(
        private TableAccess $access,
        private TowerTopics $topics,
        private ?HubInterface $hub = null,
        private ?Authorization $authorization = null,
        private ?RequestStack $requests = null,
        private ?string $hubName = null,
    ) {
    }

    public function subscribe(ResourceReference $table): ?string
    {
        if (!$this->hub instanceof HubInterface || !$this->access->canWatch($table)) {
            return null;
        }

        $topic = $this->topics->topic($table);
        $request = $this->requests?->getCurrentRequest();

        if ($this->authorization instanceof Authorization && $request instanceof Request) {
            $this->authorization->setCookie($request, [$topic], hub: $this->hubName);
        }

        $url = $this->hub->getPublicUrl();
        $parameter = ProtocolVersion::Legacy === $this->hub->getProtocolVersion() ? 'topic' : 'match';

        return $url.(str_contains($url, '?') ? '&' : '?').$parameter.'='.rawurlencode($topic);
    }
}
