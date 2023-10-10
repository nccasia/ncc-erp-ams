<?php

namespace App\Repositories;

use App\Models\Location;

class LocationRepository
{
    private $location;

    public function __construct(Location $location)
    {
        $this->location = $location;
    }

    public function getLocationById($locationId)
    {
        return $this->location::find($locationId);
    }
}
