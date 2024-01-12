<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBDatetime;
use TractorCow\Fluent\Model\LocalDateTime;
use TractorCow\Fluent\Model\Locale;

/**
 * @extends Extension<DBDatetime>
 */
class FluentDateTimeExtension extends Extension
{
    /**
     * Convert datetime object to time in current timezone (timezone of current locale, not timezone of server)
     *
     * @return LocalDateTime
     */
    public function getLocalTime(): LocalDateTime
    {
        $locale = Locale::getCurrentLocale();
        $timezone = $locale ? $locale->Timezone : null;

        return LocalDateTime::create($this->owner->getName(), $this->owner->getOptions())
            ->setValue($this->owner->getValue())
            ->setTimezone($timezone);
    }
}
