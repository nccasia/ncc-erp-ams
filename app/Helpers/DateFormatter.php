<?php

namespace App\Helpers;

use DateTime;

class DateFormatter
{
    public static function formatDate($dateFrom, $dateTo)
    {
        $formattedDateFrom = new DateTime($dateFrom);
        $formattedDateFrom = $formattedDateFrom->format('Y-m-d H:i:s');

        $formattedDateTo = new DateTime($dateTo);
        $formattedDateTo = $formattedDateTo->setTime(23, 59, 59);
        $formattedDateTo = $formattedDateTo->format('Y-m-d H:i:s');

        return [
            'dateFrom' => $formattedDateFrom,
            'dateTo' => $formattedDateTo,
        ];
    }
}
