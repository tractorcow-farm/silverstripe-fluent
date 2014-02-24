<?php

// Zend_Cache temp directory setting
$_ENV['TMPDIR'] = TEMP_FOLDER; // for *nix
$_ENV['TMP'] = TEMP_FOLDER; // for Windows

// Ensures routes etc are setup
Fluent::init();
