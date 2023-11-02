<?php

namespace App\Exceptions;

use Exception;
use GuzzleHttp\Exception\RequestException;

class W2Exception extends Exception
{
    private $status;
    private $payload;
    protected $message;
    private $status_code;
    public function __construct(string $message, int $status_code = 400)
    {
        $this->status = "error";
        $this->payload = null;
        $this->message = $message ?? "";
        $this->status_code = $status_code;
        parent::__construct($message ?? "");
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
