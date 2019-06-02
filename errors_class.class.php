<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class errors_class {

    public function __construct() {
        $this->status_codes = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            426 => 'Upgrade Required',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended'
        );

        //Get server protocol
        $this->SERVER_PROTOCOL = $_SERVER['SERVER_PROTOCOL'];
    }

    /**
     * Render JSON status code based error message. (This method will kill/exit the PHP script.)
     * @param type $http_statusCode HTTP Status code
     * @param type $error_code Error Message
     * @param type $public_error_msg Additional Message (Optional)
     * @param string $response_type (error/warning)
     * @param string $detailed_error_info (Detailed information about this error) Not visible to the public. Only for troubleshooting purposes.
     * @param boolean $report_this Write this error to error log file in Production Env
     */
    public function error_response($http_statusCode, $error_code, $public_error_msg = '', $response_type = 'error', $detailed_error_info = 'not supplied', $catchThis_error = FALSE) {

        if (isset($this->status_codes[$http_statusCode])) {
            $status_string = $http_statusCode . ' ' . $this->status_codes[$http_statusCode];
            if (php_sapi_name() !== "cli") {
                header($this->SERVER_PROTOCOL . ' ' . $status_string, true, $http_statusCode);
            }
            $return_data['response'] = $status_string;
            if ($error_code !== '') {
                $return_data['error'] = str_replace(' ', '_', strtolower($error_code));
            } else {
                $return_data['error'] = 'unknown';
            }
            $return_data['status'] = 'fail';

            if ($public_error_msg !== '') {
                $return_data['msg'] = $public_error_msg;
            }
            $return_data['response_type'] = $response_type;

            if (IS_THIS_PROD !== TRUE) {
                $return_data['dev_info'] = $detailed_error_info . ' (This information is only visible in the DEV environment)';
            }
            //Catch Error to DB
            if (TRUE) {
                $this->catch_error_to_log($error_code, $detailed_error_info, $return_data);
            }
            if (php_sapi_name() === "cli") {
                echo json_encode($return_data, JSON_PRETTY_PRINT);
            } else {
                helpers::json_display($return_data);
            }
            exit;
        }
    }

    /**
     * Write error to log
     * @param type $detailed_error_info
     */
    public function catch_error_to_log($error_code, $detailed_error_info, $full_data) {
        //Catch Error to DB
        global $CO;
        if (isset($CO->models)) {
            $CO->models->add_to_system_notification($error_code, $detailed_error_info, $full_data);
        }
    }

    /**
     * Render Success Message
     * @param type $statusCode
     * @param type $id
     */
    public function ok_response($statusCode, $id = '', $info = '', $response_type = 'success') {

        if (isset($this->status_codes[$statusCode])) {
            $status_string = $statusCode . ' ' . $this->status_codes[$statusCode];
            header($this->SERVER_PROTOCOL . ' ' . $status_string, true, $statusCode);

            $return_data['response'] = $status_string;

            $return_data['status'] = 'ok';

            //Add ID
            if ($id != '') {
                $return_data['id'] = $id;
            }

            //Info
            if ($info != '') {
                $return_data['info'] = $info;
            }
            $return_data['response_type'] = $response_type;

            helpers::json_display($return_data);
            exit;
        }
    }

}
