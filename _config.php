<?php

use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DB;

Deprecation::notification_version('4.0.0', 'tractorcow/silverstripe-fluent');

// Credit to https://github.com/sunnysideup thank you for the mysql fix
DB::query("SET SESSION sql_mode='REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE';");
