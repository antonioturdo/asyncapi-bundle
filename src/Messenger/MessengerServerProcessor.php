<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Messenger;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Document\Server;
use Zeusi\AsyncApiBundle\Processor\AsyncApiProcessorInterface;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

/**
 * Adds an AsyncAPI server for the Messenger transports a documented message is
 * routed to, derived from the transport DSN (scheme -> protocol, host[:port]).
 *
 * Servers are added only for transports an attribute-discovered message actually
 * targets — Messenger enriches the declared messages, it does not enumerate them.
 * Internal transports (sync, in-memory, doctrine…) and transports no documented
 * message uses are left out; a server already declared in config is untouched.
 *
 * It also fills a message's content type from the serializer of the transport it is
 * routed to, when derivable, and only when the message did not declare one.
 */
final class MessengerServerProcessor implements AsyncApiProcessorInterface
{
    /**
     * Messenger DSN scheme -> AsyncAPI protocol. Schemes outside this map are not
     * documentable brokers and are skipped.
     */
    private const PROTOCOLS = [
        'amqp' => 'amqp',
        'amqps' => 'amqp',
        'redis' => 'redis',
        'rediss' => 'redis',
        'kafka' => 'kafka',
        'sqs' => 'sqs',
        'sns' => 'sns',
        'mqtt' => 'mqtt',
        'stomp' => 'stomp',
    ];

    private readonly RouteResolver $routes;

    /**
     * @param array<string, string>       $transports   transport name => DSN
     * @param array<string, list<string>> $routing      message class/interface/wildcard/`*` => transport names
     * @param array<string, string>       $contentTypes transport name => wire content type (when derivable)
     */
    public function __construct(
        private readonly array $transports = [],
        array $routing = [],
        private readonly array $contentTypes = [],
    ) {
        $this->routes = new RouteResolver($routing);
    }

    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        $routed = [];
        $channelTransports = [];

        foreach ($document->channels as $key => $channel) {
            foreach ($channel->messages as $message) {
                if (!$message instanceof Message || $message->payloadClass === null) {
                    continue;
                }

                $transports = $this->routes->resolve($message->payloadClass);
                $this->applyContentType($message, $transports);

                foreach ($transports as $transport) {
                    $routed[$transport] = true;
                    $channelTransports[$key][$transport] = true;
                }
            }
        }

        // Messages already hoisted into the catalog still get their content type
        // (defensive: at this stage they normally still live inline on a channel).
        foreach ($document->components->messages as $message) {
            if ($message->payloadClass !== null) {
                $this->applyContentType($message, $this->routes->resolve($message->payloadClass));
            }
        }

        foreach (array_keys($routed) as $transport) {
            if (isset($document->servers[$transport]) || !isset($this->transports[$transport])) {
                continue;
            }

            $server = $this->serverFromDsn($this->transports[$transport]);

            if ($server !== null) {
                $document->servers[$transport] = $server;
            }
        }

        $this->associateServers($document, $channelTransports);
    }

    /**
     * Links each channel to the servers its messages are routed to — but only when
     * the document exposes more than one server. With a single server AsyncAPI
     * already implies every channel is available on it, so the link would be noise.
     * A channel that already declares its servers is left untouched.
     *
     * @param array<string, array<string, true>> $channelTransports channel key => routed transport set
     */
    private function associateServers(AsyncApiDocument $document, array $channelTransports): void
    {
        if (\count($document->servers) <= 1) {
            return;
        }

        foreach ($channelTransports as $key => $transports) {
            $channel = $document->channels[$key] ?? null;

            if ($channel === null || $channel->servers !== []) {
                continue;
            }

            foreach (array_keys($transports) as $transport) {
                if (isset($document->servers[$transport])) {
                    $channel->servers[] = Reference::to('#/servers/' . $transport);
                }
            }
        }
    }

    /**
     * Fills the message content type from the first routed transport that determines
     * one — never overriding a content type the message declared explicitly.
     *
     * @param list<string> $transports
     */
    private function applyContentType(Message $message, array $transports): void
    {
        if ($message->contentType !== null) {
            return;
        }

        foreach ($transports as $transport) {
            if (isset($this->contentTypes[$transport])) {
                $message->contentType = $this->contentTypes[$transport];

                return;
            }
        }
    }


    private function serverFromDsn(string $dsn): ?Server
    {
        $scheme = parse_url($dsn, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || !isset(self::PROTOCOLS[$scheme])) {
            return null;
        }

        $host = parse_url($dsn, \PHP_URL_HOST);

        if (!\is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($dsn, \PHP_URL_PORT);

        if (\is_int($port)) {
            $host .= ':' . $port;
        }

        return new Server(host: $host, protocol: self::PROTOCOLS[$scheme]);
    }
}
