<?php

/**
 * Queue job should stay pending and retry later (not marked completed).
 */
class QueueRescheduleException extends RuntimeException
{
    private $availableAtUtc;

    public function __construct($message, $availableAtUtc)
    {
        parent::__construct($message);
        $this->availableAtUtc = $availableAtUtc;
    }

    public function getAvailableAtUtc()
    {
        return $this->availableAtUtc;
    }
}
