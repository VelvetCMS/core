<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Events;

use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Tests\Support\TestCase;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = new EventDispatcher();
    }

    public function testListenersExecuteInRegistrationOrder(): void
    {
        $executionOrder = [];

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'first';
        });

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'second';
        });

        $this->events->listen('test.event', function ($payload) use (&$executionOrder) {
            $executionOrder[] = 'third';
        });

        $this->events->dispatch('test.event', null);

        $this->assertSame(['first', 'second', 'third'], $executionOrder);
    }

    public function testDispatchDoesNotReturnPayload(): void
    {
        $this->events->listen('transform.number', function ($value) {
            return $value * 2;
        });

        $result = $this->events->dispatch('transform.number', 5);

        $this->assertNull($result);
    }

    public function testListenersCannotMutateArrayPayload(): void
    {
        $this->events->listen('page.loading', function ($page) {
            $page['cached'] = true;
            return $page;
        });

        $payload = ['slug' => 'test'];
        $this->events->dispatch('page.loading', $payload);

        $this->assertArrayNotHasKey('cached', $payload);
    }

    public function testListenersCanMutateObjectPayloadByReference(): void
    {
        $this->events->listen('user.creating', function ($user) {
            $user->validated = true;
        });

        $user = new \stdClass();
        $user->name = 'John';

        $this->events->dispatch('user.creating', $user);

        $this->assertTrue($user->validated);
    }
}
