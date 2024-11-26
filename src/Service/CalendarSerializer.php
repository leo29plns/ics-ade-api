<?php

namespace App\Service;

use ICal\ICal;

class CalendarSerializer
{
    public function __construct(
      private ICal $ical,
      private ?array $customProperties
    ) {}

    public function serialize(): array
    {
        $calendar = [];

        $calendar = [
            'eventsCount' => $this->ical->eventCount,
            'timeZone' => $this->ical->defaultTimeZone,
        ];

        foreach ($this->ical->events() as $event) {
            $uid = $event->uid;

            $calendar['events'][$uid] = (array) $event;
            $calendar['events'][$uid]['customProperties'] = $this->customProperties[$uid];
        }

        return $calendar;
    }
}
