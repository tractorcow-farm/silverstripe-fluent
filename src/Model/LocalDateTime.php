<?php

namespace TractorCow\Fluent\Model;

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
        $this->timezone = $timezone;
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
    public function setTimezone(?string $timezone): self
    {
        if ($timezone && !in_array($timezone, timezone_identifiers_list())) {
            throw new InvalidArgumentException("Invalid timezone {$timezone}");
        }
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @param null $locale
     * @param null $pattern
     * @param int $dateLength
     * @param int $timeLength
     * @return IntlDateFormatter
     */
    public function getCustomFormatter(
        $locale = null,
        $pattern = null,
        $dateLength = IntlDateFormatter::MEDIUM,
        $timeLength = IntlDateFormatter::MEDIUM
    ) {
        $formatter = parent::getCustomFormatter($locale, $pattern, $dateLength, $timeLength);
        $timezone = $this->getTimezone();
        if ($timezone) {
            $formatter->setTimeZone($timezone);
        }
        return $formatter;
    }

    /**
     * Get ISO local value
     *
     * @return string
     */
    public function getLocalValue(): string
    {
        return $this->Format(self::ISO_DATETIME);
    }
}
