# marko/broadcasting-amphp

Native async SSE broadcasting server --- a self-hosted, PHP-only realtime server on amphp that holds thousands of Server-Sent Events connections per process and fans events out through `marko/pubsub`.

## Installation

```bash
composer require marko/broadcasting-amphp
```

This installs `marko/broadcasting`, `marko/amphp` and `marko/pubsub`. You also need a pub/sub driver such as `marko/pubsub-redis`, and a log driver.

## Quick Example

```php
use Marko\Broadcasting\PrivateChannel;

// Publish (BroadcasterInterface is bound to AmphpBroadcaster): one pub/sub message, no HTTP call
$broadcaster->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);

// Give the browser an EventSource URL; private channels get a signed token
$url = $amphpSubscriberToken->streamUrl(['shows.42', new PrivateChannel('orders.7')], $authManager->user());
```

```bash
marko broadcasting:serve --port=8085
```

## Documentation

Full usage, API reference, and examples: [marko/broadcasting-amphp](https://marko.build/docs/packages/broadcasting-amphp/)
