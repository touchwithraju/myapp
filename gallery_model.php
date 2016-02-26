<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Gallery_model extends CI_Model {

    var $tag_ref, $master_data, $c_status, $children;

    function __construct() {
        parent::__construct();
        $this->tag_ref = $this->getTagReference();
        $this->master_data = $this->getMasterData();
    }

    function get_state_maintanance($user_id=1, $type='1', $json=true) {
        if ($json) {
            $qry = 'SELECT id,sort_string from state_maintenance where filter_type="' . $type . '" AND users_id=' . $user_id;
            $this->db->query($qry);
            $query_object = $this->db->query($qry);
            $rs = $query_object->row_array();
            return json_encode($rs);
        } else {
            $qry = 'SELECT id,sort_string from state_maintenance where filter_type="' . $type . '" AND users_id=' . $user_id;
            $this->db->query($qry);
            $query_object = $this->db->query($qry);
            $rs = $query_object->row_array();
            return $rs;
        }
    }

    function getContainerUrl() {

        $container = array();
        $qry = "select name, container, cdn_base, is_image from resolutions";
        $query_object = $this->db->query($qry);
        $result = $query_object->result();
        foreach ($result as $data) {
            $container[$data->container] = array('name' => $data->name, 'container_path' => $data->cdn_base, 'isimage' => $data->is_image);
        }
        return $container;
    }

    function get_allstate_maintanance($type='1', $json=true) {
        $data = array();
        $qry = 'SELECT id,sort_string,users_id from state_maintenance where filter_type=' . $type;
        $this->db->query($qry);
        $query_object = $this->db->query($qry);
        $rs = $query_object->result();
        return $rs;
    }

    function save_state_maintanance($fds, $user_id=1, $type="1") {
        $arr_fds = explode(',', $fds);
        $str_fds = implode(',', $arr_fds);
        // update if exis
        $qry = 'SELECT id from state_maintenance where filter_type="' . $type . '" AND users_id=' . $user_id;
        $this->db->query($qry);
        $query_object = $this->db->query($qry);
        if ($query_object->result()) {
            if ($str_fds != '') {
                $qry = 'UPDATE state_maintenance SET `sort_string` = "' . $str_fds . '" WHERE filter_type="' . $type . '" AND `users_id`=' . $user_id;
            } else {
                $qry = 'DELETE FROM state_maintenance where filter_type="' . $type . '" AND `users_id`=' . $user_id;
            }
            $this->db->query($qry);
        } else {// insert if not exist
            if ($str_fds != '') {
                $qry = 'INSERT INTO state_maintenance (`users_id`,`sort_string`,`filter_type`) VALUES(' . $user_id . ',"' . $str_fds . '","' . $type . '")';
                $this->db->query($qry);
            }
        }
    }

    function getAlbumDetails($file_name='') {
        $data = array();
        if ($file_name != '') {
            $qry = 'SELECT a.id AS att_id,a.attachment_orig_name,a.attachment_rename
					FROM attachments a
					WHERE a.group_col = ( SELECT group_col FROM attachments WHERE attachment_rename="' . $file_name . '")';
        }
    }

    function getAttDetails($file_name = '') {
        $data = array();
        if ($file_name != '') {
            $qry = 'SELECT a.id AS att_id,tr.val_ref,p.project_name,a.group_col,ta.tag_value,a.base_name
					FROM attachments a
					LEFT JOIN projects p ON p.id=a.project_id
					LEFT JOIN tag_attachments ta ON ta.attachments_id=a.id
					LEFT JOIN tag_reference tr ON ta.tag_reference_id=tr.id
					WHERE a.attachment_rename="' . $file_name . '"';

            $query_object = $this->db->query($qry);
            $data['tags'] = array("tag_1" => 0, "tag_2" => 0, "tag_3" => 0, "tag_4" => 0, "tag_5" => 0, "tag_6" => 0, "tag_7" => 0, "tag_8" => 0, "tag_9" => 0);
            foreach ($query_object->result() as $row) {
                $data['tags'][$row->val_ref] = $row->tag_value;
                $data['project_name'] = filterStringDecode($row->project_name);
                $data['base_name'] = $row->base_name;
                $data['group_col'] = $row->group_col;
            }
        }
        return $data;
    }

    function getCartView($att_ids = '', $start = 0, $limit = 0) {
        $data = array();
        if ($att_ids != '') {
            // tagging details
            $qrytags = 'SELECT a.id AS att_id, tr.val_ref, md.name as tag_val
						FROM attachments a
						LEFT JOIN projects p ON p.id=a.project_id
						left join tag_attachments ta on ta.attachments_id=a.id
						left join master_data md on ta.tag_value=md.id
						left join tag_reference tr on md.tag_reference_id=tr.id WHERE a.group_col IN(
						SELECT group_col
						FROM attachments
						WHERE id IN(' . $att_ids . '))';
            $query_object_tags = $this->db->query($qrytags);
            $arr_tag_details = array();
            $tag_res = $query_object_tags->result();
            if ($tag_res) {
                foreach ($tag_res as $row) {
                    $arr_tag_details[$row->att_id][] = $row->tag_val;
                }
            }

            $qry = 'SELECT group_col
					FROM attachments
					WHERE id IN(' . $att_ids . ')
					ORDER BY tag_6 DESC,tag_7 DESC,tag_8 DESC,tag_5 ASC,tag_4 ASC,tag_3 ASC,tag_2 ASC,tag_1 ASC, base_name ASC
					LIMIT ' . $start . ',' . $limit;
            $query_object = $this->db->query($qry);
            $arr_group_col = array();
            foreach ($query_object->result() as $row) {
                $arr_group_col[] = '"' . $row->group_col . '"';
            }
            $str_group_col = implode($arr_group_col, ',');

            $qry = 'SELECT a.id,a.attachment_orig_name,a.extension,a.base_name,a.group_col,a.attachment_rename,p.project_name,a.cstatus
					FROM attachments a
					LEFT JOIN projects p ON p.id=a.project_id
					WHERE group_col IN(' . $str_group_col . ')
					ORDER BY a.tag_6 DESC,a.tag_7 DESC,a.tag_8 DESC,a.tag_5 ASC,a.tag_4 ASC,a.tag_3 ASC,a.tag_2 ASC,a.tag_1 ASC,p.project_name ASC, a.base_name ASC, a.attachment_orig_name ASC';
            //echo $qry;die;
            $query_object = $this->db->query($qry);

            foreach ($query_object->result() as $row) {
                $info = pathinfo($row->attachment_orig_name);
                if (isset($row->project_name) && !empty($row->project_name)) {
                    $tag_val_gen = $row->project_name . ' - ' . $row->base_name . ' - ' . implode($arr_tag_details[$row->id], ' &gt; ');
                } else {
                    $tag_val_gen = $row->base_name . ' - ' . implode($arr_tag_details[$row->id], ' &gt; ');
                }
                $data[$row->group_col][] = array('id' => $row->id,
                    'projectname' => filterStringDecode(strip_tags($row->project_name)),
                    'csatus' => $row->cstatus,
                    'rename' => $row->attachment_rename,
                    'extension' => $row->extension,
                    //'tag_val'=>$row->project_name.' - '.$info['filename'].' - '.implode($arr_tag_details[$row->id],' &gt; '),
                    'tag_val' => $tag_val_gen,
                    'org_name' => filterStringDecode(strip_tags($row->base_name)));
            }
        }

        return $data;
    }

    function get_gallery($start_val='', $limit_val='', $params = array()) {
        $stm = time();
        $join_qry = '';
        $arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
        $whr_qry = '1';
        if (!empty($params)) {
            $tag_ref = $this->tag_ref;
            $join_qry = '';
            $whr_val = '';
            $is_16 = false;
            $is_15 = false;

            foreach ($params as $tag => $val) {
                $ref_id = $tag_ref[$tag];
                $first_level = false;
                $alias = 'ta' . $ref_id;
                if ($ref_id == 12 || $ref_id == 13 || $ref_id == 14) {
                    $first_level = true;
                }
                if ($ref_id == 15) {
                    $is_15 = true;
                }


                $join_qry .= 'LEFT JOIN tag_attachments ' . $alias . ' ON (' . $alias . '.attachments_id=a.id AND ' . $alias . '.tag_reference_id=' . $ref_id . ')';

                if ($ref_id == 16) {
                    $whr_qry .= ' AND  (a.tag_5="0" || a.tag_5="" or ' . $alias . '.tag_value in(' . $val . '))';
                    $is_16 = true;
                } else {
                    $whr_qry .= ' AND  ' . $alias . '.tag_value in(' . $val . ')';
                }
            }
            if (($is_15 == true && $is_16 == false) || $first_level == true) {
                $whr_qry .= ' AND  (a.tag_5="0" || a.tag_5="") ';
            }
        }


        $upqry = "SELECT tp.tag_id
                        FROM tag_permissions tp
                        LEFT JOIN master_data md ON md.id=tp.tag_id
                        WHERE md.`status`='a' AND tp.user_id=" . $arr_user_details['id'];
        $up_query_object = $this->db->query($upqry);
        $up_result = $up_query_object->result_array();
        $up_permissions = array();
        foreach ($up_result as $per_result) {
            $up_permissions[] = $per_result['tag_id'];
        }
        $implode_perm = implode(',', $up_permissions);
        $count =0;
        if ($implode_perm != '') {
            $whr_qry .= ' and md.id in(' . $implode_perm . ') and (md.name=a.tag_2 or md.name=a.tag_3)';
        
          $qry = 'SELECT SQL_CALC_FOUND_ROWS md.id as mid,a.cstatus,a.attachment_orig_name,a.attachment_rename,p.project_name,a.id,a.base_name,a.group_col
				 FROM attachments a ' . $join_qry . '
				 left JOIN projects p ON p.id=a.project_id
                                 left join master_data md on (md.name=a.tag_3)
                                 WHERE ' . $whr_qry . '
				 GROUP BY a.group_col
				 ORDER BY a.tag_6 DESC,IF(a.tag_7=0,999999999,a.tag_7) DESC,IF(a.tag_8=0,999999999,a.tag_8) DESC,a.tag_2 ASC,a.tag_3 ASC,a.tag_4 ASC,a.tag_5 ASC,p.project_name ASC, a.base_name ASC, a.attachment_orig_name ASC';
     //die;
        $query_object = $this->db->query($qry);
        $count = $query_object->num_rows();
        // echo $qry;
          // die;
        }
        $data = array();
        $img_ds = array();
        if ($count) {

            $st = 4 * $start_val;
            $lt = 4 * $limit_val;
            $qry .= ' limit ' . $st . ',' . $lt;
          // echo $qry;
          // die;
            $query_object = $this->db->query($qry);

            $num_groupcol = $this->attachment_groupcol($query_object->result());

            $query_object_tags = $this->getCStatuses($num_groupcol);
            $arr_tag_details = array();
            $tag_res = $query_object_tags->result();

            if ($tag_res) {
                foreach ($tag_res as $row) {
                    if ($row->tag_val != '')
                        $arr_tag_details[$row->att_id][] = $this->string_library->getString($row->tag_val);
                }
            }

            $query_object_childs = $this->getChildren($num_groupcol);
            $arr_final_child = array();
            $cld_res = $query_object_childs->result();

            if ($cld_res) {
                foreach ($cld_res as $arr_child) {
                    $info = pathinfo($arr_child->attachment_orig_name);
                    if (isset($arr_child->project_name) && !empty($arr_child->project_name)) {
                        $org_name_gen = filterStringDecode(strip_tags($arr_child->project_name)) . " - " . filterStringDecode(strip_tags($arr_child->base_name));
                    } else {
                        $org_name_gen = filterStringDecode(strip_tags($arr_child->base_name));
                    }
                    if (isset($arr_tag_details[$arr_child->id])) {
                        $arr_final_child[$arr_child->group_col][] = array('filename' => $arr_child->attachment_rename,
                            'att_id' => $arr_child->id,
                            'tag_val' => implode($arr_tag_details[$arr_child->id], ' &gt; '),
                            'org_name' => filterStringDecode(strip_tags($org_name_gen)),
                            'projectname' => (filterStringDecode(strip_tags($arr_child->project_name)) != null ? filterStringDecode(strip_tags($arr_child->project_name)) : ''));
                    }
                }
            }

            // fetch all parent ids
            // fetch all child attachemnts for the abouve parent ids
            // prepare final array with parent attachments and child attachments

            if ($query_object->result()) {
                $i = 0;
                $cnt = 1;
                $rs = $query_object->result();
               
                $tot_rec = count($rs);
                $temp = array();
                foreach ($rs as $row) {
                    $temp['attachment_orig_name' . $cnt] = $row->attachment_rename;
                    $temp['cstatus' . $cnt] = $row->cstatus;
                    $file_nm = ($row->project_name != null ? filterStringDecode($row->project_name) : '');
                    $temp['id' . $cnt] = $row->id;
                    $img_ds[$row->id] = (!empty($arr_final_child[$row->group_col]) ? $arr_final_child[$row->group_col] : array());
                    //$img_ds[$row->id] = (!empty($arr_final_child[$row->base_name]) ? $arr_final_child[$row->base_name] : array());
                    $temp['fname' . $cnt] = $file_nm;
                    $parentinfo = pathinfo($row->attachment_orig_name);
                    $temp['attachment_fname' . $cnt] = ($file_nm != '' ? $file_nm . ' - ' : '') . $parentinfo['filename'];
                    //$temp['attachment_fname'.$cnt] = ($file_nm!=''?$file_nm.' - ':'').$row->base_name;
                    $temp['orgname' . $cnt] = $row->attachment_orig_name;
                    $temp['group_name' . $cnt] = $row->group_col;

                    $temp['proj_name' . $cnt] = filterStringDecode(strip_tags($row->project_name));
                    if ($cnt % 4 == 0) {
                        $data[$i] = array('item' => $temp, 'index' => $i);
                        $temp = array();
                        $i++;
                        $cnt = 0;
                    }
                    $cnt++;
                }
                if ($tot_rec % 4 > 0) {
                    $data[$i] = array('item' => $temp, 'index' => $i);
                }
            }
        }

        // echo  $hits_count = count($data['']);
        $result['hits'] = ceil($count / 4);
        $result['data'] = $data;
        /*echo '<pre>';
        print_r($result['data']);
        die;*/
        $result['img_ds'] = $img_ds;
        return $result;
    }

    function attachment_groupcol($group_col_array) {
        $group_col_string = '';
        if (!empty($group_col_array)) {
            $array_group_col = array();

            foreach ($group_col_array as $group_col_id) {
                $array_group_col[] = $group_col_id->group_col;
            }
            if (!empty($array_group_col)) {
                $group_col_string = implode("','", $array_group_col);
            }
        }
        return $group_col_string;
    }

    function getMasterData() {
        $tp_qry = "select SQL_CALC_FOUND_ROWS id,name from master_data where tag_reference_id='13' or tag_reference_id='14'";
        return $this->db->query($tp_qry);
    }

    function getCStatuses($gro_col) {
        if ($gro_col) {
            $qrytags = "SELECT SQL_CALC_FOUND_ROWS a.id AS att_id, a.cstatus, tr.val_ref, md.name as tag_val
                            FROM attachments a
                            LEFT JOIN projects p ON p.id=a.project_id
                            left join tag_attachments ta on ta.attachments_id=a.id
                            left join master_data md on ta.tag_value=md.id
                            left join tag_reference tr on md.tag_reference_id=tr.id
                            where a.group_col in('" . $gro_col . "')";
            return $this->db->query($qrytags);
        }
    }

    function getChildren($gro_col) {
        if ($gro_col) {
            $qrychildren = "SELECT SQL_CALC_FOUND_ROWS a.attachment_orig_name,a.attachment_rename,a.id,p.project_name,a.base_name,a.group_col
        FROM attachments a
        left JOIN projects p ON p.id=a.project_id
        where a.group_col in('" . $gro_col . "') 
        order by a.file_rename asc, a.order_number asc";

            return $this->db->query($qrychildren);
        }
    }

    function tag_permission($id) {
        $result = '';
        $up_qry = 'SELECT tp.tag_id
                   FROM tag_permissions tp
                   LEFT JOIN master_data md ON md.id=tp.tag_id
                   WHERE md.`status`="a" AND tp.user_id=' . $id;
        $up_query_obj = $this->db->query($up_qry);
        $qry_result_count = $up_query_obj->num_rows();
        $qry_result = $up_query_obj->result();
        if ($qry_result_count > 0) {
            foreach ($qry_result as $user_permission) {
                $up_data[] = $user_permission->tag_id;
            }
            $this->db->select('attachments_id');
            $this->db->from('tag_attachments');
            $this->db->where_in('tag_value', $up_data);
            //$this->db->where('tag_reference_id',13);
            $this->db->where('tag_reference_id', 14);
            //echo $this->db->last_query();
            // die;
            $per_obj = $this->db->get()->result();
            foreach ($per_obj as $fresult) {
                $result[] = $fresult->attachments_id;
            }
            return $result;
        } else {

            return $result;
        }

    }

    function getTagReference() {
        $qry = 'SELECT id,val_ref FROM tag_reference WHERE `status`="a"';
        $query = $this->db->query($qry);
        $data = array();
        if ($query->result()) {
            foreach ($query->result() as $row) {
                $data[$row->val_ref] = $row->id;
            }
        }
        return $data;
    }

}
