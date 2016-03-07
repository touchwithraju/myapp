<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Test_cron extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Load the required classes
        $this->load->model(array('uploader_model'));
    }

    function index() {
        //$rstatus = $this->uploader_model->run_cronstatus();
        //if($rstatus==0){
        set_time_limit(0);
        ini_set('memory_limit', '900M');
        log_message('error', 'cron started');
        $get_catachments = $this->db->query("select * from cloud_attachments where attachment_rename='rwI7B2_1414486731.pdf'");
        if ($get_catachments->num_rows() > 0) {
            putenv("PATH=/usr/local/bin:/usr/bin:/bin");
            $row_dateVise = $get_catachments->row();
            echo '<pre>';
            print_r($row_dateVise);
            $this->uploader_model->update_cloudstatus($row_dateVise->id, '1');
            echo $orginal_path = FCPATH . 'uploads/cloud_files/';
           echo "<br/>". $thumbnail_path = FCPATH . 'uploads/cloud_files/thumbnail/';
            $file_extention = ".jpg";
            $iparray['post'] = array();
            $additional = array();
            $iparray['post'] = array(
                'tag_1' => $row_dateVise->tag_1,
                'tag_2' => $row_dateVise->tag_2,
                'tag_3' => $row_dateVise->tag_3,
                'tag_4' => $row_dateVise->tag_4,
                'tag_5' => $row_dateVise->tag_5,
                'tag_6' => $row_dateVise->tag_6,
                'tag_7' => $row_dateVise->tag_7,
                'tag_8' => $row_dateVise->tag_8
            );
            $additional = array('group_col' => $row_dateVise->group_col,
                'extension' => $row_dateVise->extension,
                'ipaddress' => $row_dateVise->ipaddress,
                'cstatus' => 1,
                'user_id' => $row_dateVise->created_by,
                'search_name' => $row_dateVise->base_name,
                'ipaddress' => $row_dateVise->ipaddress,
                'attachment_orig_name' => $row_dateVise->attachment_orig_name,
                'project_id' =>$row_dateVise->project_id
            );
            echo "<br/>".$orginal_path . $row_dateVise->attachment_rename;
           
            if (file_exists($orginal_path.$row_dateVise->attachment_rename) && strtolower($row_dateVise->extension) == 'pdf') {
                echo "<br/>before duplicate";
                $this->deleteDuplicate($row_dateVise->group_col, $row_dateVise->attachment_orig_name);
                 echo "<br/>After duplicate";
                $path_parts = pathinfo($row_dateVise->attachment_rename);
                echo "<br/>". $file_nm = $path_parts['filename'];
                echo "<br/>". $folder_path = $orginal_path . $file_nm;
                echo "<br/>". $file_path = $orginal_path . $row_dateVise->attachment_rename;
                if (!is_dir($folder_path)) {
                mkdir($folder_path, 0777);
                }
                $pdfimg_path = $folder_path . "/" . $file_nm . $file_extention;
                // RGB 1.pdf 1.jpg
                 // text123.pdf text.jpg
                exec("identify -format %n $file_path",$x,$y);
                $density='';
                if($x['0']<50)
                {
                   $density='-density 300x300';  
                }
                  
                exec("convert -background white -alpha remove  -strip -limit memory 128 -limit map 256  -quality 90% -resize 1200x ".$density."  -colorspace RGB " . $file_path . " " . $pdfimg_path);
                $dummy_folder_files = scandir($folder_path, 1);
                $dummy_folder_files_count = count($dummy_folder_files) - 2;
                $dummy_folder_files_count_custom = $dummy_folder_files_count - 1;
                for ($i = 0; $i <= $dummy_folder_files_count_custom; $i++) {
                    $order_num = $i + 1;
                    if ($dummy_folder_files_count == 1) {
                        $rename = $file_nm . $file_extention;
                        $srename = $file_nm . $file_extention;
                    } else {
                        $rename = $file_nm . "_" . $order_num . $file_extention;
                        $srename = $file_nm . "-" . $i . $file_extention;
                    }
                    rename($folder_path . "/" . $srename, $orginal_path . $rename);
                    $source_pdf_img_path = $orginal_path . $rename;
                    $distination_pdf_img_path = $thumbnail_path . $rename;
                    exec("convert -background white -alpha remove  -strip -limit memory 128 -limit map 256  -quality 90% -resize 300x  ".$density."  -colorspace RGB " . $source_pdf_img_path . " " . $distination_pdf_img_path);
                    $iparray['file_details'][] = array('attachment_orig_name' => $row_dateVise->attachment_orig_name,
                        'order_number' => $order_num,
                        'attachment_rename' => $rename,
                        'rename' => $row_dateVise->attachment_rename,
                        'ipaddress' => $row_dateVise->ipaddress
                    );
                    if ($dummy_folder_files_count_custom == $i) {
                        rmdir($folder_path);
                    }
                }

                /* echo '<pre>';
                  print_r($iparray);
                  print_r($additional);
                  die; */
              $cloudto = $this->movingTocloud($iparray['file_details'], $row_dateVise->attachment_rename, $row_dateVise->extension);
                $this->uploader_model->delete_duplicateatt($row_dateVise->attachment_id);
                $file_save = $this->inserAttachmentpdf($iparray, $row_dateVise->attachment_orig_name, $additional);
                
                if ($file_save) {
                    echo "sucess";
                    $this->uploader_model->update_cloudstatus($row_dateVise->id, '2');
                    // $this->movingTocloud();
                } else {
                      echo "fail";
                    $this->uploader_model->update_cloudstatus($row_dateVise->id, '3');
                   
                }
            } elseif(file_exists($orginal_path.$row_dateVise->attachment_rename)) {
                echo "<br/>".$orginal_path . $row_dateVise->attachment_rename;
                $iparray['file_details'] = array('attachment_orig_name' => $row_dateVise->attachment_orig_name,
                                                 'rename' => $row_dateVise->attachment_rename,
                                                 'attachment_rename' => $row_dateVise->attachment_rename,
                                                 'ipaddress' => $row_dateVise->ipaddress
                );
              $cloudto = $this->movingTocloud(array($iparray['file_details']), $row_dateVise->attachment_rename, $row_dateVise->extension);
               $this->uploader_model->delete_duplicateatt($row_dateVise->attachment_id);
               $file_save = $this->inserAttachment($iparray, $additional);
               if ($file_save) {
                    echo "sucess";
                    $this->uploader_model->update_cloudstatus($row_dateVise->id, '2');
                    // $this->movingTocloud();
                } else {
                      echo "fail";
                    $this->uploader_model->update_cloudstatus($row_dateVise->id, '3');
                   
                }
            }else{
                echo "file is not exist";
            }
            // $this->uploader_model->set_cronstatus($row_dateVise->id,2);  
            // }
        }
    } 
    function inserAttachment($iparray, $additional) {
        $file_details = $iparray['file_details'];
        $post = $iparray['post'];
        // frame tag_array
        $arr_tg = array();
        foreach ($post as $tag => $val) {
            if ($tag != 'project_name' && !empty($val)) {
                $arr_tg[$tag] = $val;
            }
        }
        // seperate extension and file name
       $parent_id = 0;
      
        // check and insert project name details
        $project_id = 0;
       /* if ($post['project_name'] != '') {
            $sql = 'SELECT id FROM projects WHERE project_name = "' . filterString($post['project_name']) . '"';
            $qry = $this->db->query($sql);
            if ($qry->num_rows()) {
                $rs = $qry->row();
                $project_id = $rs->id;
            } else {
                $sql = 'INSERT INTO projects (project_name) VALUES ("' . filterString($post['project_name']) . '")';
                $qry = $this->db->query($sql);
                $project_id = $this->db->insert_id();
            }
        }*/

        // fetch parent attachment ids of project name
        $arr_val_ref = $this->getValReference();
        $arr_tag_ref = $this->getTagReferences();
        $arr_all_tag_ref = $this->getTagAllReferences();
        $att_col = '';
        $att_val = '';
        $tag_qry = '';

        // fetch fixed tag columns


        foreach ($arr_tg as $tag => $val) {
            if (isset($arr_tag_ref[$tag])) {
                $att_col .= ',' . $tag;
                $att_val .= ',"' . $arr_val_ref[$val] . '"';
            }
        }
        // insert attachments
        $sql = 'INSERT INTO `attachments` (`cstatus`,`extension`,`file_rename`,'
                . '`base_name`,`group_col`,`attachment_orig_name`,'
                . ' `attachment_rename`, `project_id`, `created_by`, '
                . '`created_date`, `ipaddress`' . $att_col . ') VALUES '
                . '("' . $additional['cstatus'] . '","' . $additional['extension'] . '","' . $file_details['rename'] . '",'
                . '"' . $additional['search_name'] . '","' . $additional['group_col'] . '","' . $additional['attachment_orig_name'] . '", '
                . '"' . $file_details['attachment_rename'] . '","' . $additional['project_id'] . '","' . $additional['user_id'] . '",'
                . '"' . date('Y-m-d H:i:s') . '","' . $file_details['ipaddress'] . '"' . $att_val . ');';

        $query_object = $this->db->query($sql);
        $att_id = $this->db->insert_id();

        // fetch all tag refernces
        foreach ($arr_tg as $tag => $val) {
            $tag_qry .= '(' . $att_id . ',' . $arr_all_tag_ref[$tag] . ',' . $val . ',' . $additional['user_id'] . ',"' . date('Y-m-d H:i:s') . '","' . $additional['ipaddress'] . '"),';
        }
        // insert attachment tag details
        $qry = 'INSERT INTO tag_attachments(attachments_id,tag_reference_id,tag_value,created_by,created_date,ipaddress) VALUES' . $tag_qry;
        $qry = trim($qry, ',');
        $status = $this->db->query($qry);
        return true;
    }

    function pdfinfo_email() {
        $today_uploads = $this->uploader_model->pdf_uploads_info();
        $to = "megireddyece@gmail.com";
        //$to = "Tejas.shah@me.com";
        $body_text = '';
        $total = count($today_uploads['failure'])+count($today_uploads['sucess']);
        $body_text .='<div>Total Uploaded files on : ' . date("Y/m/d") . ' : '.$today_uploads['total'].'</div>';
        $body_text .='<div>No. of PDF files uploaded : ' . $total . '</div>';
        $body_text .='<div>No. of files converted : ' . count($today_uploads['sucess']) . '</div>';
        $body_text .='<div>No. of failures : ' . count($today_uploads['failure']) . '</div>';
       
        if(count($today_uploads['failure'])>0){
             $body_text .='<div>Details of failure files </div>';
            foreach ($today_uploads['failure'] as $key=>$value){
             $body_text .='<div>'.$value['pname']." - ".$value['org_name'].'</div>'; 
          }
        }
        
        $ip_array = array('from' => 'admin@iviesystems.com',
            'to' => $to,
            'subject' => 'Gallery - PDF Info',
            'message' => $body_text,
            'reply_to' => false,
            'no_reply' => true);
        $this->load->model('login_model');
        $this->login_model->sendMail($ip_array);
        /* echo '<pre>';
          print_r($today_uploads);
          die; */
    }
    function movingTocloud($pdf_imges, $pdfname, $ext) {
       /* echo '<pre>';
        print_r($pdf_imges);*/
        set_time_limit(0);
        include(APPPATH . 'libraries/cloudfiles/cloudfiles.php');
        //$username = "enterpi_cloud"; // username
        //$username = "enterpi_cloud"; // username
        //$key = "228e76eea0634929b2216656db73e7d7"; // api key
        $username = "supervaluecore"; // username
	$key = "96467cbcc93f524b0d3c55b37c9d3e56"; // api key
        $orgnal_files = FCPATH . "uploads/cloud_files/";
        $thumb_files = $orgnal_files . "thumbnail/";
        // Connect to Rackspace
       // $container1 = $conn->get_container('WR Gallery 1');
	//$container2 = $conn->get_container('WR Gallery 2');
        $auth = new CF_Authentication($username, $key);
        $auth->authenticate();
        $conn = new CF_Connection($auth);
        $container1 = $conn->get_container('WR Gallery 1');
        $container2 = $conn->get_container('WR Gallery 2');
       if (strtolower($ext) == 'pdf') {
            $object1 = $container1->create_object($pdfname);
            $object1->load_from_filename($orgnal_files.$pdfname);
            unlink($orgnal_files . $pdfname);
        }
        foreach ($pdf_imges as $key => $value) {
            if (file_exists($orgnal_files.$value['attachment_rename'])) {
                $object1 = $container1->create_object($value['attachment_rename']);
                $object1->load_from_filename($orgnal_files.$value['attachment_rename']);
                unlink($orgnal_files.$value['attachment_rename']);
                if (file_exists($thumb_files.$value['attachment_rename'])) {
                    $object2 = $container2->create_object($value['attachment_rename']);
                    $object2->load_from_filename($thumb_files.$value['attachment_rename']);
                    unlink($thumb_files.$value['attachment_rename']);
                }
            }
            /*if($key==count($pdf_imges)){
                
            }*/
        }
        
        return TRUE;
    }

    

    function deleteDuplicate($group_col, $att_rename) {
        echo "<br/>".$qry = "select id,attachment_rename,file_rename,cstatus,extension from attachments where group_col ='" . $group_col . "' and attachment_orig_name='" . filterStringDecode($att_rename). "' and cstatus!='2'";
        $query_object = $this->db->query($qry);
        echo '<pre>';
        print_r($query_object);
        $qury_result = $query_object->result();
        print_r($qury_result);
        $qury_result_row = $query_object->num_rows();
        if ($qury_result_row > 0) {echo 'if loop';
            include(APPPATH . 'libraries/cloudfiles/cloudfiles.php');
           // $username = "enterpi_cloud"; // username
           // $key = "228e76eea0634929b2216656db73e7d7"; // api key
           $username = "supervaluecore"; // username
	   $key = "96467cbcc93f524b0d3c55b37c9d3e56"; // api key
            $auth = new CF_Authentication($username, $key);
            $auth->authenticate();
            $conn = new CF_Connection($auth);
           // $container1 = $conn->get_container('annas_3');
           // $container2 = $conn->get_container('annas_4');
            $container1 = $conn->get_container('WR Gallery 1');
            $container2 = $conn->get_container('WR Gallery 2');
            foreach ($qury_result as $row) {
                echo "for loop";
                $data[] = $row->id;
                if ($row->cstatus == "0") { echo 'cstatus=0';
                    $source_path = FCPATH . "uploads/cloud_files/";
                    $source_path_thumb = FCPATH . "uploads/cloud_files/thumbnail/";
                    unlink($source_path . $row->attachment_rename);
                    if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
                        unlink($source_path_thumb . $row->attachment_rename);
                        
                    }
                    echo $row->attachment_rename."unlink done";
                } else {
                    echo "cstatus=1";
                    
                    echo $container1->delete_object($row->attachment_rename);
                    if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
                        $container2->delete_object($row->attachment_rename);
                    }
                    echo $row->attachment_rename." deleted from cloud";
                }
            }
            if (strtolower($qury_result[0]->extension) == "pdf" && $qury_result[0]->cstatus == "1") {
                $container1->delete_object($qury_result[0]->file_rename);
            } else if (strtolower($qury_result[0]->extension) == "pdf" && $qury_result[0]->cstatus == "0") {
                unlink($source_path . $qury_result[0]->file_rename);
            }
            echo '<pre>';
            print_r($data);
            $this->db->where_in('id', $data);
            $this->db->delete('attachments');
            $this->db->where_in('attachments_id', $data);
            $this->db->delete('tag_attachments');
        }

        return "TRUE";
    }

    
    function display_firstimg($x,$y)
    {
       
       $qry="select a.attachment_rename, a.order_number, a.created_date from attachments as a
                where a.cstatus = '1' 
                and a.order_number = 1
                and a.extension not in ('mp3','mov')
                limit ".intval($x).", ".intval($y); 
         $query_obj = $this->db->query($qry);
        //$data['image_containers'] = $this->gallery_model->getContainerUrl();	
        foreach ($query_obj->result() as $key=>$value)
        {
            $s_img[]="http://2fc1fa6699acc1c84004-2baafc5e6887e32305fad7b2f08b73c8.r32.cf1.rackcdn.com/".$value->attachment_rename;
        }
        $data['images']= $s_img;
        
       $this->load->view('first_image',$data); 
    }
        function inserAttachmentpdf($iparray, $name, $additional) {
        //$file_details = $iparray['file_details'];
        $post = $iparray['post'];
        // frame tag_array
        $arr_tg = array();
        foreach ($post as $tag => $val) {
            if ($tag != 'project_name' && $val != '') {
                $arr_tg[$tag] = $val;
            }
        }
        // check and insert project name details
        $project_id = 0;
       /* if ($post['project_name'] != '') {
            $sql = 'SELECT id FROM projects WHERE project_name = "' . mysql_real_escape_string($post['project_name']) . '"';
            $qry = $this->db->query($sql);
            if ($qry->num_rows()) {
                $rs = $qry->row();
                $project_id = $rs->id;
            } else {
                $sql = 'INSERT INTO projects (project_name) VALUES ("' . mysql_real_escape_string($post['project_name']) . '")';
                $qry = $this->db->query($sql);
                $project_id = $this->db->insert_id();
            }
        }*/
        $arr_val_ref = $this->getValReference();
        $arr_tag_ref = $this->getTagReferences();
        $arr_all_tag_ref = $this->getTagAllReferences();
        $att_col = '';
        $att_val = '';
        $tag_qry = '';
        // fetch fixed tag columns
        foreach ($arr_tg as $tag => $val) {
            if (isset($arr_tag_ref[$tag]) && !empty($val)) {
                $att_col .= ',`' . $tag . '`';
                $att_val .= ',"' . $arr_val_ref[$val] . '"';
            }
        }
        // insert attachments
        $sql_att = "INSERT INTO `attachments` (`cstatus`,`extension`,`order_number`,"
                . "`file_rename`,`base_name`,`group_col`,"
                . "`attachment_orig_name`, `attachment_rename`,"
                . "`project_id`, `created_by`, `created_date`,"
                . "`ipaddress` $att_col) VALUES ";
        foreach ($iparray['file_details'] as $file_details) {
            $sql_att .= '("' . $additional['cstatus'] . '","' . $additional['extension'] . '","' . $file_details['order_number'] . '",'
                    . '"' . $file_details['rename'] . '","' . $additional['search_name'] . '","' . $additional['group_col'] . '","' . $additional['attachment_orig_name'] . '", '
                    . '"' . $file_details['attachment_rename'] . '","' . $additional['project_id'] . '","' . $additional['user_id'] . '",'
                    . '"' . date('Y-m-d H:i:s') . '","' . $additional['ipaddress'] . '"' . $att_val . '),';
        }
        $sql_attachment = trim($sql_att, ',');
        $query_object = $this->db->query($sql_attachment);
        $selectidqry = "select id from attachments where group_col='" . $additional['group_col'] . "'";
        $attachment_id_obj = $this->db->query($selectidqry);
        $attachment_id = $attachment_id_obj->result_array();
        // fetch all tag refernces
        $arr_att_id = array();
        foreach ($attachment_id as $attachmentid) {
            $arr_att_id[$attachmentid['id']] = $attachmentid['id'];
            foreach ($arr_tg as $tag => $val) {
                $tag_qry .= '(' . $attachmentid['id'] . ',' . $arr_all_tag_ref[$tag] . ',' . $val . ',' . $additional['user_id'] . ',"' . date('Y-m-d H:i:s') . '","' . $additional['ipaddress'] . '"),';
            }
        }
        $str_att_id = implode(',', $arr_att_id);
        // delete previous tag_values
        $sql = 'delete from tag_attachments where attachments_id in(' . $str_att_id . ')';
        $this->db->query($sql);
        $qry = 'INSERT INTO tag_attachments(attachments_id,tag_reference_id,tag_value,created_by,created_date,ipaddress) VALUES' . $tag_qry;
        $qry = trim($qry, ',');
        $status = $this->db->query($qry);
        return $status;
    }

    function getValReference() {
        $qry = 'SELECT id, IF(val_ref IS NULL,name,val_ref) AS val FROM master_data';
        $query_object = $this->db->query($qry);
        $res = array();
        if ($query_object->result()) {
            foreach ($query_object->result() as $row) {
                $res[$row->id] = $row->val;
            }
        }
        return $res;
    }

    function getTagReferences() {
        $qry = 'SELECT id,val_ref FROM tag_reference WHERE `is_fixed`="y"';
        $query_object = $this->db->query($qry);
        $res = array();
        if ($query_object->result()) {
            foreach ($query_object->result() as $row) {
                $res[$row->val_ref] = $row->id;
            }
        }
        return $res;
    }

    function getTagAllReferences() {
        $qry = 'SELECT id,val_ref FROM tag_reference';
        $query_object = $this->db->query($qry);
        $res = array();
        if ($query_object->result()) {
            foreach ($query_object->result() as $row) {
                $res[$row->val_ref] = $row->id;
            }
        }
        return $res;
    }
    
     function testmovingTocloud() {
        include(APPPATH . 'libraries/cloudfiles/cloudfiles.php');
        //$username = "enterpi_cloud"; // username
        $username = "enterpi_cloud"; // username
        $key = "228e76eea0634929b2216656db73e7d7"; // api key
        $orgnal_files = FCPATH."uploads/cloud_files/";
        $thumb_files = $orgnal_files."thumbnail/";
        $auth = new CF_Authentication($username, $key);
        $auth->authenticate();
        $conn = new CF_Connection($auth);
        $container1 = $conn->get_container('annas_3');
        if(file_exists($orgnal_files."FH9Do2_1412923970_1.jpeg")){
        $object1 = $container1->create_object('FH9Do2_1412923970_1.jpeg');
        $object1->load_from_filename($orgnal_files."FH9Do2_1412923970_1.jpeg");
        }else{
            echo "file not exist";
        }
     }

     function deleteDuplicate_xxx($group_col, $att_rename) {
        $CI = & get_instance();
        $qry = "select id,attachment_rename,file_rename,cstatus,extension from attachments where group_col ='" . $group_col . "' and attachment_orig_name='" . $att_rename . "'";
        $query_object = $CI->db->query($qry);
        $qury_result = $query_object->result();
        $qury_result_row = $query_object->num_rows();

        if ($qury_result_row > 0) {
            include(APPPATH . 'libraries/cloudfiles/cloudfiles.php');
            $username = "supervaluecore"; // username
            $key = "96467cbcc93f524b0d3c55b37c9d3e56"; // api key
            $auth = new CF_Authentication($username, $key);
            $auth->authenticate();
            $conn = new CF_Connection($auth);
            $container1 = $conn->get_container('WR Gallery 1');
            $container2 = $conn->get_container('WR Gallery 2');
            foreach ($qury_result as $row) {
                $data[] = $row->id;
                if ($row->cstatus == "0") {
                    $source_path = FCPATH . "uploads/";
                    $source_path_thumb = FCPATH . "uploads/thumbnail/";
                    unlink($source_path . $row->attachment_rename);
                    if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
                        unlink($source_path_thumb . $row->attachment_rename);
                    }
                } else {
                    $container1->delete_object($row->attachment_rename);
                    if (strtolower($row->extension) != "mp3" && strtolower($row->extension) != "mov") {
                        $container2->delete_object($row->attachment_rename);
                    }
                }
            }
            if (strtolower($qury_result[0]->extension) == "pdf" && $qury_result[0]->cstatus == "1") {
                $container1->delete_object($qury_result[0]->file_rename);
            } else if (strtolower($qury_result[0]->extension) == "pdf" && $qury_result[0]->cstatus == "0") {
                unlink($source_path . $qury_result[0]->file_rename);
            }
            $CI->db->where_in('id', $data);
            $CI->db->delete('attachments');
            $CI->db->where_in('attachments_id', $data);
            $CI->db->delete('tag_attachments');
        }
        return TRUE;
    }
}
