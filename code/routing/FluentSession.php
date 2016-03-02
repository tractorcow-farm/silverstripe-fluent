<?php

/**
 * Because there is not always an active controller (such as during pre-request filters)
 * FluentSession will ensure the correct session object is injected as necessary
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentSession extends Session
{
    /**
     * Current session
     *
     * @var Session
     */
    protected static $old_session = null;

    /**
     * Allows session to be temporarily injected into default_session prior to
     * the existence of a controller
     */
    public static function with_session(Session $session, $callback)
    {
        self::$old_session = self::$default_session;
        self::$default_session = $session;
        try {
            $callback();
        } catch (Exception $ex) {
            self::$default_session = self::$old_session;
            throw $ex;
        }
        self::$default_session = self::$old_session;
    }
}
