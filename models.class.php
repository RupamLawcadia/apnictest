<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class models {

    public function __construct($CO) {
        $this->CO = $CO;
    }

    private function datasource_id_from_company_id($id) {
        $data = $this->CO->database_connector->find_from_id('companies', $id, array('data_source_id'));
        return $data['data_source_id'];
    }

    private function datasource_ip_array_from_id($id) {
        $data = $this->CO->database_connector->find_from_id('data_sources', $id, array('ip', 'ip_2', 'ip_3', 'ip_4'));
        return $data;
    }

    public function find_company_id_from_user_id($user_id) {
        $data = $this->CO->database_connector->find_from_id('users', $user_id, array('company_id'));
        return $data['company_id'];
    }

    /**
     * Get Datasource IP from company ID
     * @param type $company_id
     * @return type
     */
    public function FIND_datasource_ip_array_from_company_id($company_id) {

        $datasource_id = $this->datasource_id_from_company_id($company_id);
        if ($datasource_id > 0) {
            return $this->datasource_ip_array_from_id($datasource_id);
        } else {
            $this->CO->errors_class->error_response(400, 'Invalid Company Id', 'No Remote Host Found');
        }
    }

    /**
     * Find user org unit with access level (Only Active ORG units will be removed)
     * @param type $user_id
     * @param type $org_unit_id (Optional) You can optionally validate single org unit and get info (Used for org unit filter)
     * @return type
     */
    public function find_user_org_units_with_access_level($user_id, $org_unit_id = '') {
        $where_object = array(
            (object) array(
                'key' => 'user_id',
                'compare' => '=',
                'value' => $user_id,
                'bool' => 'AND',
            ),
        );

        if ($org_unit_id != '' && $org_unit_id > 0) {
            array_push($where_object, (object) array(
                        'key' => 'org_unit_id',
                        'compare' => '=',
                        'value' => $org_unit_id,
                        'bool' => 'AND',
            ));
        }

        $data = $this->CO->database_connector->get_from_table('org_unit_users', '', array('org_unit_id', 'access_level'), $where_object, '');
        if (count($data) > 0) {
            $org_unit_simple_array = array();
            $org_unit_multi_d_array = array();
            foreach ($data as $value) {
                $org_unit_id_ = (int) $value['org_unit_id'];
                $org_unit_simple_array[] = $org_unit_id_;
                $org_unit_multi_d_array[$org_unit_id_] = (int) $value['access_level'];
            }

            $in_object_ = array('column' => 'id', 'item_array' => $org_unit_simple_array);

            $where_object2 = array(
                (object) array(
                    'key' => 'status',
                    'compare' => '=',
                    'value' => 'ACTIVE',
                    'bool' => 'AND'
                )
            );

            $org_unit_detailed = $this->CO->database_connector->mysql_in_v2('org_units', '', $in_object_, array('id'), $where_object2);
            if (count($org_unit_detailed) > 0) {

                $active_org_units = array();

                foreach ($org_unit_detailed as $value) {
                    $active_org_units[$value['id']] = $org_unit_multi_d_array[$value['id']];
                }

                return $active_org_units;
            } else {
                $this->CO->errors_class->error_response(400, 'no_active_org_units_found_for_this_user', 'No Active Org Units found for this user');
            }
        } else {
            $this->CO->errors_class->error_response(400, 'no_org_units_found_for_this_user', 'No Org Units are assigned to this user');
        }
    }

    /**
     * Find user Org Units (Only Active ones will be returned)
     * @param type $user_id
     * @return type
     */
    public function find_user_org_units($user_id) {
        $active_org_units = $this->find_user_org_units_with_access_level($user_id);
        $ou_array = array();
        foreach ($active_org_units as $ou_id => $access_level) {
            $ou_array[] = $ou_id;
        }
        return $ou_array;
    }

    public function getPlatformVersion() {
        $version = $this->CO->database_connector->find_from_id('SYSTEM_info', 1, array("info"), true);

        return $version["info"];
    }

    /**
     * is_company_have_access_to_domain
     * @param type $full_email
     * @return type
     */
    public function is_company_have_access_to_domain($full_email, $company_id) {
        $email_explode = explode('@', $full_email);

        $where_object = array(
            (object) array(
                'key' => 'email_domain',
                'compare' => '=',
                'value' => $email_explode[1],
                'bool' => 'AND'
            ), (object) array(
                'key' => 'company_id',
                'compare' => '=',
                'value' => $company_id,
                'bool' => 'AND'
            )
        );
        $dataset = $this->CO->database_connector->get_from_table('company_email_domains', '', array('company_id'), $where_object, 'LIMIT 1');

        if (isset($dataset[0]['company_id']) && $dataset[0]['company_id'] == $company_id) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * This function uses email hash to do the match. No NoSQL DB involvement
     * @param type $email
     * @return type
     */
    public function find_company_id_from_email($email) {
        //add to email_hash
        $email_hash = $this->CO->middleware_class->create_email_hash(trim(strtolower($email)));

        $where_object = array(
            (object) array(
                'key' => 'email_hash',
                'compare' => '=',
                'value' => $email_hash
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('users', '', array('company_id'), $where_object, 'LIMIT 1');
        if (isset($dataset[0]['company_id'])) {
            return $dataset[0]['company_id'];
        } else {
            return NULL;
        }
    }

    /**
     * Get Matter Org Units
     * @param int $matter_id Matter ID
     * @return Array Array of Org Units
     */
    public function get_matter_ous($matter_id) {
        $where_object = array(
            (object) array(
                'key' => 'matter_id',
                'compare' => '=',
                'value' => $matter_id
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('client_matter_org_units', '', array('org_unit_id'), $where_object, '');
        $org_unit_array = array();
        foreach ($dataset as $value) {
            $org_unit_array[] = (int) $value['org_unit_id'];
        }
        return $org_unit_array;
    }

    /**
     * 
     * @param type $matter_id
     */
    public function validate_matter_id($matter_id) {
        $where_object = array(
            (object) array(
                'key' => 'id',
                'compare' => '=',
                'value' => $matter_id,
                'bool' => 'AND'
            ,
            ),
            (object) array(
                'key' => 'status',
                'compare' => '!=',
                'value' => 'deleted',
                'bool' => 'AND'
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('matters', '', array('id'), $where_object, '', FALSE);
        if (count($dataset) <= 0) {
            $this->CO->errors_class->error_response(403, 'access_denied', 'User not allowed to access this resource');
        } else {
            return true;
        }
    }

    /**
     * Get Lawyer Org Units
     * @param int $lawyer_user_id Lawyer User ID
     * @return Array Array of Org Units
     */
    public function get_lawyer_ous($lawyer_user_id) {
        $where_object = array(
            (object) array(
                'key' => 'user_id',
                'compare' => '=',
                'value' => $lawyer_user_id
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('org_unit_users', '', array('org_unit_id'), $where_object, '');
        $org_unit_array = array();
        foreach ($dataset as $value) {
            $org_unit_array[] = (int) $value['org_unit_id'];
        }
        return $org_unit_array;
    }

    /**
     * Get Tender Org Units
     * @param int $tender_id Tender ID
     * @return Array Array of Org Units
     */
    public function get_tender_ous($tender_id) {
        $where_object = array(
            (object) array(
                'key' => 'tender_id',
                'compare' => '=',
                'value' => $tender_id
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('tender_org_units', '', array('org_unit_id'), $where_object, '');
        $org_unit_array = array();
        foreach ($dataset as $value) {
            $org_unit_array[] = (int) $value['org_unit_id'];
        }
        return $org_unit_array;
    }

    /**
     * Get Work Scope Org Units
     * @param int $work_scope_id Work Scope ID
     * @return Array Array of Org Units
     */
    public function get_work_scope_ous($work_scope_id) {
        $where_object = array(
            (object) array(
                'key' => 'work_scope_id',
                'compare' => '=',
                'value' => $work_scope_id
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('work_scope_lawyer_org_units', '', array('org_unit_id'), $where_object, '');
        $org_unit_array = array();
        foreach ($dataset as $value) {
            $org_unit_array[] = (int) $value['org_unit_id'];
        }
        return $org_unit_array;
    }

    /**
     * Get Panel Org Units
     * @param int $panel_id Panel ID
     * @return Array Array of Org Units
     */
    public function get_panel_ous($panel_id) {
        $where_object = array(
            (object) array(
                'key' => 'panel_id',
                'compare' => '=',
                'value' => $panel_id
            ),
        );
        $dataset = $this->CO->database_connector->get_from_table('panel_org_units', '', array('org_unit_id'), $where_object, '');
        $org_unit_array = array();
        foreach ($dataset as $value) {
            $org_unit_array[] = (int) $value['org_unit_id'];
        }
        return $org_unit_array;
    }

    /**
     * Get Matter IDs (Index)
     * @param int $org_units Org units
     * @return Array Array of Org Units
     */
    public function get_matter_ids($org_units) {

        $orgs = explode(',', $org_units);
        //validate org unit array if active or not
        $org_units_array = $this->CO->middleware_class->validate_org_unit_array($orgs);

//        $org_units_array = explode(',', $org_units_checked);

        $dataset = $this->CO->database_connector->mysql_in('client_matter_org_units', '', 'org_unit_id', $org_units_array, array('matter_id'));

        $matter_id_array = array();
        foreach ($dataset as $value) {
            $matter_id_array[] = (int) $value['matter_id'];
        }
        return $matter_id_array;
    }

    /**
     * Get Matter IDs (Index)
     * @param int $org_units Org units
     * @return Array Array of Org Units
     */
    public function get_work_scope_ids($org_units) {

        $orgs = explode(',', $org_units);
        //validate org unit array if active or not
        $org_units_array = $this->CO->middleware_class->validate_org_unit_array($orgs);

//        $org_units_array = explode(',', $org_units_checked);

        $dataset = $this->CO->database_connector->mysql_in('work_scope_lawyer_org_units', '', 'org_unit_id', $org_units_array, array('work_scope_id'));

        $work_scope_id_array = array();
        foreach ($dataset as $value) {
            $work_scope_id_array[] = (int) $value['work_scope_id'];
        }
        return $work_scope_id_array;
    }

    /**
     * Get Tender IDs (index)
     * @param int $org_units Org units
     * @return Array Array of Org Units
     */
    public function get_tender_ids($org_units) {

        $orgs = explode(',', $org_units);

        //validate org unit array if active or not
        $org_units_array = $this->CO->middleware_class->validate_org_unit_array($orgs);

//        $org_units_array = explode(',', $org_units_checked);

        $dataset = $this->CO->database_connector->mysql_in('tender_org_units', '', 'org_unit_id', $org_units_array, array('tender_id'));

        $tender_id_array = array();
        foreach ($dataset as $value) {
            $tender_id_array[] = (int) $value['tender_id'];
        }
        return $tender_id_array;
    }

    /**
     * 
     * @param type $org_units
     * @return type
     */
    public function get_tender_index_ids($org_units) {

        //validate org unit array if active or not
        $org_units_array = $this->CO->middleware_class->validate_org_unit_array($org_units);

        $in_object = array('column' => 'org_unit_id', 'item_array' => $org_units_array);
        $dataset = $this->CO->database_connector->mysql_in_v2('tender_org_units', '', $in_object, array('tender_id', 'cleared_conflicts_yn'), '', '');

        return $dataset;
    }

    /**
     * Get Tender IDs (index)
     * @param int $org_units Org units
     * @return Array Array of Org Units
     */
    public function get_tender_ids_without_responses($org_units, $company_id) {

//        $orgs = explode(',', $org_units);
        //validate org unit array if active or not
        $org_units = $this->CO->middleware_class->validate_org_unit_array($org_units);


        $tender_response_query = "SELECT b.tender_id FROM tender_responses b where b.lawfirm_id= :lawfirm_id and (b.status='pending_approval' or b.status='accepted' ) and NOT(tender_type='marketplace')";
        $bind_array = array('lawfirm_id' => $company_id);
        $tender_responses = $this->CO->database_connector->custom_query_v2('tender_responses', $tender_response_query, $bind_array, TRUE);
        if (count($tender_responses) > 0) {
            foreach ($tender_responses as $tender_response) {
                foreach ($tender_response as $key => $value) {
                    $tender_response_ids_array[] = $value;
                }
            }
            $in_object_ = (array) array('column' => 'tender_id', 'item_array' => $tender_response_ids_array);

            $loop_count = 1;
            $in_object_count = count($in_object_['item_array']);
            foreach ($in_object_['item_array'] as $in_item) {

                $this_open_bracket = '';
                $this_close_bracket = '';
                $this_bool = 'AND';


                if ($loop_count === 1) {
                    $this_open_bracket = '(';
                } elseif ($loop_count === $in_object_count) {
                    $this_close_bracket = ')';
                    $this_bool = 'AND';
                }
                if ($in_object_count === 1) {
                    $this_open_bracket = '(';
                    $this_close_bracket = ')';
                }

                $where_object[] = (object) array(
                            'key' => $in_object_['column'],
                            'compare' => '!=',
                            'value' => $in_item,
                            'open_bracket' => $this_open_bracket,
                            'bool' => $this_bool,
                            'close_bracket' => $this_close_bracket
                );

                $loop_count++;
            }
        } else {
            $where_object = "";
        }

//        var_dump($where_object);
        $in_object = array('column' => 'org_unit_id', 'item_array' => $org_units);
        $dataset = $this->CO->database_connector->mysql_in_v2('tender_org_units', '', $in_object, array('tender_id', 'cleared_conflicts_yn'), $where_object, '');

        return $dataset;
    }

    /**
     * Get Panel IDs (index)
     * @param int $org_units Org Units
     * @return Array Array of Org Units
     */
    public function get_panel_ids($org_units) {

        $orgs = explode(',', $org_units);

        //validate org unit array if active or not
        $org_units_array = $this->CO->middleware_class->validate_org_unit_array($orgs);

//        $org_units_array = explode(',', $org_units);

        $dataset = $this->CO->database_connector->mysql_in('panel_org_units', '', 'org_unit_id', $org_units_array, array('panel_id'));

        $matter_id_array = array();
        foreach ($dataset as $value) {
            $matter_id_array[] = (int) $value['panel_id'];
        }
        return $matter_id_array;
    }

    /**
     * Map IDs with actual values // Depends on "$table_mapping_obj". Note - It is required to add tables in to "$table_mapping_obj". Otherwise this method does not work
     * @global type $table_mapping_obj
     * @param array $data_obj Data Object as and array or single object
     * @param array $ids_to_map List of ids that needs to be mapped
     * @return array Array of mapped objects
     */
    public function map_ids($data_obj, $ids_to_map) {
        return $this->CO->table_mapper->map_ids($data_obj, $ids_to_map);
    }

    /**
     * Make sure data source is non dedicated (This is extra layer of security to to avoid accidental invalid data source assignments)
     * @param type $data_source_id
     */
    public function is_data_source_shared($data_source_id) {
        $data = $this->CO->database_connector->find_from_id('data_sources', $data_source_id, array('dedicated_to'));
        if (isset($data['dedicated_to']) && $data['dedicated_to'] < 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Find default ORG UNIT of a company
     * @param type $company_id
     * @return type
     */
    public function find_default_OU_of_a_company($company_id) {
        $where_object = array(
            (object) array(
                'key' => 'company_id',
                'compare' => '=',
                'value' => $company_id,
                'bool' => 'AND'
            ), (object) array(
                'key' => 'is_default_OU',
                'compare' => '=',
                'value' => 1,
                'bool' => 'AND'
            )
        );

        $dataset = $this->CO->database_connector->get_from_table('org_units', '', array('id'), $where_object, 'LIMIT 1');
        if (isset($dataset[0]['id'])) {
            return $dataset[0]['id'];
        } else {
            return NULL;
        }
    }

    /**
     * Assign ORG Unit to a user
     * @param type $user_id
     * @param type $org_unit_id
     * @param type $own_all_permissions ALL = 1/OWN = 0
     * @return type
     */
    public function assign_org_unit_to_a_user($user_id, $org_unit_id, $own_all_permissions = 0) {
        //validate org unit if active or not
        $this->CO->middleware_class->validate_org_unit_id($org_unit_id);

        return $this->CO->database_connector->insert_to_table('org_unit_users', array('user_id' => $user_id, 'org_unit_id' => $org_unit_id, 'access_level' => $own_all_permissions, 'created_at' => time()), FALSE);
    }

    public function conflict_check($tender_id, $org_unit_array) {

        //validate org unit array if active or not
        $org_unit_array = $this->CO->middleware_class->validate_org_unit_array($org_unit_array);

        $in_object = array('column' => 'org_unit_id', 'item_array' => $org_unit_array);
        $where_object = array(
            (object) array(
                'key' => 'tender_id',
                'compare' => '=',
                'value' => $tender_id,
                'bool' => 'AND',
            ),
        );
        return $this->CO->database_connector->mysql_in_v2('tender_org_units', '', $in_object, array('cleared_conflicts_yn', 'org_unit_id'), $where_object, '');
//        return $this->CO->database_connector->custom_query('tender_org_units', $query, TRUE);
    }

    public function existing_tender_response($tender_id, $org_unit_array) {
        //validate org unit array if active or not
        $org_unit_array = $this->CO->middleware_class->validate_org_unit_array($org_unit_array);

        $in_object = array('column' => 'org_unit_id', 'item_array' => $org_unit_array);
        $users = $this->CO->database_connector->mysql_in_v2('org_unit_users', 'DISTINCT', $in_object, array('user_id'), '', '');
        foreach ($users as $user) {
            foreach ($user as $key => $value) {
                $created_by[] = $value;
            }
        }

        $in_object_ = array('column' => 'created_by', 'item_array' => $created_by);
        $where_object = array(
            (object) array(
                'key' => 'tender_id',
                'compare' => '=',
                'value' => $tender_id,
                'bool' => 'AND',
            ),
            (object) array(
                'key' => 'status',
                'compare' => '=',
                'value' => 'new',
                'bool' => 'AND',
            ),
        );
        return $this->CO->database_connector->mysql_in_v2('tender_responses', '', $in_object_, array('id'), $where_object, '');
    }

    public function existing_draft_tender_response($tender_id, $org_unit_array) {

        //validate org unit array if active or not
        $org_unit_array = $this->CO->middleware_class->validate_org_unit_array($org_unit_array);

        $in_object = array('column' => 'org_unit_id', 'item_array' => $org_unit_array);
        $users = $this->CO->database_connector->mysql_in_v2('org_unit_users', 'DISTINCT', $in_object, array('user_id'), '', '');
        foreach ($users as $user) {
            foreach ($user as $key => $value) {
                $created_by[] = $value;
            }
        }

        $in_object_ = array('column' => 'created_by', 'item_array' => $created_by);
        $where_object = array(
            (object) array(
                'key' => 'tender_id',
                'compare' => '=',
                'value' => $tender_id,
                'bool' => 'AND',
            ),
            (object) array(
                'key' => 'status',
                'compare' => '=',
                'value' => 'draft',
                'bool' => 'AND',
            ),
        );
        return $this->CO->database_connector->mysql_in_v2('tender_responses', '', $in_object_, array('id'), $where_object, '');
    }

    /**
     * Find given user role is allowed for a given company
     * @param int $company_id Company ID
     * @param int $user_role_id User Role ID (Eg - Superadmin -4. Client- 2)
     * @return boolean
     */
    public function is_user_role_allowed_for_the_company($company_id, $user_role_id) {
        //Find Company Role
        $user_role_id = (int) $user_role_id;
        $company_data = $this->CO->database_connector->find_from_id('companies', $company_id, array('company_role'));
        $user_role_data = $this->CO->database_connector->find_from_id('roles', $user_role_id, array('allowed_company_role'));
        if ($user_role_id > 0 && $company_data['company_role'] === $user_role_data['allowed_company_role']) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Get Company ID from item type
     * @param string $type matters/tenders/invoices/tender_responses
     * @param int $id item ID (Eg - matter ID/tender ID)
     * @return int Company ID
     */
    public function get_company_id_from_item_type($type, $id) {
        //Allowed attachment types
        $types = ['matters', 'tenders', 'tender_responses', 'invoices'];
        if (!in_array($type, $types)) {
            $this->CO->errors_class->error_response(400, 'invalid_item_type', 'Invalid Item Type', 'error', 'Only ' . implode('/', $types) . ' types allowed');
        }
        $company_id = $this->CO->database_connector->find_from_id($type, $id, array('company_id'), FALSE);
        return $company_id['company_id'];
    }

    /**
     * Log User Activity - Be extremely careful with different types of company IDs
     * @param type $this_company_id This company ID from header data (Who is doing the action)
     * @param type $information_owner_company_id Who owns this information. Destination NoSQL storage's company ID
     * @param type $user_id This user ID from header data
     * @param type $activity_type Eg - 'file_download','lawfirm_engage'
     * @param type $item_type Eg - matter/tender
     * @param type $item_id Eg - matter ID/tender ID
     * @param type $action_data - Any custom data Eg - Payload of form edit (Goes to NoSQL env)
     */
    public function log_activity($this_company_id, $information_owner_company_id, $user_id, $activity_type, $item_type, $item_id, $action_data = array()) {

        $CF_ray_city = explode('-', $_SERVER["HTTP_CF_RAY"]);
        if (!isset($CF_ray_city[1])) {
            $CF_ray_city[1] = '--';
        }

        global $CO;
        $sql_data_array = array(
            'done_by' => $user_id,
            'timestamp' => time(),
            'request_came_from' => $_SERVER["HTTP_CF_IPCOUNTRY"],
            'ip_address' => $_SERVER["HTTP_CF_CONNECTING_IP"],
            'activity_type' => $activity_type,
            'item_type' => $item_type,
            'item_id' => $item_id,
            'CF_RAY' => $CF_ray_city[1],
            'company_id' => $information_owner_company_id,
            'company_id_of_action_owner' => $this_company_id
        );
        return $CO->hybrid_db->insert_into_hybrid_db($information_owner_company_id, 'activity_history', $sql_data_array, $action_data);
    }

    /**
     * Validate org unit array against company_id
     * @param type $company_id
     * @param type $org_unit_array
     */
    public function validate_org_unit_array_against_company_id($company_id, $org_unit_array) {
        $in_object = array('column' => 'id', 'item_array' => $org_unit_array);
        $where_object = array(
            (object) array(
                'key' => 'status',
                'compare' => '=',
                'value' => 'ACTIVE',
                'bool' => 'AND',
            ),
        );
        $data = $this->CO->database_connector->mysql_in_v2('org_units', '', $in_object, array('company_id'), $where_object, '');

        $company_id_ = (int) $company_id;
        foreach ($data as $items) {
            $company_id_new = (int) $items['company_id'];
            if ($company_id_ !== $company_id_new || $company_id_ < 1) {
                return FALSE;
            }
        }
        //No False found
        return TRUE;
    }

    /**
     * get_user_info_from_user_id
     * @param type $company_id
     * @param type $user_id
     * @return type
     */
    public function get_user_info_from_user_id($company_id, $user_id) {
        $data = $this->CO->hybrid_db->get_from_hybrid_db($company_id, "users", $user_id, array('*'));
        if ($data->company_id != $company_id) {
            return NULL;
        } else {
            return $data;
        }
    }

    /**
     * Request Rest Password Email
     * @param type $email
     * @return boolean
     */
    public function request_rest_password_email($email) {
        //Is user valid
        $user_info = $this->CO->middleware_class->get_user_info_from_email_v2($email);

        //Trigger Email
        if ($user_info != NULL && $user_info->id > 0) {

            //Add timestamp
            $user_info->time_requested = time();
            $user_info->request_type = 'password_reset';
            //Create token
            $token = ($this->CO->middleware_class->create_auth_token($user_info, TOKEN_SALT));
            $URLHash = '#reset_password?token=' . $token;

            //Send password instantly (Do not add to the queue)
            $this->CO->ext_notifications->send_email_now_v2(array($email), 'Password Reset', 'Password Reset', 'We\'ve been notified that you have requested to reset your Lawcadia account password. Please use the link below to action this request. This link is only valid for 24 hours', 'Reset Password', $URLHash);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Welcome email
     * @param type $email
     * @return boolean
     */
    public function send_welcome_email($email) {
        //Is user valid
        $user_info = $this->CO->middleware_class->get_user_info_from_email_v2($email);

        //Trigger Email
        if ($user_info != NULL && $user_info->id > 0) {

            //Add timestamp
            $user_info->time_requested = time();
            $user_info->request_type = 'welcome_email';
            //Create token
            $token = ($this->CO->middleware_class->create_auth_token($user_info, TOKEN_SALT));
            $URLHash = '#reset_password?token=' . $token;

            //Send password instantly (Do not add to the queue)
            $this->CO->ext_notifications->send_email_now_v2(array($email), 'Lawcadia - New Account Setup', 'Welcome to Lawcadia - New Account Setup', 'Welcome to Lawcadia. A new account has been created for you.  Please finish your account setup by using the link below to set your initial password. (This link is only valid for 7 days)', 'Finish Account Setup', $URLHash);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * add_to_system_notification
     * @param type $type
     * @param type $input_payload
     * @param type $output_payload
     * @return type
     */
    public function add_to_system_notification($type, $input_payload, $output_payload, $additional_info = '') {

        if (is_array($input_payload) || is_object($input_payload)) {
            $input_payload = json_encode($input_payload);
        }

        if (is_array($output_payload) || is_object($output_payload)) {
            $output_payload = json_encode($output_payload);
        }

        if (is_array($additional_info) || is_object($additional_info)) {
            $additional_info = json_encode($additional_info);
        }

        $backtrace = debug_backtrace(FALSE);

        //Capture endpoint data
        $column_value_array = array(
            'type' => $type,
            'debug_info' => json_encode($backtrace),
            'input_payload' => $input_payload,
            'output_payload' => $output_payload,
            'time' => time(),
            'additional_info' => $additional_info
        );
        return $this->CO->database_connector->insert_to_table('system_notifications', $column_value_array, FALSE);
    }

    /**
     * get_validated_org_unit_array_against_company_id
     * @param type $company_id
     * @param type $org_unit_array
     * @return type
     */
    public function get_validated_org_unit_array_against_company_id($company_id, $org_unit_array) {
        $in_object = array('column' => 'id', 'item_array' => $org_unit_array);
        $where_object = array(
            (object) array(
                'key' => 'status',
                'compare' => '=',
                'value' => 'ACTIVE',
                'bool' => 'AND',
            ), (object) array(
                'key' => 'company_id',
                'compare' => '=',
                'value' => $company_id,
                'bool' => 'AND',
            )
        );
        $data = $this->CO->database_connector->mysql_in_v2('org_units', '', $in_object, array('id'), $where_object, '');

        $new_org_unit_array = array();

        foreach ($data as $value) {
            $new_org_unit_array[] = $value['id'];
        }

        return $new_org_unit_array;
    }

}
