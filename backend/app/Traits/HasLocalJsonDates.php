<?php

namespace App\Traits;

use DateTimeInterface;

/**
 * Add `use HasLocalJsonDates;` to any model that has datetime-cast
 * columns (start_time, end_time, checkin_time, etc).
 *
 * Why this exists: Illuminate\Database\Eloquent\Concerns\HasAttributes::
 * serializeDate() converts every datetime-cast Carbon attribute to UTC
 * and appends "Z" whenever the model is turned into JSON — completely
 * independent of config('app.timezone'). That config only changes what
 * PHP's default timezone / Carbon::now() resolve to; it has zero effect
 * on this conversion.
 *
 * Symptom without this: a booking made for 14:00 Asia/Kuala_Lumpur is
 * stored and loaded correctly as 14:00, then silently rewritten to
 * "...T06:00:00.000000Z" (14:00 - 8 hours) the moment it's serialized
 * into an API response — an exact "8 hours behind" bug that has nothing
 * to do with how the frontend parses it.
 *
 * This override just formats the date as plain local wall-clock time
 * with no UTC conversion and no "Z"/offset suffix, so the digits that
 * leave the API are the same digits that were entered.
 */
trait HasLocalJsonDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
