<?php

class ApiException extends Exception
{
    public $status;
    public $details;

    public function __construct(string $message, int $status = 400, $details = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->details = $details;
    }
}
