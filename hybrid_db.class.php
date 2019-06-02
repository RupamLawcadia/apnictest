<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class hybrid_db {

    public function __construct($CO) {
        $this->errors_class = $CO->errors_class;
        $this->database_connector = $CO->database_connector;
        $this->proxy = $CO->proxy;
        $this->helpers = $CO->helpers;
    }

    /**
     * Create non detailed message for production environment.
     * @param type $code
     * @param type $msg
     */
    private function render_error($code, $msg) {
        //Render Error
        $this->errors_class->error_response($code, 'remote_database_error', 'Something went wrong', 'error', $msg);
    }

    /**
     * Get Encryption Key of the company (Key Authorization to be implemented)
     * @param int $company_id Company ID
     * @return string Encryption Key
     */
    private function get_encryption_key_of_company($company_id) {
        $encryption_key = $this->database_connector->find_from_id('companies', $company_id, array('encryption_key'), TRUE);
        if (strlen($encryption_key['encryption_key']) > 31) {
            return $encryption_key['encryption_key'];
        } else {
            return NULL;
        }
    }

    /**
     * Encrypt Object before send to the NOSQL
     * @param type $company_id
     * @param type $nosql_data_obj
     * @return type
     */
    private function encrypt_nosql($company_id, $nosql_data_obj) {
        $encryption_key = $this->get_encryption_key_of_company($company_id);

        if ($encryption_key === NULL) {
            //Set encrypted flag to no if not encrypted
            $nosql_data_obj->encrypted = 'no';
            return $nosql_data_obj;
        } else {
            $enc_nosql_data_obj = (object) array();
            foreach ($nosql_data_obj as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $enc_nosql_data_obj->{$key} = $this->helpers->encrypt_salt($value, $encryption_key);
            }
            //Set encrypted flag to yes if encrypted
            $enc_nosql_data_obj->encrypted = 'yes';
            //Add encrption key checksum
            $enc_nosql_data_obj->encryption_key_checksum = sha1($encryption_key);
            return $enc_nosql_data_obj;
        }
    }

    /**
     * Make sure encryption key is not altered.
     * @param type $key
     * @param type $checksum
     */
    private function encryption_key_checksum_match($key, $checksum) {
        if ($checksum !== sha1($key)) {
            $this->render_error(500, 'Data Decryption Error. Encryption Key Altered');
        }
    }

    /**
     * Decrypt data get returned from NOSQL
     * @param type $company_id
     * @param type $enc_nosql_data_obj
     * @return type
     */
    private function decrypt_nosql($encryption_key, $enc_nosql_data_obj) {
        if ($encryption_key === NULL) {
            return $enc_nosql_data_obj;
        }

        //check for encryption flag
        if (!(isset($enc_nosql_data_obj->encrypted) && $enc_nosql_data_obj->encrypted == 'yes')) {
            //Data not encrypted
            return $enc_nosql_data_obj;
        } elseif ($enc_nosql_data_obj->encrypted == 'yes' && isset($enc_nosql_data_obj->encryption_key_checksum)) {
            //Match Checksum
            $this->encryption_key_checksum_match($encryption_key, $enc_nosql_data_obj->encryption_key_checksum);

            $dec_nosql_data_obj = (object) array();
            foreach ($enc_nosql_data_obj as $key1 => $value1) {
                if ($key1 === '_id') {
                    $dec_nosql_data_obj->{$key1} = $value1;
                } else {
                    $decrypted_data = $this->helpers->decrypt_salt($value1, $encryption_key);

                    $decrypted_data_json = json_decode($decrypted_data);
                    if ($decrypted_data_json !== NULL) {
                        $dec_nosql_data_obj->{$key1} = $decrypted_data_json;
                    } else {
                        $dec_nosql_data_obj->{$key1} = $decrypted_data;
                    }
                }
            };
            //Unset encryption related keys
            unset($dec_nosql_data_obj->encrypted);
            unset($dec_nosql_data_obj->encryption_key_checksum);

            return $dec_nosql_data_obj;
        } else {
            //May be do nothing
            //return (object) array('');
            //$this->errors_class->error_response(500, 'decryption_error', 'Data Decryption Error. Checksum not registered');
        }
    }

    /**
     * Insert to NoSQL Database
     * @param type $company_id Company ID to find database location
     * @param type $item_id Item ID required as NoSQL does not auto increment IDs in this occasion
     * @param type $collection_name Collection Name users/companies
     * @param type $nosql_data_obj Object to Insert
     * @return Object Return Data
     */
    public function insert_to_nosql($company_id, $item_id, $collection_name, $nosql_data_obj_) {
        $nosql_data_obj = (object) $nosql_data_obj_;

        //Encrypt data before insert to the NOSQL instance
        $nosql_data_obj_enc = $this->encrypt_nosql($company_id, $nosql_data_obj);

        $url = 'sub_api_1/add/' . $collection_name . '/' . $item_id . '/';

        $response = $this->proxy->POST_NoSQL($company_id, $url, $nosql_data_obj_enc);

        //JSON Decode the Response
        return json_decode($response);
    }

    /**
     * Edit NoSQL content based on ID
     * @param type $company_id
     * @param type $item_id
     * @param type $collection_name
     * @param type $nosql_data_obj
     * @param string $upsert 'yes' or 'no'
     * @return type
     */
    public function edit_nosql($company_id, $item_id, $collection_name, $nosql_data_obj, $upsert) {
        $nosql_data_obj = (object) $nosql_data_obj;

        //Encrypt data before insert to the NOSQL instance
        $nosql_data_obj_enc = $this->encrypt_nosql($company_id, $nosql_data_obj);
        //URL
        $url = 'sub_api_1/edit/' . $collection_name . '/' . $item_id . '/' . $upsert . '/';
        //POST to NoSQL
        $response = $this->proxy->POST_NoSQL($company_id, $url, $nosql_data_obj_enc);

        //JSON Decode the Response
        return json_decode($response);
    }

    /**
     * Enter Data into Hybrid data environment
     * @param type $company_id
     * @param type $collection_name
     * @param type $mysql_data_array
     * @param type $nosql_data_obj
     */
    public function insert_into_hybrid_db($company_id, $collection_name, $mysql_data_array, $nosql_data_obj = array()) {
        //Insert MySQL Table
        $this->database_connector->beginTransaction();
        //Get the last Insert ID
        $sql_insert_id = $this->database_connector->insert_to_table($collection_name, $mysql_data_array);

        if ($sql_insert_id > 0) {

            //Do not execute NOSQL insertion if no data for nosql
            if (count($nosql_data_obj) < 1) {
                $this->database_connector->commit();
                return $sql_insert_id;
            }

            //Insert to Nosql Database
            $response_obj = $this->insert_to_nosql($company_id, $sql_insert_id, $collection_name, $nosql_data_obj);

            if (isset($response_obj->id) && $response_obj->id > 0 && $response_obj->status === 'ok') {
                $this->database_connector->commit();
                return $response_obj->id;
            } elseif (isset($response_obj->error)) {
                $this->database_connector->rollBack();
                $this->render_error(400, $response_obj->error);
            } else {
                $this->database_connector->rollBack();
                $this->render_error(400, 'Remote Data Storage Error');
            }
        } else {
            $this->render_error(400, 'MySQL Server Issue');
        }
    }

    /**
     * Get items remote NOSQL instance
     * @param type $company_id
     * @param type $collection_name
     * @param type $index_key
     * @param type $value
     * @param type $value_type
     * @param type $get_multiple Set TRUE to get multiple values
     * @return type
     */
    public function get_items_from_remote_nosql($company_id, $collection_name, $index_key, $value_plain_text, $value_type, $get_multiple) {
        //Get Encryption Key
        $encryption_key = $this->get_encryption_key_of_company($company_id);

        //Check for 
        if ($encryption_key === NULL || $index_key == '_id') {
            $value = $value_plain_text;
        } else {
            $value = urlencode($this->helpers->encrypt_salt($value_plain_text, $encryption_key));
        }

        if ($get_multiple === TRUE) {
            $url = 'sub_api_1/get/' . $collection_name . '/' . $index_key . '/' . $value . '/' . $value_type . '/yes';
        } else {
            $url = 'sub_api_1/get/' . $collection_name . '/' . $index_key . '/' . $value . '/' . $value_type . '/no';
        }


        $data_from_server = json_decode($this->proxy->GET_NoSQL($company_id, $url));

        //Decrypt Data
        return $this->decrypt_nosql($encryption_key, $data_from_server);
    }

    /**
     * Get individual item from Hybrid Database
     * @param type $company_id
     * @param type $collection_name
     * @param type $item_id
     * @param array $items_to_select Eg - array('id', 'email', 'bio');
     * @param boolean Optional $get_only_sql_data If this set to TRUE function does not fetch data from NOSQL instance
     * @param type Optional $strict_mode If set to true, function will fail if no information returns from the NOSQL
     * @return Object
     */
    public function get_from_hybrid_db($company_id, $collection_name, $item_id, $items_to_select, $get_only_sql_data = FALSE, $strict_mode = FALSE, $terminate_script = TRUE) {
        //Find Remote host based on comapany ID
        if ($get_only_sql_data === TRUE) {
            //Select based on given array if we getting it from SQL only
            $data['sql'] = $this->database_connector->find_from_id($collection_name, $item_id, $items_to_select);
        } else {
            $data['sql'] = $this->database_connector->find_from_id($collection_name, $item_id, array('*'));
        }
        //Return SQL only data
        if ($get_only_sql_data === TRUE) {
            return $data['sql'];
        }

        //Get NOSQL data
        $data['nosql'] = $this->get_items_from_remote_nosql($company_id, $collection_name, '_id', $item_id, 'int', FALSE);

        //Make sure NoSQL returns any data
        if (isset($data['nosql']->_id)) {
            //Unset NoSQL id
            unset($data['nosql']->_id);
            $return_data = (object) array_merge((array) $data['sql'], (array) $data['nosql']);

            //Display only the items based on $items_to_select
            if ($items_to_select[0] === '*') {
                return $return_data;
            } elseif (count($items_to_select) > 0) {
                //Source - https://stackoverflow.com/a/4260168/1362713
                return array_intersect_key((array) $return_data, array_flip($items_to_select));
            }
        } else {
            if ($strict_mode === TRUE) {
                if ($terminate_script === TRUE) {
                    $this->render_error(400, 'Remote NOSQL instance did not respond with valid data');
                } else {
                    return NULL;
                }
            } else {
                return (object) $data['sql'];
            }
        }
    }

    /**
     * Find from NOSQL - similar to MySQL IN
     * @param type $company_id
     * @param type $collection_name
     * @param type $id_array
     * @param type $nosql_select_columns
     * @return type
     */
    public function nosql_find_from_id_array($company_id, $collection_name, $id_array, $nosql_select_columns) {
        //Find Remote host based on comapany ID
        $url = 'sub_api_1/find_multiple/' . $collection_name . '/';
        $form_params = array(
            'in_array' => implode(',', $id_array),
            'select_column_array' => implode(',', $nosql_select_columns),
        );


        $data_from_server = json_decode($this->proxy->POST_NoSQL($company_id, $url, $form_params));

        if (is_array($data_from_server)) {
            //Decrypt Data
            $decrpted_data = (object) array();
            //Get Encryption Key
            $encryption_key = $this->get_encryption_key_of_company($company_id);
            foreach ($data_from_server as $data1) {
                $decrpted_data->{$data1->_id} = $this->decrypt_nosql($encryption_key, $data1);
            }
        } else {
            $this->render_error(400, 'Update Failed from NoSQL server');
        }

        return $decrpted_data;
    }

    /**
     * Find multiple Hybrid Db collection objects using an array
     * @param type $company_id
     * @param type $collection_name
     * @param type $id_array
     * @param type $mysql_select_columns
     * @param type $nosql_select_columns This does not accept array('*') - Must be a proper array of keys
     * @return type
     */
    public function hybrid_DB_find_from_id_array($company_id, $collection_name, $id_array, $mysql_select_columns, $nosql_select_columns) {
        $data['nosql'] = $this->nosql_find_from_id_array($company_id, $collection_name, $id_array, $nosql_select_columns);

        if ($mysql_select_columns[0] != '*') {
            array_push($mysql_select_columns, 'id');
        }

        $in_object_ = array('column' => 'id', 'item_array' => $id_array);
        $where_object = array(
            (object) array(
                'key' => 'company_id',
                'compare' => '=',
                'value' => $company_id,
                'bool' => 'AND',
            ),
        );
        $data['mysql'] = $this->database_connector->mysql_in_v2($collection_name, '', $in_object_, $mysql_select_columns, $where_object);

        return $this->merge_mysql_no_sql($data['mysql'], $data['nosql']);
    }

    /**
     * Merge mysql and nosql data objects (PVT function)
     * @param type $data_mysql
     * @param type $data_nosql
     */
    private function merge_mysql_no_sql($data_mysql, $data_nosql) {
        $return_data = array();
        foreach ($data_mysql as $mysql_) {

            if (isset($data_nosql->{$mysql_['id']})) {
                $nosql_item = $data_nosql->{$mysql_['id']};

                $obj_merged = (object) array_merge((array) $nosql_item, (array) $mysql_);
                unset($obj_merged->_id);

                $return_data[] = $obj_merged;
            }
        }
        return $return_data;
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $data_nosql
     * @return type
     */
    private function merge_mysql_no_sql_v2($data_mysql, $data_nosql) {
        $return_data = array();
        foreach ($data_mysql as $mysql_) {

            if (isset($data_nosql->{$mysql_['primary_contact']})) {
                $nosql_item = $data_nosql->{$mysql_['primary_contact']};
                unset($nosql_item->_id);

                $obj_merged = (object) array_merge(array('primary_contact_details' => $nosql_item), (array) $mysql_);

                $return_data[] = $obj_merged;
            }
        }
        return $return_data;
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $data_nosql
     * @return type
     */
    private function merge_mysql_no_sql_v3($data_mysql, $data_nosql) {
        $return_data = array();
        foreach ($data_mysql as $mysql_) {

            if (isset($data_nosql->{$mysql_['id']})) {
                $nosql_item = $data_nosql->{$mysql_['id']};

                $obj_merged = array_merge((array) $nosql_item, (array) $mysql_);
                unset($obj_merged->_id);

                $return_data[] = $obj_merged;
            }
        }
        return $return_data;
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $data_nosql
     * @return type
     */
    private function merge_mysql_no_sql_v4($data_mysql, $data_nosql) {
        $return_data = array();
        foreach ($data_mysql as $mysql_) {

            if (isset($data_nosql->{$mysql_['user_id']})) {
                $nosql_item = $data_nosql->{$mysql_['user_id']};
                unset($nosql_item->_id);

                $obj_merged = (object) array_merge((array) $nosql_item, (array) $mysql_);

                $return_data[] = $obj_merged;
            }
        }
        return $return_data;
    }

    /**
     * Use this function if you have processed mysql data response. 
     * Example - use "get_from_table" function query the mysql table and pass that mysql response query array to this function.
     * Make sure to refer correct mysql tables and nosql collections
     * @param type $company_id
     * @param type $mysql_data_array
     * @param type $collection
     * @param type $nosql_column_array
     * @return type
     */
    public function get_relevent_nosql_data_from_mysql_array($company_id, $mysql_data_array, $collection, $nosql_column_array) {
        $id_array = array();
        foreach ($mysql_data_array as $data_mysql_v) {
            $id_array[] = $data_mysql_v['id'];
        }
        $nosql_data = $this->nosql_find_from_id_array($company_id, $collection, $id_array, $nosql_column_array);
        return $this->merge_mysql_no_sql($mysql_data_array, $nosql_data);
    }

    /**
     * 
     * @param type $company_id
     * @param type $mysql_data_array
     * @param type $collection
     * @param type $nosql_column_array
     * @return type
     */
    public function get_relevent_nosql_data_from_mysql_array_v2($company_id, $mysql_data_array, $collection, $nosql_column_array) {
        $id_array = array();
        foreach ($mysql_data_array as $data_mysql_v) {
            $id_array[] = $data_mysql_v['id'];
        }
        $nosql_data = $this->nosql_find_from_id_array($company_id, $collection, $id_array, $nosql_column_array);
        return $this->merge_mysql_no_sql_v3($mysql_data_array, $nosql_data);
    }

    /**
     * This function is similar to mysql "get_from_table". But able to map all the relevant NoSQL information as well
     * @param type $collection
     * @param type $sql_prefix Eg - DISCTINCT
     * @param type $sql_columns_to_get
     * @param type $nosql_select_columns
     * @param type $sql_where_object
     * @param type $sql_suffix Eg - LIMIT
     * @return type
     */
    public function get_from_collection_with_sql_filters($collection, $sql_prefix, $sql_columns_to_get, $nosql_select_columns, $sql_where_object, $sql_suffix) {
        //Retrive ID is compulsory.
        array_push($sql_columns_to_get, 'id', 'company_id');
        $data_mysql = $this->database_connector->get_from_table($collection, $sql_prefix, $sql_columns_to_get, $sql_where_object, $sql_suffix);
        return $this->get_from_nosql_with_given_mysql_array($data_mysql, $collection, $nosql_select_columns);
    }

    /**
     * Get NoSQL data merged with given MYSQL array (Each MySQL array item should contain company_id) 
     * @param type $data_mysql MySQL data array with company ID
     * @param type $collection NoSQL collection name
     * @param type $nosql_select_columns NoSQL select columns
     * @return array NoSQL data combined with given MySQL array
     */
    public function get_from_nosql_with_given_mysql_array($data_mysql, $collection, $nosql_select_columns) {
        //Create array of IDs grouped by company_id
        $id_array = array();
        foreach ($data_mysql as $data_mysql_v) {
            if ($data_mysql_v['company_id'] < 1) {
                $this->render_error(400, 'Cannot find company ID in $data_mysql array - get_from_nosql_with_mysql_array');
            }

            $id_array[$data_mysql_v['company_id']][] = $data_mysql_v['id'];
        }

        //Get NoSQL data
        $data_nosql = array();
        foreach ($id_array as $id_array_k => $id_array_v) {
            $data_nosql[] = (array) $this->nosql_find_from_id_array($id_array_k, $collection, $id_array_v, $nosql_select_columns);
        }

        if (empty($data_nosql)) {
            return array();
        }

        //Combine data from different sources.
        $nosql_data_combined = (object) call_user_func_array('array_merge', $data_nosql);

        return $this->merge_mysql_no_sql($data_mysql, $nosql_data_combined);
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $collection
     * @param type $nosql_select_columns
     * @return type
     */
    public function get_from_nosql_with_given_mysql_array_v2($data_mysql, $collection, $nosql_select_columns) {
        //Create array of IDs grouped by company_id
        $id_array = array();
        foreach ($data_mysql as $data_mysql_v) {
            if ($data_mysql_v['company_id'] < 1) {
                $this->render_error(400, 'Cannot find company ID in $data_mysql array - get_from_nosql_with_mysql_array');
            }
            if (empty($data_mysql_v['primary_contact'])) {
                $id = $data_mysql_v['user_id'];
            } else {
                $id = $data_mysql_v['primary_contact'];
            }
            $id_array[$data_mysql_v['company_id']][] = $id;
        }

        //Get NoSQL data
        $data_nosql = array();
        foreach ($id_array as $id_array_k => $id_array_v) {
            $data_nosql[] = (array) $this->nosql_find_from_id_array($id_array_k, $collection, $id_array_v, $nosql_select_columns);
        }

        if (empty($data_nosql)) {
            return array();
        }

        //Combine data from different sources.
        $nosql_data_combined = (object) call_user_func_array('array_merge', $data_nosql);
        return $this->merge_mysql_no_sql_v2($data_mysql, $nosql_data_combined);
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $collection
     * @param type $nosql_select_columns
     * @return type
     */
    public function get_from_nosql_with_given_mysql_array_v3($data_mysql, $collection, $nosql_select_columns) {
        //Create array of IDs grouped by company_id
        $id_array = array();
        foreach ($data_mysql as $data_mysql_v) {
            if ($data_mysql_v['company_id'] < 1) {
                $this->render_error(400, 'Cannot find company ID in $data_mysql array - get_from_nosql_with_mysql_array');
            }

            $id_array[$data_mysql_v['company_id']][] = $data_mysql_v['id'];
        }

        //Get NoSQL data
        $data_nosql = array();
        foreach ($id_array as $id_array_k => $id_array_v) {
            $data_nosql[] = (array) $this->nosql_find_from_id_array($id_array_k, $collection, $id_array_v, $nosql_select_columns);
        }

        if (empty($data_nosql)) {
            return array();
        }

        //Combine data from different sources.
        $nosql_data_combined = (object) call_user_func_array('array_merge', $data_nosql);

        return $this->merge_mysql_no_sql_v3($data_mysql, $nosql_data_combined);
    }

    /**
     * 
     * @param type $data_mysql
     * @param type $collection
     * @param type $nosql_select_columns
     * @return type
     */
    public function get_from_nosql_with_given_mysql_array_v4($data_mysql, $collection, $nosql_select_columns) {
        //Create array of IDs grouped by company_id
        $id_array = array();
        foreach ($data_mysql as $data_mysql_v) {
            if ($data_mysql_v['company_id'] < 1) {
                $this->render_error(400, 'Cannot find company ID in $data_mysql array - get_from_nosql_with_mysql_array');
            }
            $id = $data_mysql_v['user_id'];
            $id_array[$data_mysql_v['company_id']][] = $id;
        }

        //Get NoSQL data
        $data_nosql = array();
        foreach ($id_array as $id_array_k => $id_array_v) {
            $data_nosql[] = (array) $this->nosql_find_from_id_array($id_array_k, $collection, $id_array_v, $nosql_select_columns);
        }

        if (empty($data_nosql)) {
            return array();
        }

        //Combine data from different sources.
        $nosql_data_combined = (object) call_user_func_array('array_merge', $data_nosql);
        return $this->merge_mysql_no_sql_v4($data_mysql, $nosql_data_combined);
    }

    /**
     * Edit Hybrid DB values from ID
     * @param type $collection
     * @param type $company_id
     * @param type $id
     * @param type $sql_column_value_array
     * @param type $nosql_column_value_array
     * @param type $upesert_nosql Upsert MONGO DB Information. Set to "no" by default.
     */
    public function edit_hybrid_db_value_from_id($collection, $company_id, $id, $sql_column_value_array, $nosql_column_value_array, $upsert_nosql = 'no', $terminate_script = TRUE) {
        $status_sql = FALSE;
        $status_nosql = FALSE;

        //Begin MySQL transaction
        $this->database_connector->beginTransaction();
        if ($sql_column_value_array != NULL && count($sql_column_value_array) > 0) {
            $status_sql = $this->database_connector->update_entry($collection, 'id', $id, $sql_column_value_array);
        }

        if ($nosql_column_value_array != NULL && count($nosql_column_value_array) > 0) {
            $status_nosql = $this->edit_nosql($company_id, $id, $collection, $nosql_column_value_array, $upsert_nosql);
        }

        if ($status_sql === TRUE) {
            $status['sql'] = 'ok';
        }
        $status['nosql'] = $status_nosql;

        if ($status_sql === TRUE || ($status_nosql->MatchedCount > 0) || $status_nosql->ModifiedCount > 0) {
            //Commit Transaction
            $this->database_connector->commit();
            if ($terminate_script === TRUE) {
                $this->errors_class->ok_response(200, $id, $status);
            } else {
                return TRUE;
            }
        } else {
            //Rollback Mysql changes
            $this->database_connector->rollBack();
            if ($terminate_script === TRUE) {
                $this->render_error(400, 'Update Failed - edit_hybrid_db_value_from_id method');
            } else {
                return FALSE;
            }
        }
    }

    /**
     * Upload file chunks to MongoDB instance
     * @param type $company_id
     * @param type $base64_payload
     * @param type $file_id
     * @param type $total_chunks
     * @param type $this_chunk_id
     * @param type $additional_data
     * @return type
     */
    function upload_chunks($company_id, $base64_payload, $file_id, $total_chunks, $this_chunk_id, $additional_data) {
        //Get Encryption Key
        $encryption_key = $this->get_encryption_key_of_company($company_id);
        if ($encryption_key === NULL) {
            $encrypted_payload = $base64_payload;
            $is_encrypted = 'no';
        } else {
            $encrypted_payload = $this->helpers->encrypt_salt($base64_payload, $encryption_key);
            $is_encrypted = 'yes';
        }

        $form_param = array(
            '_id' => $this_chunk_id,
            'encrypted' => $is_encrypted,
            'additional_data' => $additional_data,
            'chunk_payload' => $encrypted_payload,
            'file_id' => $file_id,
            'total_chunks' => $total_chunks,
            'chunk_hash' => sha1($encrypted_payload)
        );

        $url = 'sub_api_1/upload/';
        return $this->proxy->POST_NoSQL($company_id, $url, $form_param);
    }

    /**
     * Download Attachment Chunks with NOSQL
     * @param type $attachment_id
     * @param type $chunk_id
     * @return type
     */
    function download_chunks($company_id, $attachment_id, $chunk_id) {
        //Get Encryption Key
        $encryption_key = $this->get_encryption_key_of_company($company_id);

        $url = 'sub_api_1/download/' . $attachment_id . '/' . $chunk_id;
        $return_data = json_decode($this->proxy->GET_NoSQL($company_id, $url));

        if ($return_data->encrypted === 'yes') {
            $return_data->chunk_payload = $this->helpers->decrypt_salt($return_data->chunk_payload, $encryption_key);
        }

        return $return_data;
    }

}
