<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class validation {

    public function __construct($error_class) {
        $this->error_class = $error_class;
    }

    /**
     * Validate an Input
     * @param string $var Input (any string, int or float)
     * @param type $type Type to validate the input against (email/number/float/boolean/ip/url/string)
     * @param type $optional
     * @return string
     * @author Mihitha R K<mihitha@gmail.com>
     */
    public function validateItem($var, $type) {

        $return_value = FALSE;

        switch ($type) {
            case 'email':
                $var = substr($var, 0, 254);
                if (filter_var($var, FILTER_VALIDATE_EMAIL)) {
                    $return_value = TRUE;
                }
                break;
            case 'number':
                if (filter_var($var, FILTER_VALIDATE_INT) || is_numeric($var)) {
                    $return_value = TRUE;
                }
                break;
            case 'float':
                if (filter_var($var, FILTER_VALIDATE_FLOAT) || $var === '0.00') {
                    $return_value = TRUE;
                }
                break;
            case 'boolean':
                if (($var == 0 || $var == 1)) {
                    $return_value = TRUE;
                }
                break;
            case 'ip':
                if (filter_var($var, FILTER_VALIDATE_IP)) {
                    $return_value = TRUE;
                }
                break;
            case 'url':
                if (filter_var($var, FILTER_VALIDATE_URL)) {
                    $return_value = TRUE;
                }
                break;
            case 'string':
                //This will accept any string but will sanitize it inside "post_data_mapper" method
                if (strlen($var) > 0) {
                    $return_value = TRUE;
                }
                break;
            /**
             * Password must be longer than 8
             */
            case 'password':
                if (strlen($var) > 7) {
                    $return_value = TRUE;
                }
                break;

            default:
                $return_value = FALSE;
        }

        return $return_value;
    }

    /**
     * Validate input or input array
     * @param type $field_data_array
     * @param type $type
     * @return boolean
     */
    private function validate_item_or_array($field_data, $type) {
        if (is_array($field_data)) {
            foreach ($field_data as $field) {
                if (!$this->validateItem($field, $type)) {
                    return FALSE;
                }
            }
            return TRUE;
        } else {
            return $this->validateItem($field_data, $type);
        }
    }

    /**
     * Sanitize Array or String with PHP FILTER_SANITIZE_STRING
     * @param array/string $field_data Input Data
     * @return array/string Sanitized data output
     */
    public function sanitize_array_or_string($field_data) {
        $sanitize_array_or_stringd_data = array();
        if (is_array($field_data)) {
            foreach ($field_data as $field) {
                $sanitize_array_or_stringd_data[] = filter_var($field, FILTER_SANITIZE_STRING);
            }
            return $sanitize_array_or_stringd_data;
        } else {
            return filter_var($field_data, FILTER_SANITIZE_STRING);
        }
    }

    /**
     * trim_array_or_string
     * @param type $field_data
     * @return type
     */
    public function trim_array_or_string($field_data) {
        $sanitize_array_or_stringd_data = array();
        if (is_array($field_data)) {
            foreach ($field_data as $field) {
                $sanitize_array_or_stringd_data[] = trim($field);
            }
            return $sanitize_array_or_stringd_data;
        } else {
            return trim($field_data);
        }
    }

    public function format_email($field_data) {
        $sanitize_array_or_stringd_data = array();
        if (is_array($field_data)) {
            foreach ($field_data as $field) {
                $sanitize_array_or_stringd_data[] = trim(strtolower($field));
            }
            return $sanitize_array_or_stringd_data;
        } else {
            return trim(strtolower($field_data));
        }
    }

    /**
     * Validate/Sanitize POST Data
     * @Note If you are expecting an array from the user, you have to explicitly set "'is_array' => true".
     * @Note Data will get separated based on data groups that user defines
     * @Note "is_array' => true" will converts non array value to an array.
     * @param type $rules_object
     * Sample Rules Object : 
     * $rules_object = (object) array(
      'company_id' => (object) array(
      'type' => 'number',
      'is_array' => false,
      'required' => true,
      'is_relational' => true
      ),
      'password' => (object) array(
      'type' => 'password',
      'is_array' => false,
      'required' => true,
      'is_relational' => true
      ),
      'array_test' => (object) array(
      'type' => 'number',
      'is_array' => true,
      'required' => false
      )
      );
     * @return Object Sanitized POST data.
     * Example : array(5) {
      ["DATA"]=>
      array(5) {
      ["first_name"]=>
      string(4) "Test"
      ["last_name"]=>
      string(10) "Kankanamge"
      ["email"]=>
      string(14) "test@gmail.com"
      ["company_id"]=>
      string(1) "1"
      ["password"]=>
      string(8) "Mdwxxx33"
      }
      ["DATA_NR"]=>
      array(2) {
      ["first_name"]=>
      string(4) "Test"
      ["last_name"]=>
      string(10) "Kankanamge"
      }
      ["DATA_R"]=>
      array(3) {
      ["email"]=>
      string(14) "test@gmail.com"
      ["company_id"]=>
      string(1) "1"
      ["password"]=>
      string(8) "Mdwxxx33"
      }
      ["any_invalid_items"]=>
      bool(false)
      ["invalid_items"]=>
      array(0) {
      }
      }
     * @author Mihitha R K<mihitha@gmail.com>
     */
    function post_data_mapper($rules_object) {
        $data_array = $_POST;

        $data_object = [];
        $invalid_items = array();
        foreach ($rules_object as $field_key => $field_rules) {

            if (isset($data_array[$field_key]) && $data_array[$field_key] != '') {
                //Sanitize only strings
                if ($field_rules->type === 'string') {
                    $this_field_data = $this->sanitize_array_or_string($data_array[$field_key]);
                } elseif ($field_rules->type === 'password') {
                    $this_field_data = $data_array[$field_key];
                } elseif ($field_rules->type === 'email') {
                    $this_field_data = $this->format_email($data_array[$field_key]);
                } else {
                    $this_field_data = $this->trim_array_or_string($data_array[$field_key]);
                }
            } else {
                $this_field_data = NULL;
            }

            //Is this array
            $this_this_array = is_array($this_field_data);

            //Is Required
            if (!isset($field_rules->required)) {
                $field_rules->required = FALSE;
            }

            //Convert item to array if validation expecting it as an array
            if ($this_field_data != NULL && isset($field_rules->is_array) && $field_rules->is_array && !$this_this_array) {
                $this_field_data = (array) $this_field_data;
            }


            //Validation will be FALSE when the user sending an array when the validation object is not expectinf it
            if ((!isset($field_rules->is_array) || !$field_rules->is_array) && $this_this_array) {
                $is_this_valid = FALSE;
            } else {
                $is_this_valid = $this->validate_item_or_array($this_field_data, $field_rules->type);
            }

            //Valid
            if ($field_rules->required && $this_field_data != NULL && $is_this_valid) {
                $data_object['DATA'][$field_key] = $this_field_data;
            }
            //Valid
            if (!$field_rules->required && $this_field_data != NULL) {
                $data_object['DATA'][$field_key] = $this_field_data;
            }
            //Valid
            if (!$field_rules->required && $this_field_data != NULL && $is_this_valid) {
                $data_object['DATA'][$field_key] = $this_field_data;
            }

            //Invalid
            if ($field_rules->required && $this_field_data == NULL && !$is_this_valid) {
                if (isset($field_rules->field_name)) {
                    $invalid_items[] = $field_rules->field_name;
                } else {
                    $invalid_items[] = $field_key;
                }
            }
            //Invalid
            if ($field_rules->required && $this_field_data != NULL && !$is_this_valid) {
                if (isset($field_rules->field_name)) {
                    $invalid_items[] = $field_rules->field_name;
                } else {
                    $invalid_items[] = $field_key;
                }
            }
            //Invalid
            if (!$field_rules->required && $this_field_data != NULL && !$is_this_valid) {
                if (isset($field_rules->field_name)) {
                    $invalid_items[] = $field_rules->field_name;
                } else {
                    $invalid_items[] = $field_key;
                }
            }

            //Set Group by object to NULL
            if (isset($field_rules->group_by) && !isset($data_object[$field_rules->group_by])) {
                $data_object[$field_rules->group_by] = NULL;
            }

            //Map relational and non relational data based on "$field_rules->is_relational" value
            if (isset($data_object['DATA'][$field_key]) && isset($field_rules->group_by)) {
                $data_object[$field_rules->group_by][$field_key] = $data_object['DATA'][$field_key];
            }
        }


        //Return Info About Invalid Items
        if (count($invalid_items) > 0) {
            $data_object['any_invalid_items'] = true;
            $data_object['invalid_items'] = $invalid_items;
        } else {
            $data_object['any_invalid_items'] = false;
            $data_object['invalid_items'] = $invalid_items;
        }

        //Unset POST Data (to stop developers are miss using POST data. )
        unset($_POST);

        //Render JSON error if there any invalid items 
        if ($data_object['any_invalid_items']) {
            $this->error_class->error_response(200, 'invalid_fields', $data_object['invalid_items']);
        } else {
            return $data_object;
        }
    }

}
