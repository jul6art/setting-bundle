<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps what it is given to dispatch, for a test to read.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
