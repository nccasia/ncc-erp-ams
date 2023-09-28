<?php

namespace App\Exceptions;

use Exception;

class AssetException extends Exception
{
    protected $status;
    protected $payload;
    protected $statusCode;

    public function __construct($errorMessage, $status, $statusCode, $payload = null)
    {
        parent::__construct($errorMessage);
        $this->status = $status;
        $this->payload = $payload;
        $this->statusCode = $statusCode;
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
        return $this->statusCode;
    }
}
