<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Correlation ID object: how to identify/correlate messages.
 *
 * `location` (a runtime expression) is the only field the specification requires.
 */
final class CorrelationId
{
    use HasExtensions;

    public function __construct(
        public string $location,
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['location' => $this->location];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $this->withExtensions($data);
    }
}
