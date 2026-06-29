<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Attribute;

use Zeusi\AsyncApiBundle\Document\CorrelationId;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Document\Tag;

/**
 * Marks a DTO as a message documented by AsyncAPI.
 *
 * It carries the message-level documentation that lives best next to the code.
 * The attribute does NOT describe channel/operation metadata (those are shared
 * across messages). It only declares the message's *placement*: the coordinates
 * `(channel, action)` of where and how this message flows. `channel` is an
 * optional grouping key — null gives the message its own channel; messages
 * sharing a `channel` are grouped together. `action` defaults to `send`
 * (producer); under a transport-aware source the placement is derived instead.
 *
 * Each placement yields exactly one operation (one operation per
 * `(message, action)`), which is the opinionated subset of AsyncAPI this
 * generator derives from code.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsyncApiMessage
{
    /**
     * @param string|null $channel Placement grouping key; null gives the message its own channel.
     * @param OperationAction $action Placement direction; `send` (default) documents a published message.
     * @param string|null $name Message name. Defaults to the class short name when null.
     * @param string|null $title Human-friendly message title.
     * @param string|null $summary Short, one-line summary of the message (describes the data).
     * @param string|null $description Longer description (CommonMark allowed).
     * @param string|null $contentType Payload content type carried over the wire.
     * @param list<string|Tag> $tags Free-form tags; a plain string is shorthand for a Tag with that name.
     * @param ExternalDocumentation|null $externalDocs Link to external documentation for this message.
     * @param CorrelationId|null $correlationId Locates the correlation id in the message (a runtime expression).
     */
    public function __construct(
        public ?string $channel = null,
        public OperationAction $action = OperationAction::Send,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $contentType = null,
        public array $tags = [],
        public ?ExternalDocumentation $externalDocs = null,
        public ?CorrelationId $correlationId = null,
    ) {}
}
