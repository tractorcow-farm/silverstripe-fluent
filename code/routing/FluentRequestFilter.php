<?php

/**
 * Fluent initialisation filter to run during director init
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentRequestFilter implements RequestFilter
{
    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model)
    {
        // Ensures routes etc are setup
        // We need to inject the presented session temporarily, as there is no current controller set
        FluentSession::with_session($session, function () {
            Fluent::init();
        });
    }

    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
    }
}
