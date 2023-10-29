<?php

class DateTimeHelper
{
    /**
     * Calculate the duration between a start date and an end date based on the specified duration unit.
     *
     * @param string $startDate The start date in ISO8601 format (e.g., '2022-12-31T14:59:00Z').
     * @param string|null $endDate The end date in ISO8601 format (or null if no end date).
     * @param string $durationUnit The unit of duration ('HOURS', 'DAYS', or 'WEEKS', default is 'DAYS').
     *
     * @return float|null The calculated duration in the specified unit (null if invalid input).
     * @throws Exception
     */
    public function calculateDuration($startDate, $endDate, $durationUnit = 'DAYS')
    {
        // Check if start_date is a valid ISO 8601 date
        if (!preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $startDate)) {
            return null; // Invalid start_date
        }

        // If end_date is null, duration is also null
        if ($endDate === null) {
            return null;
        }

        // Check if end_date is a valid ISO 8601 date and is later than start_date
        if (!preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $endDate)) {
            return null; // Invalid end_date
        }

        $startDatetime = new DateTime($startDate);
        $endDatetime = new DateTime($endDate);

        if ($startDatetime >= $endDatetime) {
            return null; // end_date is not later than start_date
        }

        // date interval
        $diff = $startDatetime->diff($endDatetime);

        // Calculate the duration based on the provided durationUnit
        switch (strtoupper($durationUnit)) {
            case 'HOURS':
                $duration = $diff->days * 24 + $diff->h;
                break;
            case 'DAYS':
                $duration = $diff->days + $diff->h / 24;
                break;
            case 'WEEKS':
                $duration = $diff->days / 7 + $diff->h/ 7 / 24;
                break;
            default:
                return null; // Invalid durationUnit
        }

        // Round the duration to the nearest whole hour
        $duration = round($duration, 2);

        return $duration;
    }


    /**
     * Converts a date format.
     *
     * @param string|null $date The date string to convert.
     * @param string $toFormat
     * @param string $fromFormat
     * @return string|null The converted date string.
     */
    public function convertDateFormat($date, $toFormat = 'Y-m-d H:i:s', $fromFormat = 'Y-m-d\TH:i:sP')
    {
        $dateTime = DateTime::createFromFormat($fromFormat, (string)$date);

        if (!$dateTime) {
            return null;
        }

        return $dateTime->format($toFormat);
    }
}