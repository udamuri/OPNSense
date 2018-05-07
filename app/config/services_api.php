<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

// Service definition for API
use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Session\Adapter\Files as SessionAdapter;
use OPNsense\Core\Config;
use OPNsense\Core\Routing;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

$di->set('view', function () use ($config) {
    // return a empty view
    $view = new View();
    $view->disable();
    return $view;
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->set('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
}, true);

/**
 * Start the session the first time some component request the session service
 */
$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();
    // Set session response cookie, unfortunalty we need to read the config here to determine if secure option is
    // a valid choice.
    $cnf = Config::getInstance();
    if ((string)$cnf->object()->system->webgui->protocol == 'https') {
        $secure = true;
    } else {
        $secure = false;
    }
    setcookie(session_name(), session_id(), null, '/', null, $secure, true);

    return $session;
});


$di->set('config', $config);


/**
 * Setup router
 */
$di->set('router', function () {

    $routing = new Routing(__DIR__."/../controllers/", "api");
    $routing->getRouter()->handle();
    return $routing->getRouter();
});

// exception handling
$di->get('eventsManager')->attach("dispatch:beforeException", function ($event, $dispatcher, $exception) {
    switch ($exception->getCode()) {
        case Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
            // send to error action on default index controller
            $dispatcher->forward(array(
                'controller' => 'index',
                'namespace' => '\OPNsense\Base',
                'action' => 'handleError',
                'params'     => array(
                    'message' =>  'controller ' . $dispatcher->getControllerClass() . ' not found',
                    'sender' => 'API'
                )
            ));
            return false;
        case Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
            // send to error action on default index controller
            $dispatcher->forward(array(
                'controller' => 'index',
                'namespace' => '\OPNsense\Base',
                'action' => 'handleError',
                'params'     => array(
                    'message' => 'action ' . $dispatcher->getActionName() . ' not found',
                    'sender' => 'API'
                )
            ));
            return false;
    }
});
$di->get('dispatcher')->setEventsManager($di->get('eventsManager'));