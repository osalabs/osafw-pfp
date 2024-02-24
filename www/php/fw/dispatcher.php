<?php

/*
Dispatcher/router class - used for call funcs/methods by URL
by default RESTful approach assumed

Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com

SAMPLE USAGE:

$ROUTES = array(
''      => 'index', //default route
'401'   => 'baseview::page401', //default 401 page (Unauthorized)
'404'   => 'baseview::page404', //default 404 page (Not Found)
'/aaa/bbb/ccc'  => 'class',         #class to work
'/aaa/bbb/ccc'  => 'class::method', #particular method to work with uri
'/aaa/bbb/ccc'  => '::method',      #global function to work with uri
'/admin/user'  => '/auser',         #"internal redirect"
'MODULE'  => 'CLASS',               #resource replacing - process MODULE with class CLASS
'^/regexp/(\d+)'=> 'class',         #match uri by regexp TODO???
);
# MODULE - chars allowed just a-z and 0-9, case insensitive


Dispatcher::go($ROUTES);



RESTful interface:
GET   /controller                 Index
POST  /controller                 Save     (save new record - Create)
PUT   /controller                 SaveMulti (update multiple records)
GET   /controller/new             ShowForm (show new form - ShowNew)
GET   /controller/{id}[.format]   Show     (show in format - not for editing)
GET   /controller/{id}/edit       ShowForm (show edit form - ShowEdit)
GET   /controller/{id}/delete     ShowDelete
POST/PUT  /controller/{id}        Save     (save changes to exisitng record - Update    Note:$_POST should contain data
POST/DELETE  /controller/{id}     Delete    Note:$_POST should NOT contain any data

/controller/(Action)              Action    call for arbitrary action from the controller

 */

class Dispatcher {
    const def_controller = 'Home';
    const def_action     = 'Index';
    public static $METHOD_ALLOWED = array(
        'GET'    => true,
        'POST'   => true,
        'PUT'    => true,
        'DELETE' => true,
    );

    # public static $table_name = '';
    public static $REST2METHOD_MAP = array(
        'view'        => 'Show',
        'create'      => 'Save',
        'update'      => 'Save',
        'updatemulti' => 'SaveMulti',
        'delete'      => 'Delete',
        'showdelete'  => 'ShowDelete',
        'list'        => 'Index',
        'new'         => 'ShowForm',
        'edit'        => 'ShowForm',
        'options'     => 'Options',
    );

    public static $HTTP_CODE = array(
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal server error',
    );

    public $ROUTES = array();
    public $ROOT_URL;
    public $ROUTE_PREFIXES; #array('/Admin', '/My', ...)
    public $request_url; #last url processed by uriToRoute

    # leave just allowed chars in string - for routers: controller, action
    # IN: raw name of the controller or action
    # OUT: normalized name with only allowed chars
    public static function RouteFixChars($str) {
        return preg_replace("/[^A-Za-z0-9_-]+/", "", $str);
    }

    public function __construct($ROUTES = array(), $ROOT_URL = '', $ROUTE_PREFIXES = array()) {
        $this->ROUTES         = $ROUTES;
        $this->ROOT_URL       = $ROOT_URL;
        $this->ROUTE_PREFIXES = $ROUTE_PREFIXES;
    }

    # get route for method/uri with defaults
    public function getRoute() {
        $method = $_SERVER['REQUEST_METHOD']; #ex: POST, GET
        $uri    = $_SERVER['REQUEST_URI']; #ex: /add/post/12390/alksjdla?qoeewlkj

        $route = $this->uriToRoute($method, $uri, $this->ROUTES);
        #logger("ROUTE found:", $route);
        logger('TRACE', "ROUTE: " . $route->method . ' ' . $route->controller . '.' . $route->action . ' id=' . $route->id . ' ' . $route->action_more);

        return $route;
    }

    public function runController($controller, $action, $aparams = array()) {
        if (!$controller) {
            throw new NoControllerException();
        }

        if (!$action) {
            throw new NoClassMethodException();
        }
        return $this->callClassMethod($controller . 'Controller', $action . 'Action', $aparams);
    }

    # call functions and methods
    # for classes - creates object instance first
    # IN: class name, method, params
    # OUT: throws NoControllerException/NoClassMethodException if no class/method exists
    public function callClassMethod($class_name, $method, $aparams = array()) {
        logger('TRACE', "calling $class_name->$method", $aparams);
        try {
            if ($class_name) {
                if (!class_exists($class_name)) {
                    throw new NoControllerException();
                }

                $obj  = new $class_name;
                $func = array($obj, $method);
            } else {
                $func = $method;
            }
            if (!is_callable($func)) {
                throw new NoClassMethodException();
            }

            return call_user_func_array($func, $aparams);
        } catch (NoControllerException $ex) {
            throw $ex;
        } catch (NoClassMethodException $ex) {
            throw $ex;
        }
    }

    public function getRouteDefaultAction($controller) {
        $class_name = $controller . 'Controller';
        if (!class_exists($class_name)) {
            throw new NoControllerException();
        }

        return $class_name::route_default_action;
    }

    public function getRouteAccessLevel($controller) {
        $class_name = $controller . 'Controller';
        if (!class_exists($class_name)) {
            throw new NoControllerException();
        }

        return $class_name::access_level;
    }

    #IN: controller::action
    #OUT: array(controller, action)
    public function splitRoute($route) {
        list($controller, $action) = explode('::', $route);
        if (!$controller) {
            #TODO - global
        } elseif (!$action) {
            $action = "Index";
        }

        return array($controller, $action);
    }

    # string to route
    # ususally string is from $ROUTES
    public function str2route($str) {
        list($controller, $action) = $this->splitRoute($str);

        #TODO handle controller prefix?

        $result              = new stdClass;
        $result->method      = 'GET';
        $result->prefix      = '';
        $result->controller  = $controller;
        $result->action      = $action;
        $result->id          = '';
        $result->action_more = '';
        $result->format      = 'html';
        $result->params      = array();

        return $result;
    }

    public function detectOperation($method, $id, $action_more) {
        //$oper= $uri1=='new'?'new':( isset($uri2)?$uri2:'' );
        $result = '';

        if ($method == 'GET') {
            if ($action_more == 'new') {
                $result = 'new';
            } elseif ($id > '' && $action_more == 'edit') {
                $result = 'edit';
            } elseif ($id > '' && $action_more == 'delete') {
                $result = 'showdelete';
            } elseif ($id > '') {
                $result = 'view';
            } else {
                $result = 'list';
            }
        } elseif ($method == 'POST') {
            if ($id > '') {
                if (count($_POST) > 0) {
                    $result = 'update';
                } else {
                    $result = 'delete';
                }
            } else {
                $result = 'create';
            }
        } elseif ($method == 'PUT') {
            if ($id > '') {
                $result = 'update';
            } else {
                $result = 'updatemulti';
            }

        } elseif ($method == 'DELETE' && $id > '') {
            $result = 'delete';
        } elseif ($method == 'OPTIONS') {
            $result = 'options';
        }

        if (!$result) {
            #just respond with 405 and exit immediately
            header("HTTP/1.0 405 Method Not Allowed", true, 405);
            header("Allow: GET, POST, PUT, DELETE, OPTIONS"); #instruct client what methods are allowed
            exit;
            #throw new Exception('Unsupported REST params combination'); #405 Method Not Allowed
        }

        return $result;
    }

    # for a given method, uri and routes return controller/action
    # usually:
    #   $method=$_SERVER['REQUEST_METHOD'];    #ex: POST, GET
    #   $uri=$_SERVER['REQUEST_URI'];         #ex: /add/post/12390/alksjdla?qoeewlkj
    # IN: method, uri, routes
    # OUT: hash:
    #       method
    #       prefix
    #       controller
    #       action
    #       id
    #       action_more
    #       format
    #       params
    public function uriToRoute($method, $uri, $ROUTES) {
        $root_url = $this->ROOT_URL;

        $result = array();

        if ($root_url) {
            $uri = preg_replace("/^" . preg_quote($root_url, '/') . "/", '', $uri); #remove root_url if any
        }

        $uri               = preg_replace("/\?.*/", '', $uri); #remove query if any , ex. /add/post/12390/alksjdla
        $uri               = preg_replace("!/$!", '', $uri); #remove last /
        $uri               = str_replace(["%28", "%29"], ["(", ")"], $uri); # mobile chrome posts urls like /xxx/(Save) to /xxx/%28Save%29 for some reason, need to un-escape
        $this->request_url = $uri;

        #check if method override exits
        $tmp_method_check = $_POST['_method'] ?? '';
        if ($tmp_method_check > '' && array_key_exists($tmp_method_check, $this::$METHOD_ALLOWED)) {
            $method = $tmp_method_check;
        }

        #prefixes (such as /Admin /Member - setup in Config.php::$ROUTE_PREFIXES) - or do via $ROUTES?
        $controller_prefix = '';
        foreach ($this->ROUTE_PREFIXES as $prefix) {
            $qprefix = preg_quote($prefix, '/');
            if (preg_match('/^' . $qprefix . '/i', $uri)) {
                $controller_prefix = $this->RouteFixChars($prefix);
                $uri               = preg_replace('/^' . $qprefix . '/', '', $uri);
                break;
            }
        }

        $cur_controller  = 'Home';
        $cur_action      = 'Index';
        $cur_id          = '';
        $cur_action_more = '';
        $cur_format      = 'html';
        $cur_aparams     = array(); #stores additional resourse/id  i.e. /user/999/notes/888/attachments/777

        #process ROUTES to find matching routes
        $is_route_found = 0;
        if (count($ROUTES)) {
            while (!$is_route_found) {
                if (array_key_exists($uri, $ROUTES)) {
                    if ($ROUTES[$uri][0] == '/') {
                        #if started from / - this is redirect url
                        $uri = $ROUTES[$uri];
                    } else {
                        #otherwise - it's a direct class-method to call
                        list($cur_controller, $cur_action) = $this->splitRoute($ROUTES[$uri]);
                        $is_route_found = 1;
                        break;
                    }
                } else {
                    break;
                }
            }

            if (!$is_route_found) { #if no route found - try to process regexp routes
                #TODO
            }
        }

        if (!$is_route_found) {
            #if no special ROUTES found - try to detect default RESTful URLs
            $RX_CONTROLLER = '[^/]+';
            $RX_ACTION     = '[\d\w_-]+';

            #get RESTful URI
            $is_match = preg_match("!^/($RX_CONTROLLER)(?:/(new|\.\w+)|/($RX_ACTION)(?:\.(\w+))?(?:/(edit|delete))?)?/?$!i", $uri, $m); #one controller only, id is "Alphanum_-"

            #logger('TRACE', "$method $uri => REST is_match=$is_match");
            #logger($m);

            if ($is_match) {
                #foreach ($m[0] as $vv) { #go thru resourses
                $cur_controller = $this->RouteFixChars($m[1]);
                if (!strlen($cur_controller)) {
                    throw new Exception("Wrong request", 1);
                }

                #TODO - capitalize controller name or not? or site-wide option?

                $cur_id          = $m[3] ?? '';
                $cur_format      = $m[4] ?? '';
                $cur_action_more = $m[5] ?? '';

                $tmp_check_suffix = $m[2] ?? ''; #could contain "new" or ".format"
                if ($tmp_check_suffix > '') {
                    if ($tmp_check_suffix == 'new') {
                        $cur_action_more = 'new';
                    } else {
                        $cur_format = substr($tmp_check_suffix, 1);
                    }
                }

                $rest_oper  = $this->detectOperation($method, $cur_id, $cur_action_more);
                $cur_action = self::$REST2METHOD_MAP[$rest_oper];

                #check if there is mapping module => class present and replace dest class
                if (array_key_exists($cur_controller, $ROUTES)) {
                    $cur_controller = $ROUTES[$cur_controller];
                }
            } else {
                #otherwise detect controller/action/id.format/more_action
                if ($uri) {
                    $arr = explode("/", $uri);
                    #0 element is empty string, not needed
                    $cur_controller  = $arr[1] ?? '';
                    $cur_action      = $arr[2] ?? '';
                    $cur_id          = $arr[3] ?? '';
                    $cur_action_more = $arr[4] ?? '';
                    $cur_controller  = $this->RouteFixChars($cur_controller);
                }

                #call default method $ROUTES['']
                #@list($cur_controller,$cur_action)=$this->splitRoute($ROUTES['']);
                #logger('TRACE', "DEFAULT call $cur_controller->$cur_action()\n");
            }
        }

        $cur_controller = $controller_prefix . $cur_controller;
        $cur_action     = self::RouteFixChars($cur_action);
        if (!strlen($cur_action)) {
            $cur_action = 'Index';
        }

        array_unshift($cur_aparams, $cur_id); #first param always is id

        $result              = new stdClass;
        $result->method      = $method;
        $result->prefix      = $controller_prefix;
        $result->controller  = $cur_controller;
        $result->action      = $cur_action;
        $result->id          = $cur_id;
        $result->action_more = $cur_action_more;
        $result->format      = $cur_format;
        $result->params      = $cur_aparams;

        logger('TRACE', 'ROUTER RESULT=', $result);
        return $result;
    }
}
