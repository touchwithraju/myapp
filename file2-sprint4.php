<?php

class Gallery extends MY_Controller {

    function index() {
        // check if users is logged in or not
        $this->output->set_header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
        $this->output->set_header("Pragma: no-cache");
        $arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
        if (isset($arr_user_details['id']) && !empty($arr_user_details['id'])) {
            $this->load->model('tag_model');
            $this->load->model('gallery_model');
            $data = array();
            $data['tags'] = $this->tag_model->getTags();
            $data['heirarchy'] = $this->tag_model->getTagHeirarchy();
            $data['master_data'] = $this->tag_model->getMasterData();
            $data['image_containers'] = $this->gallery_model->getContainerUrl();
            $data['act_page'] = 'gallery';
            $user_details = checkUserPerms($data['act_page']); // checking if permission exist
            $data['perms'] = $user_details['perms'];
            $data['user_perms'] = $user_details['user_perms'];
            $data['user_details'] = $user_details['user_details'];
            $data['gallery_flts'] = $this->gallery_model->get_state_maintanance($data['user_details']['id'], '1');
            $data['cart_view_data'] = $this->gallery_model->get_state_maintanance($data['user_details']['id'], '2', $json = false);
            $data['tag_permission'] = $this->gallery_model->tag_permission($arr_user_details['id']);
            $data['cart_view'] = $this->tag_avalible_prms($data['cart_view_data'], $data['tag_permission']);
            $data['from_page'] = $this->db_session->userdata('from_page');


            $this->load->view('gallery', $data);
        } else {
            redirect('login/loginview');
        }
    }

    function secLevel($id) {
        $total = array();
        $qry = "select h.parent_tag_id,h.child_tag_ref_id,h.child_tag_id,m.name from heirarchy h inner join master_data m on h.child_tag_id=m.id";
        $query_object = $this->db->query($qry);
        $res = $query_object->result();

        foreach ($res as $row) {
            $total[$row->parent_tag_id][$row->child_tag_ref_id][$row->child_tag_id] = $row->name;
        }

        //return $total;
        return isset($total[$id]) ? $total[$id] : array();
    }

    function firstLevel($id) {
        $nodes = $this->secLevel($id);
        if (count($nodes)) {
            $this->new2[$id] = $nodes;
            foreach ($nodes as $q) {
                foreach ($q as $a => $b) {
                    $this->firstLevel($a);
                }
            }
        }
    }

    function fetchHrcy($str_id = '29,30,31', $local = false) {
        $str_id = (isset($_POST['id']) ? $_POST['id'] : $str_id);
        $arr_comma_vals = explode(',', $str_id);
        foreach ($arr_comma_vals as $id) {
            $this->firstLevel($id);
            $y = $this->new2;
        }
        $new = array();
        foreach ($y as $p => $q) {
            foreach ($q as $a => $b) {
                foreach ($b as $l => $m) {
                    $new[$a][$l] = $m;
                }
            }
        }
        if ($local) {
            return $new;
        } else {
            //header('Content-type: application/json');
            return json_encode($new);
        }
    }

    function get_tag_details() {
        $this->load->model('gallery_model');
        $this->load->model('tag_model');

        $res = $this->gallery_model->getAttDetails($_POST['file_name']);
        $child = array();
        foreach ($res as $i => $id) {
            $child[$id] = $this->fetchHrcy($id, true);
        }

        $data['rs'] = $res;
        $data['child'] = $child;
        //header('Content-type: application/json');
        return json_encode($data);
    }

    function tag_avalible_prms($cart, $tag_perm) {
        $cart_view = array();
        if (!empty($tag_perm) && !empty($cart)) {
            $explode_cart = explode(',', $cart['sort_string']);

            // print_r($explode_cart);
            $explode_count = count($explode_cart);


            for ($i = 0; $i < $explode_count; $i++) {
                //echo $explode_cart[$i]; die;
                if (in_array($explode_cart[$i], $tag_perm)) {
                    $cart_view[] = $explode_cart[$i];
                }
            }

            $cart_view_data['sort_string'] = implode(',', $cart_view);
            return json_encode($cart_view_data);
        } else {
            return json_encode($cart_view);
        }
    }

    function get_gallery($start_val = 0, $limit_val = 10) {
        error_reporting(0);
        $this->db_session->set_userdata('from_page', 'cart_view');
        $jsonp_callback = isset($_GET['callback']) ? $_GET['callback'] : null;
        $this->load->model('gallery_model');
        $params = array();
        if (isset($_GET) && count($_GET)) {
            foreach ($_GET as $k => $prms) {
                if ($k != 'callback' && $k != 'fds') {
                    $params[$k] = implode(',', $prms);
                }
            }
        }
        $res = $this->gallery_model->get_gallery($start_val, $limit_val, $params);
        // storing filters in state maintanance
        $arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
        $this->gallery_model->save_state_maintanance($_GET['fds'], $arr_user_details['id'], '1');

        $response = new stdClass();
        //$response->time = 0.01415705680847168;
        $response->hits = $res['hits'];
        $response->facet_results = array("fields" => array(), "queries" => array());
        $response->warnings = array();
        //$response->request = array('start'=>(int)$start_val, 'limit'=>(int)$limit_val/4);
        $response->request = array('start' => (int) $start_val, 'limit' => (int) $limit_val);
        $response->results = $res['data'];
        $response->img_ds = $res['img_ds'];
        $result = json_encode($response);
        print $jsonp_callback ? "$jsonp_callback($result)" : $result;

        //echo json_encode($images);
    }

    //single file download

    function download($file, $folder = 'uploads') {
        $filename = $folder . "/" . $file;
        $this->db->select('id, attachment_rename,attachment_orig_name');
        $this->db->from('attachments');
        $this->db->where('attachment_rename', $file);
        $mask_name = $this->db->get()->row_array();
        $ext = explode(".", $file);
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . basename($mask_name['attachment_orig_name']));
        if ($ext[count($ext) - 1] == "zip")
            header("Content-Type: application/zip");
        else if ($ext[count($ext) - 1] == "xls" || $ext[count($ext) - 1] == "xlsx" || $ext[count($ext) - 1] == "csv")
            header("Content-Type: application/vnd.ms-excel");
        else if ($ext[count($ext) - 1] == "doc" || $ext[count($ext) - 1] == "docx")
            header("Content-Type: application/msword");
        else
            header("Content-type: application/octet-stream");

        header("Content-Transfer-Encoding: binary");
        header('Content-Length: ' . filesize($filename));
        readfile($filename);
        //redirect('gallery');
    }

    function album_download($file) {
        $source_path_img = base_url() . "htdocs/uploads/" . $file;
        //if(file_exists($source_path_img)){
        $fqry = 'SELECT a.attachment_rename, a.attachment_orig_name, a.group_col
                FROM attachments a
                WHERE a.attachment_rename=' . "'" . $file . "'";
        $fquery_object = $this->db->query($fqry);
        $fresult = $fquery_object->row_array();

        $qry = 'select attachment_rename, attachment_orig_name, cstatus, extension, file_rename, group_col, base_name AS title_name
                      from attachments 
                      where group_col=' . "'" . $fresult['group_col'] . "' group by attachment_orig_name";
        $query_object = $this->db->query($qry);
        $count = $query_object->num_rows();

        $result = $query_object->row_array();
        //$result = $query_object->result();
        if ($count <= 1) {
            if (strtolower($result['extension']) == "pdf") {
                $this->single_file_download($result['file_rename'], $result['attachment_orig_name'], $result['cstatus']);
            } else {
                $this->single_file_download($result['attachment_rename'], $result['attachment_orig_name'], $result['cstatus']);
            }
        } else {
            $this->multiple_file_download($result['extension'], $result['group_col'], $result['title_name'], $result['cstatus']);
        }
        //}else{

        /* //echo "<script>alert('unable to download it');</script>"; */


        //}
    }

    function single_file_download($re_name, $org_name, $status) {
        $this->load->helper('download');
        if ($status == '0') {
            $path = FCPATH . 'uploads/' . $re_name;
        } else {
            $path = "http://8f5430da4655da50b277-ccf9999e35faf61f9468bceda7c812f3.r39.cf1.rackcdn.com/" . $re_name;
        }
        $data = file_get_contents($path); // Read the file's contents
        $name = $org_name;
        force_download($name, $data);
    }

    function remove_folder($dirname) {
        if (is_dir($dirname))
            $dir_handle = opendir($dirname);
        if (!$dir_handle)
            return false;
        while ($file = readdir($dir_handle)) {
            if ($file != "." && $file != "..") {
                if (!is_dir($dirname . "/" . $file))
                    unlink($dirname . "/" . $file);
                else
                    delete_directory($dirname . '/' . $file);
            }
        }
        closedir($dir_handle);
        rmdir($dirname);
        return true;
    }

    function multiple_file_download($extension, $group_col, $titlename, $status) {
        set_time_limit(0);
        $distination_path = FCPATH . 'uploads/downloads/' . $titlename;
        if (file_exists($distination_path)) {
            $this->remove_folder($distination_path);
        }
        if ($status == "0") {
            $source_path = FCPATH . 'uploads/';
        } else {
            $source_path = 'http://8f5430da4655da50b277-ccf9999e35faf61f9468bceda7c812f3.r39.cf1.rackcdn.com/';
        }
        mkdir($distination_path, 0777);

        if (strtolower($extension) == "pdf") {

            $qry = 'select attachment_rename, attachment_orig_name, extension, file_rename, group_col
                      from attachments 
                      where group_col=' . "'" . $group_col . "' group by file_rename";
            $query_object = $this->db->query($qry);
            $result = $query_object->result();
            foreach ($result as $imagename) {
                copy($source_path . $imagename->file_rename, $distination_path . "/" . $imagename->attachment_orig_name);
            }
        } else {

            $qry = 'select attachment_rename, attachment_orig_name ,extension, file_rename ,group_col
                      from attachments 
                      where group_col=' . "'" . $group_col . "'";

            $query_object = $this->db->query($qry);
            $result = $query_object->result();
            foreach ($result as $imagename) {
                copy($source_path . $imagename->attachment_rename, $distination_path . "/" . $imagename->attachment_orig_name);
            }
        }
        $this->zip_album_download($titlename);
    }

    //ZIP album folder

    function zip_album_download($zip_name) {

        $path = FCPATH . 'uploads/downloads/' . $zip_name . "/";
        $this->load->library('zip');
        $this->zip->read_dir($path, false);
        $this->zip->archive($path);
        //$this->zip->read_file($path);
        //$this->zip->add_dir();
        $this->remove_folder($path);
        $this->zip->download($zip_name);
    }

    /* function album_download($file,$folder='uploads')
      {

      $qry = 'SELECT a.base_name AS title_name, a.group_col, a.extension, a.attachment_rename, a.attachment_orig_name
      FROM attachments a
      LEFT JOIN projects p ON p.id=a.project_id
      WHERE a.attachment_rename='."'".$file."'";

      $query_object=$this->db->query($qry);
      $result = $query_object->row_array();
      $group_query='select attachment_rename, attachment_orig_name
      from attachments
      where group_col='."'".$result['group_col']."' group by attachment_orig_name" ;
      $group_query_object=$this->db->query($group_query);
      $count = $group_query_object->num_rows();
      $distination_path = FCPATH.'uploads/'.$result['title_name'];
      $source_path = FCPATH.'uploads/';
      if (file_exists($distination_path)) {
      $this->zip_album_download($result['title_name']);
      } else {

      if($count<=1)
      {
      $this->load->helper('download');
      $explode_name = explode("_",$result['attachment_rename']);
      if(strtolower($result['extension']!='pdf')){
      $impload_name = $result['attachment_rename'];
      }else{
      // if($result['extension']!='pdf'){
      end($explode_name);
      unset($explode_name[key($explode_name)]);
      $impload_name = implode('_', $explode_name).".".$result['extension'];

      // }else{
      // $file_parts = pathinfo($result['attachment_rename']);
      //  $impload_name = $file_parts['filename'].".".$result['extension'];
      // }
      }
      $path = FCPATH.'uploads/'.$impload_name;
      $data = file_get_contents($path); // Read the file's contents
      $name = $result['attachment_orig_name'];
      force_download($name, $data);

      }else{
      mkdir($distination_path, 0777);
      $download_result = $group_query_object->result();
      if(strtolower($result['extension'])=='pdf' && $count>=2)
      {
      $qry_pdf ='select attachment_rename,extension, attachment_orig_name from attachments where group_col="'.$result['group_col'].'" group by attachment_orig_name';
      $group_pdf_object=$this->db->query($qry_pdf);
      $download_result = $group_pdf_object->result();
      foreach ($download_result as $imagename)
      {
      $explode_name = explode("_",$imagename->attachment_rename);
      end($explode_name);
      unset($explode_name[key($explode_name)]);
      $impload_name = implode('_', $explode_name).".".$imagename->extension;
      copy($source_path.$impload_name, $distination_path."/".$imagename->attachment_orig_name);
      }

      }else{

      foreach ($download_result as $imagename)
      {
      copy($source_path.$imagename->attachment_rename, $distination_path."/".$imagename->attachment_orig_name);
      }
      }

      $this->zip_album_download($result['title_name']);
      }

      }
      }
     */

    //single file Delete

    function filedeleate() {
        $file_orgname = $_POST['fid'];
        $this->db->select('id, attachment_rename');
        $this->db->from('attachments');
        $this->db->where('attachment_rename', $file_orgname);
        $query = $this->db->get()->row_array();
        $this->db->delete('attachments', array('id' => $query['id']));
        $this->db->delete('tag_attachments', array('attachments_id' => $query['id']));
        $source_path = FCPATH . "uploads/";
        $source_path_thumb = FCPATH . "uploads/thumbnail/";
        unlink($source_path . $query['attachment_rename']);
        unlink($source_path_thumb . $query['attachment_rename']);
        echo TRUE;
    }

    //Album  Delete
    function albumfiledeleate() {
//        include(APPPATH . 'libraries/cloudfiles/cloudfiles.php');
//        $username = "supervaluecore"; // username
//        $key = "96467cbcc93f524b0d3c55b37c9d3e56"; // api key
//        $auth = new CF_Authentication($username, $key);
//        $auth->authenticate();
//        $conn = new CF_Connection($auth);
//        $container1 = $conn->get_container('WR Gallery 1');
//        $container2 = $conn->get_container('WR Gallery 2');
        //local server paths
        $source_path = FCPATH . "uploads/";
        $source_path_thumb = FCPATH . "uploads/thumbnail/";
        $file_orgname = $_POST['fid'];
        $this->db->select('id,extension,file_rename,group_col');
        $this->db->from('attachments');
        $this->db->where('attachment_rename', $file_orgname);
        //$this->db->limit(1);
        $query = $this->db->get()->row_array();
        $this->load->model('gallery_model');
        $data['jsonfile_id'] = $this->gallery_model->get_allstate_maintanance('2');
        $filedetails = $data['jsonfile_id'];
        if (strtolower($query['extension']) == "pdf") {
            $pdfdelete_qry = "select file_rename,cstatus from attachments where group_col='" . $query['group_col'] . "' group by file_rename";
            $query_object_del_pdf = $this->db->query($pdfdelete_qry);
            $result_pdf_del = $query_object_del_pdf->result();
            foreach ($result_pdf_del as $del_pdf) {
                $del_pdf->file_rename;

                if ($del_pdf->cstatus == '0') {
                    unlink($source_path . $del_pdf->file_rename);
                } else {
//                    $container1->delete_object($del_pdf->file_rename);
                    //$container2->delete_object($del_pdf->file_rename);
                }
            }
            //$this->pdfFiledelete($source_path,$query['group_col']);
        }


        foreach ($filedetails as $fileids) {
            if (isset($fileids) && !empty($fileids)) {
                $data['file_id'] = $fileids->sort_string;
                $explode_fileid = explode(',', $data['file_id']);
                if (in_array($query['id'], $explode_fileid)) {
                    $uexplode_fileid = array_diff($explode_fileid, array($query['id']));
                    $implode_filearry = implode(',', $uexplode_fileid);
                    $this->gallery_model->save_state_maintanance($implode_filearry, $fileids->users_id, '2');
                }
            }
        }
        $qry = 'SELECT id,attachment_rename,cstatus,extension  FROM attachments 
			   WHERE group_col in(SELECT group_col FROM attachments WHERE attachment_rename="' . $file_orgname . '")';
        $query_object = $this->db->query($qry);
        $data = '';

        foreach ($query_object->result() as $row) {
            $data[] = $row->id;
            //check cloud server status
            if ($row->cstatus == '0') {
                unlink($source_path . $row->attachment_rename);
                if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
                    unlink($source_path_thumb . $row->attachment_rename);
                }
            } else {
//                $container1->delete_object($row->attachment_rename);
//                if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
//                    $container2->delete_object($row->attachment_rename);
//                }
            }
        }
        //delete attachments
        $this->db->where_in('id', $data);
        $this->db->delete('attachments');
        //delete tag attachments
        $this->db->where_in('attachments_id', $data);
        $this->db->delete('tag_attachments');
    }

    function pdfFiledelete($source_path, $group_col) {
        $pdfdelete_qry = "select file_rename,cstatus from attachments where group_col='" . $group_col . "' group by file_rename";
        $query_object_del_pdf = $this->db->query($pdfdelete_qry);
        $result_pdf_del = $query_object_del_pdf->result();
        foreach ($result_pdf_del as $del_pdf) {
            $del_pdf->file_rename;

            if ($del_pdf->cstatus == '0') {
                unlink($source_path . $del_pdf->file_rename);
            } else {
                $container1->delete_object($del_pdf->file_rename);
                //$container2->delete_object($del_pdf->file_rename);
            }
        }
    }

    function printimage() {
        $file_orgname = $_POST['fid'];
        $qry = 'SELECT CONCAT(p.project_name," - ",a.base_name) AS title_name
        FROM attachments a
        LEFT JOIN projects p ON p.id=a.project_id
        WHERE a.attachment_rename=' . "'" . $file_orgname . "'";
        $query_object = $this->db->query($qry);
        $result = $query_object->row_array();
        echo $result['title_name'];
    }

    //album print option
    function aprintimage() {
        $this->load->model('gallery_model');
        $image_containers = $this->gallery_model->getContainerUrl();
        $file_orgname = $_REQUEST['fid'];
        $qry = 'SELECT CONCAT(p.project_name," - ",a.base_name) AS title_name, a.group_col
        FROM attachments a
        LEFT JOIN projects p ON p.id=a.project_id
        WHERE a.attachment_rename=' . "'" . $file_orgname . "'";
        $query_object = $this->db->query($qry);
        $result = $query_object->row_array();
        // echo $result['title_name'];
        $group_query = 'select attachment_rename,cstatus from attachments where group_col=' . "'" . $result['group_col'] . "'";
        $group_query_object = $this->db->query($group_query);
        $print_result['img_name'] = $group_query_object->result();
        $print_markup = '';
        $print_markup .="<h3 align='center'>" . $result['title_name'] . "</h3>";
        foreach ($print_result['img_name'] as $ima_name) {
            //check cstatus
            if ($ima_name->cstatus != "0") {
                $dynscr = $image_containers['cloud_orginal']['container_path'] . "/" . $ima_name->attachment_rename;
            } else {
                $dynscr = site_url() . "uploads/" . $ima_name->attachment_rename;
            }
            $print_markup .="<div align='center' style='width:590px; height:840px'><img style='max-width:590px; max-height:840px;' src=" . $dynscr . "></div>";
        }
        echo $print_markup;
    }

    //download album
    // fetch childre from parent ids

    function cartview() {
        $this->load->model('gallery_model');
        $this->db_session->set_userdata('from_page', 'cart_view');

        $data = array();
        $data['act_page'] = 'gallery';
        $user_details = checkUserPerms($data['act_page']); // checking if permission exist
        $data['perms'] = $user_details['perms'];
        $data['user_perms'] = $user_details['user_perms'];
        $data['user_details'] = $user_details['user_details'];
        $arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
        if ($_POST) {
            $data['file_id'] = $_POST['file_id'];
            $save_cart = $this->gallery_model->save_state_maintanance($_POST['file_id'], $arr_user_details['id'], '2');
        } else {

            $data['cart_view_data'] = $this->gallery_model->get_state_maintanance($data['user_details']['id'], '2', $json = false);
            $data['tag_permission'] = $this->gallery_model->tag_permission($arr_user_details['id']);
            // $data['cart_view']='';
            $data['cart_view'] = $this->tag_avalible_prms($data['cart_view_data'], $data['tag_permission']);
            if (isset($data['cart_view']) && !empty($data['cart_view'])) {
                $fileids = json_decode($data['cart_view']);
            } else {
                $fileids = array();
            }
            if (isset($fileids->sort_string) && !empty($fileids->sort_string)) {

                $data['file_id'] = $fileids->sort_string;
            } else {
                $data['file_id'] = '';
                redirect('gallery');
            }
        }
        $data['image_containers'] = $this->gallery_model->getContainerUrl();
        $this->load->view('cart_view', $data);
    }

    function cartupdate() {
        if ($_POST) {

            $arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
            if (!$_POST['fid'] == '') {
                $save_data = implode(',', $_POST['fid']);
            } else {
                $save_data = '';
            }

            $this->load->model('gallery_model');
            $save_cart = $this->gallery_model->save_state_maintanance($save_data, $arr_user_details['id'], '2');
        }
    }

    public function ajaxcartview() {
        $this->load->model('gallery_model');
        $count_cartviwe = explode(',', $_POST['file_id']);
        $start = '';
        $limit = '';
        //$result = $this->account_model->getMails($this->session->userdata('email'), $limit, $offset);
        $start = ($_POST['page'] - 1) * $_POST['limit'];
        $limit = $_POST['limit'];
        //$slice_files = array_slice($count_cartviwe,$start,$limit,$limit);
        $res = $this->gallery_model->getCartView($_POST['file_id'], $start, $limit);
        $data['details'] = $res;
        $data['current_page'] = $_POST['page'];
        $data['file_id'] = $_POST['file_id'];
        $data['selectedids'] = $count_cartviwe;
        $data['image_containers'] = $this->gallery_model->getContainerUrl();
        echo $this->load->view('slider_content', $data, TRUE);
    }

}

/* End of file gallery.php */
/* Location: ./application/controllers/gallery.php */
