<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

/**
 * A message with NO #[AsyncApiMessage] attribute.
 *
 * It is documented purely because it is routed in framework.messenger.routing and
 * the Messenger routing-discovery is enabled — its payload is still derived from
 * the typed properties.
 */
final class PaymentCaptured
{
    public function __construct(
        public readonly string $paymentId,
        public readonly int $amountCents,
        public readonly string $currency,
    ) {}
}
