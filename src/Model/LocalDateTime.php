<?php

namespace TractorCow\Fluent\Model;

use DateTime;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use InvalidArgumentException;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Stores dates in the same timezone as DBDateTime, but will format them
 * based on the timezone of another locale.
 *
 * Note: getValue() date will NOT be in the specified timezone. Only dates formatted via ->Format()
 * or ->getLocalValue() will be converted as expected.
 */
class LocalDateTime extends DBDatetime
{
    protected $timezone = null;

    public function __construct($name = null, $options = [], $timezone = null)
    {
        $this->setTimezone($timezone);
        parent::__construct($name, $options);
    }

    /**
     * @return string|null
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Assign timezone
     *
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone(?string $timezone): LocalDateTime
    {
        if ($timezone && !in_array($timezone, timezone_identifiers_list())) {
            throw new InvalidArgumentException("Invalid timezone {$timezone}");
        }
        $this->timezone = $timezone;
        return $this;
    }

    public function getCustomFormatter(
        $locale = null,
        $pattern = null,
        $dateLength = IntlDateFormatter::MEDIUM,
        $timeLength = IntlDateFormatter::MEDIUM
    ) {
        $formatter = parent::getCustomFormatter($locale, $pattern, $dateLength, $timeLength);
        $timezone = $this->getTimezone();
        if ($timezone) {
            $formatter->setTimezone($timezone);
        }
        return $formatter;
    }

    /**
     * Assign value in server timezone
     *
     * @param mixed $value
     * @param string $record
     * @param bool $markChanged
     * @return $this
     */
    public function setValue($value, $record = null, $markChanged = true)
    {
        // Disable timezone when setting value (always stored in server timezone)
        $timezone = $this->getTimezone();
        try {
            $this->setTimezone(null);
            return parent::setValue($value, $record, $markChanged);
        } finally {
            $this->setTimezone($timezone);
        }
    }

    /**
     * Get ISO local value
     *
     * @return string
     */
    public function getLocalValue(): string
    {
        return $this->Format(LocalDateTime::ISO_DATETIME);
    }

    /** Assign a value that's already in the current locale
     *
     * @param string $value
     * @param string $timezone Timezone to assign to this date (defaults to current assigned timezone)
     * @return $this
     * @throws Exception
     */
    public function setLocalValue($value, $timezone = null)
    {
        // If assigning timezone, set first
        if (func_num_args() >= 2) {
            $this->setTimezone($timezone);
        }

        // Empty values
        if (empty($value)) {
            $this->value = null;
            return $this;
        }

        // Parse from local timezone
        $timezone = $this->getTimezone() ?: date_default_timezone_get();
        $localTime = new DateTime($value, new DateTimeZone($timezone));
        $localTime->setTimezone(new DateTimeZone(date_default_timezone_get())); // Store in server timezone
        $serverValue = $localTime->format('Y-m-d H:i:s');
        $this->value = $serverValue;
        return $this;
    }
}
