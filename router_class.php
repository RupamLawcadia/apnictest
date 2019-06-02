<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class router_class {

    public function __construct($routes, $CO) {
        $this->api_prefix = $routes->api_prefix;

        $this->primary_token_expiery_in_seconds = (60 * 60 * 60);

        $this->secondary_token_expiery_in_seconds = (60 * 30);

        //Get Entire routing object
        $this->routes = $routes->routes;

        //This Request type - GET/POST/PUT/DELETE
        $this->request_type = $_SERVER['REQUEST_METHOD'];

        //This URI
        $this->URI = $_SERVER['REQUEST_URI'];

        //Get current Route Path
        /**
         * NGINX proxy may return different 'REQUEST_URI's based on your proxy methods.
         * Eg - URL - https://dv1.lawcadia.com/api/v1/auth/sign_in
         * Proxy URL - https://dv1.lawcadia.com/api/v1/
         * Your final REQUEST_URI is '/auth/sign_in'
         */
        $this->route_path = str_replace($this->api_prefix, '', $this->URI);

        //Get all the loaded classes
        $this->CO = $CO;

        //Current Request Time
        $this->request_time = $_SERVER['REQUEST_TIME'];
    }

    /**
     * Read headers from user request
     * @return array List of request headers 
     */
    private function get_all_headers() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * Handle preflight request. Should be disable in a Production Environment
     */
    private function cors() {
        // Access-Control headers are received during OPTIONS requests
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        exit(0);
    }

    /**
     * Validate the user Auth-Token
     * @return type
     */
    private function authenticate() {

        $headers = $this->get_all_headers();

        if (isset($headers['Auth-Token'])) {
            //Time
            $time_now = time();
            //Decrypt the Auth token
            $token_data_object = json_decode($this->CO->middleware_class->decrypt_auth_token($headers['Auth-Token'], TOKEN_SALT));

            //Token object validation

            if (!isset($token_data_object->created) || ($time_now - $token_data_object->created) > $this->primary_token_expiery_in_seconds) {
                $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Received an expired token');
            }

            //Check secondary token first if it is enebled
            if (ENABLE_SECONDARY_TOKEN) {
                if (isset($_COOKIE['Auth-Token-2'])) {
                    $secondary_token_data = json_decode($this->CO->middleware_class->decrypt_auth_token($_COOKIE['Auth-Token-2'], TOKEN_SALT));
                    if (($time_now - $secondary_token_data->created) > $this->secondary_token_expiery_in_seconds) {
                        $this->CO->errors_class->error_response(401, 'token_expired', 'Session expired. Please login again', 'error', 'Secondary Authentication Failed - Token Expired');
                    } elseif ($secondary_token_data->user_id != $token_data_object->user_id) {
                        $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Secondary Authentication Failed - Did not receive a valid secondary token');
                    }
                } else {
                    $this->CO->errors_class->error_response(401, 'token_expired', 'Session expired. Please login again', 'error', 'Cannot read secondary token cookie');
                }
            }

            if (isset($token_data_object->user_id) && $token_data_object->user_id > 0 && filter_var($token_data_object->email, FILTER_VALIDATE_EMAIL)) {

                //Check for valid role
                if (!isset($token_data_object->role_id) || $token_data_object->role_id < 1) {
                    $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Login information is correct but no user role assigned');
                    //Check for valid user information
                } else {
                    return $token_data_object;
                }
            } else {
                $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Invalid information in the user token data');
            }
        } else {
            $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Auth-Token header is missing from the request');
        }
    }

    /**
     * Authenticate Lawcadia public API endpoints
     * @return type
     */
    private function public_api_authenticate() {
        $headers = $this->get_all_headers();
        if (!isset($headers['Public-Api-Token'])) {
            $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Token Header is required : Public-Api-Token');
        }

        $token_data_object = json_decode($this->CO->middleware_class->decrypt_auth_token($headers['Public-Api-Token'], TOKEN_SALT));
        if (isset($token_data_object->company_id) && $token_data_object->company_id > 0 && isset($token_data_object->token_type) && $token_data_object->token_type === 'public_api' && isset($token_data_object->token_key) && $token_data_object->token_key !== '') {
            //Confirm Key Validity
            $keys = $this->CO->database_connector->find_from_id('companies', $token_data_object->company_id, array('public_api_salt'));
            if ($keys['public_api_salt'] !== $token_data_object->token_key) {
                $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Invalid Public API Token');
            }
            return $token_data_object;
        } else {
            $this->CO->errors_class->error_response(401, 'token_invalid', 'Authentication Failed', 'error', 'Invalid Public API Token');
        }
    }

    public function map_routes() {

        //Handle Option requests (Do not use in PRODUCTION)
        if ($this->request_type === 'OPTIONS') {
            $this->cors();
            exit;
        }

        //Route Data Object. This objejct can read inside route destination file
        $route_data['REQUEST_TIME'] = $this->request_time;

        //Explode current route (Apply array_filter to deal with taling slash(s))
        $explode_this_route = array_filter(explode('/', $this->route_path));

        //This route item count
        $this_route_count = count($explode_this_route);

        foreach ($this->routes as $key => $single_route_object) {

            //Set new empty array object
            $route_data = array();

            $this_route_fail = FALSE;
            $explode_route = explode('/', $key);


            if ($this_route_count === count($explode_route)) {
                //Finding validity of this route (This is part of the main)
                for ($x = 0; $x < $this_route_count; $x++) {
                    if (isset($explode_route[$x]) && isset($explode_this_route[$x]) && $explode_route[$x] == $explode_this_route[$x]) {
                        
                    } elseif ($explode_route[$x][0] == ':') {
                        $variiables_key = str_replace(':', '', $explode_route[$x]);
                        //filter Input for potentially insecure inputs and Map it
                        $route_data['get_vars'][$variiables_key] = filter_var(urldecode($explode_this_route[$x]), FILTER_SANITIZE_STRING);
                    } else {
                        $this_route_fail = TRUE;
                        break;
                    }
                }

                if ($this_route_fail === FALSE) {
                    //Route Data Object. This objejct can read inside route destination file
                    $route_data['REQUEST_TIME'] = $this->request_time;

                    //Authentication
                    //Public API Auth
                    if (isset($single_route_object->Auth) && strtolower($single_route_object->Auth) === 'public_api') {
                        $route_data['token_data'] = $this->public_api_authenticate();
                        $this->include_the_file($single_route_object, $route_data);

                        //Normal Authentication
                    } elseif (isset($single_route_object->Auth) && $single_route_object->Auth === TRUE) {
                        $route_data['token_data'] = $this->authenticate();

                        //Make simple ORG unit array
                        $ou_array = array();
                        foreach ($route_data['token_data']->OU_access_level as $ou_id => $access_level) {
                            $ou_array[] = (int) $ou_id;
                        }

                        //Create Simple Array for ORG Units
                        $route_data['token_data']->user_OUs = $ou_array;

                        if ($route_data['token_data']->role_id === 2) {
                            $route_data['token_data']->role_type = 'client';
                        } elseif ($route_data['token_data']->role_id === 3) {
                            $route_data['token_data']->role_type = 'lawyer';
                        }

                        //Role ID
                        $role_id = $route_data['token_data']->role_id;

                        //Role check for this endpoint
                        if (isset($single_route_object->allowed_roles) && $single_route_object->allowed_roles === 'ALL') {
                            $this->include_the_file($single_route_object, $route_data);
                        } else if (isset($single_route_object->allowed_roles) && in_array($role_id, $single_route_object->allowed_roles)) {
                            $this->include_the_file($single_route_object, $route_data);
                        } else {
                            $this->CO->errors_class->error_response(404, 'route_not_found', 'Route not found', 'error', 'Route found but user not allowed to access this resource');
                        }
                    } elseif (isset($single_route_object->Auth) && $single_route_object->Auth === FALSE) {
                        $this->include_the_file($single_route_object, $route_data);
                    } else {
                        $this->CO->errors_class->error_response(404, 'route_not_found', 'Route not found', 'error', 'Route found but authentication not defined');
                    }

                    exit;
                }
            }
        }

        //Route Not Found
        $this->CO->errors_class->error_response(404, 'route_not_found', 'Route not found', 'error', 'Cannot find the route entry in the route object');
    }

    private function include_the_file($route_object, $route_data) {

        $file_path = '';

        if ($this->request_type === 'OPTIONS') {
            $this->cors();
        } else if (isset($route_object->ALL)) {
            $file_path = ROOT . '/' . $route_object->module_path . $route_object->ALL;
        } elseif (isset($route_object->{$this->request_type})) {
            $file_path = ROOT . '/' . $route_object->module_path . $this->request_type . '/' . $route_object->{$this->request_type};
        } else {
            //Route Not Found
            $this->CO->errors_class->error_response(404, 'route_not_found', 'Route not found', 'error', 'Route exist, but invalid GET/POST method');
        }

        if ($file_path != '' && file_exists($file_path)) {

            //Passing class object to the route
            $CO = $this->CO;

            //Renew Secondary Token
            if (isset($route_data['token_data']->user_id)) {
                $this->CO->middleware_class->secondary_cookie_token($route_data['token_data']->user_id, 3);
            }

            include $file_path;
        } else {
            $this->CO->errors_class->error_response(404, 'route_not_found', 'Route not found', 'error', 'Route Found, but internal file is missing');
        }
    }

}
