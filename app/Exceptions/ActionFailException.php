<?php

namespace App\Exceptions;

use Exception;

class ActionFailException extends Exception
{
    private $status;
    private $payload;
    protected $message;
    private $status_code;
    public function __construct($status, $payload, $message, $status_code)
    {
        $this->status = $status;
        $this->payload = $payload;
        $this->message = $message;
        $this->status_code = $status_code;

        parent::__construct($message);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getStatusCode()
    {
        return $this->status_code;
    }
}
