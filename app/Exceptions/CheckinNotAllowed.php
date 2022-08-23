<?php

namespace App\Exceptions;

use Exception;

class CheckinNotAllowed extends Exception
{
    private $errorMessage;

    public function __construct($errorMessage = null)
    {
        $this->errorMessage = $errorMessage;

        parent::__construct($errorMessage);
    }

    public function __toString()
    {
        return is_null($this->errorMessage) ? 'A checkin is not allowed under these circumstances' : $this->errorMessage;
    }
}
