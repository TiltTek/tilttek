<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\Leaderboards\Contract\ValkeyConnection;

/**
 * A Valkey connection for the tests, over a socket in RESP2: enough to run the store against a real server without an
 * extension nor a client library.
 */
final class RespConnection implements ValkeyConnection
{
    /**
     * @var resource
     */
    private $socket;

    public function __construct(string $url)
    {
        $parts = parse_url($url);
        $socket = stream_socket_client(\sprintf('tcp://%s:%d', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 6379), $code, $message, 2.0);

        if (false === $socket) {
            throw new RuntimeException(\sprintf('No Valkey at %s: %s.', $url, $message));
        }

        stream_set_timeout($socket, 5);
        $this->socket = $socket;
        $database = ltrim($parts['path'] ?? '', '/');

        if ('' !== $database) {
            $this->execute('SELECT', $database);
        }
    }

    #[Override]
    public function execute(string ...$arguments): array|int|string|null
    {
        $command = '*'.\count($arguments)."\r\n";

        foreach ($arguments as $argument) {
            $command .= '$'.\strlen($argument)."\r\n".$argument."\r\n";
        }

        fwrite($this->socket, $command);

        return $this->read();
    }

    /**
     * @return int|list<mixed>|string|null
     */
    private function read(): array|int|string|null
    {
        $line = fgets($this->socket);

        if (false === $line) {
            throw new RuntimeException('Valkey closed the connection.');
        }

        $type = $line[0];
        $payload = substr($line, 1, -2);

        return match ($type) {
            '+' => $payload,
            '-' => throw new RuntimeException($payload),
            ':' => (int) $payload,
            '$' => '-1' === $payload ? null : $this->bulk((int) $payload),
            '*' => '-1' === $payload ? null : $this->list((int) $payload),
            default => throw new RuntimeException(\sprintf('"%s" is not a RESP reply.', $line)),
        };
    }

    /**
     * @return list<mixed>
     */
    private function list(int $count): array
    {
        $items = [];

        for ($index = 0; $index < $count; ++$index) {
            $items[] = $this->read();
        }

        return $items;
    }

    private function bulk(int $length): string
    {
        $data = '';

        while (($missing = $length + 2 - \strlen($data)) > 0) {
            $chunk = fread($this->socket, $missing);

            if (false === $chunk || '' === $chunk) {
                throw new RuntimeException('Valkey closed the connection.');
            }

            $data .= $chunk;
        }

        return substr($data, 0, $length);
    }
}
