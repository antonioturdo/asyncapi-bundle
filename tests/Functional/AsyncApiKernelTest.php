<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Zeusi\AsyncApiBundle\Tests\Fixtures\App\TestKernel;

final class AsyncApiKernelTest extends TestCase
{
    public function testItGeneratesTheDocumentFromAttributedDtosOverHttp(): void
    {
        $kernel = new TestKernel(uniqid('t', true), false);
        $kernel->boot();

        $response = $kernel->handle(Request::create('/asyncapi.json'));
        $decoded = json_decode((string) $response->getContent(), true);

        $kernel->shutdown();

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($decoded);
        self::assertSame('3.1.0', $decoded['asyncapi'] ?? null);
        self::assertSame('Demo Events', $decoded['info']['title'] ?? null);

        // Discovery + assembly wired end to end: the attributed DTOs produced
        // channels (with the two order messages grouped) and one operation each.
        $channels = $decoded['channels'] ?? null;
        self::assertIsArray($channels);
        self::assertArrayHasKey('bookings', $channels);

        $bookings = $channels['bookings'];
        self::assertIsArray($bookings);
        $bookingMessages = $bookings['messages'] ?? null;
        self::assertIsArray($bookingMessages);
        // The channel references the messages from the components catalog.
        self::assertSame(['$ref' => '#/components/messages/TripBooked'], $bookingMessages['TripBooked'] ?? null);
        self::assertArrayHasKey('TripCancelled', $bookingMessages);

        $operations = $decoded['operations'] ?? null;
        self::assertIsArray($operations);
        self::assertArrayHasKey('sendTripBooked', $operations);

        // The message (with payload derived via json-schema-extractor) lives in components.
        $componentMessages = $decoded['components']['messages'] ?? null;
        self::assertIsArray($componentMessages);
        $tripBooked = $componentMessages['TripBooked'] ?? null;
        self::assertIsArray($tripBooked);
        $payload = $tripBooked['payload'] ?? null;
        self::assertIsArray($payload);
        self::assertSame('object', $payload['type'] ?? null);
        $properties = $payload['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertArrayHasKey('bookingId', $properties);
        self::assertArrayHasKey('travelers', $properties);

        // A separate message in its own channel, with a send operation.
        self::assertArrayHasKey('travels', $channels);
        self::assertArrayHasKey('sendTripCompleted', $operations);

        // Its payload is derived from the DTO too: scalar fields and the list of sights.
        $tripCompleted = $componentMessages['TripCompleted'] ?? null;
        self::assertIsArray($tripCompleted);
        $tripProperties = $tripCompleted['payload']['properties'] ?? null;
        self::assertIsArray($tripProperties);
        self::assertArrayHasKey('country', $tripProperties);
        self::assertArrayHasKey('city', $tripProperties);
        self::assertSame('array', $tripProperties['sights']['type'] ?? null);
    }

    public function testValidationConstraintsAreReflectedInThePayloadSchema(): void
    {
        $kernel = new TestKernel(uniqid('t', true), false);
        $kernel->boot();

        $decoded = json_decode((string) $kernel->handle(Request::create('/asyncapi.json'))->getContent(), true);

        $kernel->shutdown();

        self::assertIsArray($decoded);
        // TripReviewed's `comment` carries #[Assert\NotBlank] + #[Assert\Length].
        $payload = $decoded['components']['messages']['TripReviewed']['payload'] ?? null;
        self::assertIsArray($payload);

        // The Symfony Validator enricher (wired only when the validator is present)
        // reflected the constraints into the schema.
        self::assertSame(3, $payload['properties']['comment']['minLength'] ?? null);
        self::assertSame(280, $payload['properties']['comment']['maxLength'] ?? null);
        self::assertContains('comment', $payload['required'] ?? []);
    }

    public function testMessengerTransportsAreAddedAsServers(): void
    {
        $kernel = new TestKernel(uniqid('t', true), false);
        $kernel->boot();

        $decoded = json_decode((string) $kernel->handle(Request::create('/asyncapi.json'))->getContent(), true);

        $kernel->shutdown();

        self::assertIsArray($decoded);
        // TripBooked is routed to the 'events' (amqp) transport → its server is added.
        self::assertSame(
            ['host' => 'broker.demo.test:5672', 'protocol' => 'amqp'],
            $decoded['servers']['events'] ?? null,
        );
        // The internal sync transport is not a broker → no server.
        self::assertArrayNotHasKey('internal', $decoded['servers'] ?? []);
    }

    public function testItDiscoversRoutedMessagesWithoutAnAttribute(): void
    {
        $kernel = new TestKernel(uniqid('t', true), false);
        $kernel->boot();

        $decoded = json_decode((string) $kernel->handle(Request::create('/asyncapi.json'))->getContent(), true);

        $kernel->shutdown();

        self::assertIsArray($decoded);
        // PaymentCaptured carries NO #[AsyncApiMessage]; it is documented purely
        // because it is routed in framework.messenger and routing-discovery is on.
        $message = $decoded['components']['messages']['PaymentCaptured'] ?? null;
        self::assertIsArray($message);
        self::assertArrayHasKey('PaymentCaptured', $decoded['channels'] ?? []);

        // Its payload is still derived from the typed constructor properties.
        $properties = $message['payload']['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertArrayHasKey('paymentId', $properties);
        self::assertArrayHasKey('amountCents', $properties);
    }

    public function testItRendersTheHtmlUiWithTheConfiguredWebComponent(): void
    {
        $kernel = new TestKernel(uniqid('t', true), false);
        $kernel->boot();

        // The UI route resolves the bundle's templates/ path (registered via
        // getPath()) and renders the AsyncAPI web component.
        $response = $kernel->handle(Request::create('/asyncapi'));

        $kernel->shutdown();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $html = (string) $response->getContent();
        // The web component is rendered, pointed at the JSON document endpoint,
        // with the configured stylesheet wired in.
        self::assertStringContainsString('<asyncapi-component', $html);
        self::assertStringContainsString('/asyncapi.json', $html);
        self::assertStringContainsString('react-component', $html);
    }
}
