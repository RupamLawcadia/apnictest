<?php

/* MySQL PDO Database connector class. 
 * All the MySQL DB quries in this entire framework are routed via this "database_connector" class.
 * Security - 
 *      Uses PDO_MYSQL driver
 *      Only prepared statements are used with parameters binding. (Except - "custom_query" method. See method documentation for more info)
 *      "ATTR_EMULATE_PREPARES" Set to false (Source - https://stackoverflow.com/a/60496/1362713)
 * 
 * Created By Mihitha Rajith Kankanamge
 * Created 29 May 2015 (Version 1)
 * Update 21 Sep 2015 (Version 2)
 * Update 29 Jun 2016 (Version 3 - PDO Changeover)
 * Update 08 May 2017 (Version 4 - for Lawcadia PHP API)
 * All rights received.
 */

class database_connector {

    public function __construct($error_class) {

        //Load error Class
        $this->errors_class = $error_class;

        //Load Database connection to an object
        try {
            $this->connection = new PDO('mysql:host=' . MYSQL_SERVER . ';dbname=' . MYSQL_DBASE . ';charset=utf8', MYSQL_USER, MYSQL_PASS);
        } catch (PDOException $e) {
            $this->render_error(400, $e->getMessage());
        }

        //Add allowed prefixes in uppercase
        $this->allowed_prefixes_list = array('', 'DISTINCT');
    }

    /**
     * Create non detailed message for production environment.
     * @param type $code
     * @param type $msg
     */
    private function render_error($code, $msg) {
        //Render Error
        $this->errors_class->error_response($code, 'database_error', 'Something went wrong', 'error', $msg);
    }

    /**
     * Validate prefix based on the 'allowed_prefixes_list' array
     * @param type $prefix_
     */
    private function validate_prefix($prefix_) {
        $prefix = strtoupper($prefix_);
        if (in_array($prefix, $this->allowed_prefixes_list) !== TRUE) {
            $this->render_error(400, 'Unauthorised prefix used in a method');
        }
    }

    /**
     * Database Connection for this Class
     * @return PDO Database Connection
     */
    private function Connect_to_PDO() {
        return $this->connection;
    }

    /**
     * Begin Transaction
     */
    public function beginTransaction() {
        $this->Connect_to_PDO()->beginTransaction();
    }

    /**
     * Commit Transaction
     */
    public function commit() {
        $this->Connect_to_PDO()->commit();
    }

    /**
     * Rollback Transaction
     */
    public function rollBack() {
        $this->Connect_to_PDO()->rollBack();
    }

    /**
     * Insert Data to Database
     * @param string $table - Table Name
     * @param array $column_value_array - Array of data 'column_name'=>'value','column_name2'=>'value2'
     * @param type $terminate_script Function will return FALSE if set to FALSE  
     * @return last insert ID
     * @return boolean false if fails
     * @author Mihitha R K <mihitha@gmail.com>
     */
    public function insert_to_table($table, $column_value_array, $terminate_script = TRUE) {
        //Count MySQL write calls
        helpers::endpoint_metrics('mysql');

        $pdo = $this->Connect_to_PDO();


        $keys = implode(',', array_keys($column_value_array));
        $values = ':' . implode(', :', array_keys($column_value_array));


        //Make Param Array in PDO way with :
        foreach ($column_value_array as $k => $v) {
            $column_value_array[':' . $k] = $v;
            unset($column_value_array[$k]);
        }

        //Create SQL statement
        $query = 'INSERT INTO ' . $table . ' (' . $keys . ') VALUES (' . $values . ')';

        //Set Errors
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //Another extra step to avoid SQL injection
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        try {
            //PREPARE & EXECUTE QUERY
            $stmt = $pdo->prepare($query);
            //Bind Paramerters (Avoid SQL injection) and execute.
            if ($stmt->execute($column_value_array)) {
                return $pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            if ($terminate_script === TRUE) {
                $this->render_error(400, $e->getMessage());
            } else {
                return FALSE;
            }
        }
    }

    /**
     * Get data from a table
     * @param type $table
     * @param type $prefix
     * @param type $selected_columns
     * @param type $where_columns
     * Eg - $where_object = array(
      (object) array(
      'key'=>'id',
      'compare'=>'=',
      'value'=>2,
      'open_bracket'=>'(',(' //Optional
      'bool'=>'AND', //Optional
      'close_bracket'=>')' //Optional
      ),
      );
     * @param type $suffix
     * @param boolean $terminate_script TRUE/FALSE
     * @return type
     */
    public function get_from_table($table, $prefix, $selected_columns, $where_columns, $suffix, $terminate_script = TRUE) {
        //Validate prefix
        $this->validate_prefix($prefix);

        //Convert Select array to a String
        $select_string = implode(',', $selected_columns);


        $where_part = '';

        //MAKE WHERE column string
        if (isset($where_columns[0]->key)) {
            $array_count = count($where_columns);
            $where_part = ' WHERE ';

            //Make Param Array in PDO way with :
            $where_columns_new = array();

            //Limit Last AND/OR of the loop
            $loop_count = 1;
            //Make "WHERE String of the QUERY"
            foreach ($where_columns as $key => $value) {

                $open_bracket = '';
                if (isset($value->open_bracket) && $value->open_bracket == '(') {
                    $open_bracket = $value->open_bracket;
                }

                $close_bracket = '';
                if (isset($value->close_bracket) && $value->close_bracket == ')') {
                    $close_bracket = $value->close_bracket;
                }

                //For all the other entris use normal $where_column_operator
                $where_part .= $open_bracket . $value->key . $value->compare . ' :' . $value->key . '_' . $loop_count . $close_bracket;

                if ($loop_count < $array_count) {
                    $where_part .= ' ' . $value->bool . ' ';
                }

                $where_columns_new[':' . $value->key . '_' . $loop_count] = $value->value;
                unset($where_columns[$key]);


                $loop_count++;
            }
        } else {
            $where_columns_new = array();
        }

        //Make QUERY
        $query = 'SELECT ' . $prefix . ' ' . $select_string . ' FROM ' . $table . $where_part . ' ' . $suffix;

        return $this->custom_query_v2($table, $query, $where_columns_new, TRUE, $terminate_script);
    }

    /**
     * Get values from selected DB Table
     * @param type $table
     * @param type $id
     * @param array $columns_to_get Array
     * @param type $do_not_kill
     * @return type
     */
    public function find_from_id($table, $id, $columns_to_get, $do_not_kill = FALSE) {
        $where_object = array(
            (object) array(
                'key' => 'id',
                'compare' => '=',
                'value' => $id
            ),
        );

        $dataset = $this->get_from_table($table, '', $columns_to_get, $where_object, 'LIMIT 1');

        if (isset($dataset[0])) {
            return $dataset[0];
        } else {
            if ($do_not_kill === TRUE) {
                return NULL;
            } else {
                $this->errors_class->error_response(400, 'no_data_found', 'No data found in the collection');
            }
        }
    }

    /**
     * Update Data in the Mysql Database
     * @param type $table 
     * @param type $which_column Search Column for Update
     * @param type $search_value Search Value
     * @param type $column_value_array (New Values to Replace ) Array of data 'column_name'=>'value','column_name2'=>'value2'
     * @return boolean
     * @author Mihitha R K <mihitha@gmail.com>
     */
    public function update_entry($table, $which_column, $search_value, $column_value_array, $terminate_script = TRUE) {

        //WHERE COLUMN ARRAY COUNT
        $array_count = count($column_value_array);

        //MAKE SET column string
        if ($array_count > 0) {
            $set_part = ' SET ';

            //Limit Last AND/OR of the loop
            $loop_count = 1;
            //Make "WHERE String of the QUERY"
            foreach ($column_value_array as $key => $value) {
                $set_part .= $key . '=' . ' :' . $key;
                if ($loop_count < $array_count) {
                    $set_part .= ', ';
                }
                $loop_count++;
                //Make Param Array in PDO way with :
                $column_value_array[':' . $key] = $value;
                unset($column_value_array[$key]);
            }
        } else {
            $this->render_error(400, '$column_value_array is empty in update_entry method');
        }

        //Add search column and value to Bind Array
        $bind_array = array_merge($column_value_array, array(':' . $which_column => $search_value));

        //Make QUERY
        $query = 'UPDATE ' . $table . $set_part . ' WHERE ' . $which_column . '=:' . $which_column;

        //Use custom Query to execute quary with BIND parameters
        return $this->custom_query_v2($table, $query, $bind_array, FALSE, $terminate_script);
    }

    /**
     * Execute Custom Query V2
     * @param string $table
     * @param string $query Eg: DELETE FROM panels WHERE ID =:id and name=:name
     * @param array $bind_array - Eg: array('id'=>34,'name'=>'Lawcadia')
     * @param boolean $query_return_data
     * @return boolean/array
     */
    public function custom_query_v2($table, $query, $bind_array, $query_return_data = FALSE, $terminate_script = TRUE) {
        //Count MySQL read calls
        helpers::endpoint_metrics('mysql');

        $pdo = $this->Connect_to_PDO();

        //Set Errors
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //Another extra step to avoid SQL injection 
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        try {
            //PREPARE & EXECUTE QUERY
            $stmt = $pdo->prepare($query);
            if ($stmt->execute($bind_array)) {
                //RETURN DATASET FROM FUNCTION as ASSOC ARRAY
                if ($query_return_data === TRUE) {
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    return TRUE;
                }
            } else {
                return FALSE;
            }
        } catch (PDOException $e) {
            if ($terminate_script === TRUE) {
                $this->render_error(400, $e->getMessage());
            } else {
                return NULL;
            }
        }
    }

    /**
     * MySQL in method
     * @param type $table_
     * @param type $in_column_
     * @param type $in_array
     * @param type $selected_columns
     * @param type $suffix
     * @param type $prefix
     */
    public function mysql_in($table_, $prefix, $in_column_, $in_array, $selected_columns) {
        //Count MySQL read calls
        helpers::endpoint_metrics('mysql');

        foreach ($in_array as $in_item) {
            $where_object[] = (object) array(
                        'key' => $in_column_,
                        'compare' => '=',
                        'value' => $in_item,
                        'bool' => 'OR'
            );
        }
        return $this->get_from_table($table_, $prefix, $selected_columns, $where_object, '');
    }

    /**
     * MySQL in method
     * @param type $table_
     * @param type $prefix
     * @param type $in_object_ Eg - $in_object_ = array('column'=>'column_name','item_array'=>array(1,3,4,5,6,7,8));
     * @param type $select_columns
     * @param type $suffix
     * @return type
     */
    public function mysql_in_v2($table_, $prefix, $in_object_, $select_columns, $where_object_ = '', $suffix = '', $terminate_script = FALSE) {
        //Count MySQL read calls
        helpers::endpoint_metrics('mysql');

        $in_object_ = (array) $in_object_;

        //Make sure $in_object_['item_array'] is an array
        if (is_array($in_object_['item_array']) !== TRUE) {
            $this->render_error(400, 'Invalid $in_object_ in mysql_in method');
        }

        $where_object = array();

        $loop_count = 1;
        $in_object_count = count($in_object_['item_array']);

        if ($in_object_count < 1) {
            return array();
        }

        foreach ($in_object_['item_array'] as $in_item) {

            $this_open_bracket = '';
            $this_close_bracket = '';
            $this_bool = 'OR';


            if ($loop_count === 1) {
                $this_open_bracket = '(';
            }

            if ($loop_count === $in_object_count) {
                $this_close_bracket = ')';
                $this_bool = 'AND';
            }

            $where_object[] = (object) array(
                        'key' => $in_object_['column'],
                        'compare' => '=',
                        'value' => $in_item,
                        'open_bracket' => $this_open_bracket,
                        'bool' => $this_bool,
                        'close_bracket' => $this_close_bracket
            );

            $loop_count++;
        }



        //Merge 2 where object arrays
        if ($where_object_ !== '') {
            //Combine data from different sources.
            $where_object_joined = array_merge($where_object, $where_object_);
        } else {
            $where_object_joined = $where_object;
        }

        //Pass dynamically created object to the Get from Table method
        return $this->get_from_table($table_, $prefix, $select_columns, $where_object_joined, $suffix, $terminate_script);
    }

    /**
     * add_new_column_to_table
     * @param string $table
     * @param string $column_name
     * @param string $data_type
     * @param int $data_type_length
     */
    private function add_new_column_to_table(string $table, string $column_name, string $data_type, int $data_type_length, $default_val = 'NULL' , $after = '') {
        if ($default_val === NULL) {
            $default_val = 'NULL';
        } else {
            $default_val = "'$default_val'";
        }
        if(isset($after)){
        
            $after_part  = ' AFTER '.$after;
        }
        else{
        
            $after_part = '';
        }
        $data_type = strtoupper($data_type);
        $query = "ALTER TABLE $table ADD $column_name $data_type( $data_type_length ) DEFAULT $default_val $after_part";
        return $this->custom_query_v2($table, $query, array(), TRUE, FALSE);
    }

    /**
     * db_migration
     * @param type $new_column_object
     * @return type
     */
    public function db_migration_add_new_columns($new_column_object) {
        $return = array();
        foreach ($new_column_object as $new_column) {
            $output = $this->add_new_column_to_table($new_column['table'], $new_column['column_name'], $new_column['data_type'], $new_column['data_type_length'], $new_column['default_val'], $new_column['after']);
            if (is_array($output) === TRUE) {
                $return[] = $new_column['table'] . ' ' . $new_column['column_name'] . ' success';
            } else {
                $return[] = $new_column['table'] . ' ' . $new_column['column_name'] . ' failed';
            }
        }
        return $return;
    }

}
