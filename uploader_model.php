<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Uploader_model  extends CI_Model {	
	function updateTagAttaches($post){	
		$arr_user_details = @unserialize($this->db_session->userdata('user_login_details'));
				
		//print_r($post);exit;		
		// frame tag_array
		$arr_tg = array();
		foreach($post as $tag=>$val){
			if($tag != 'project_name' && $tag != 'group_col' && $tag != 'base_name'){
				$arr_tg[$tag] = $val;
			}
		}		
		
		// fetch all attachments of group_col
		$qry = 'SELECT id FROM attachments WHERE group_col="'.$post['group_col'].'"';
		$query = $this->db->query($qry);
		$obj_all_ids = $query->result();
		$arr_att_ids = array();
		foreach($obj_all_ids as $row){
			$arr_att_ids[] = $row->id;
		}
		$att_ids = implode($arr_att_ids,',');
		
		// fetch all tag references
		$qry = 'SELECT val_ref FROM tag_reference WHERE is_fixed="y"';
		$query = $this->db->query($qry);
		$obj_val_refs = $query->result();
		$val_refs = array();
		foreach($obj_val_refs as $row){
			$val_refs[] = $row->val_ref;
		}
		$project_id = 0;
		if($post['project_name'] != ''){
			$sql = 'SELECT id FROM projects WHERE project_name = "'.$post['project_name'].'"';
			$qry = $this->db->query($sql);
			if($qry->num_rows()){
				$rs = $qry->row();
				$project_id = $rs->id;
			}
			else{
				$sql = 'INSERT INTO projects (project_name) VALUES ("'.$post['project_name'].'")';
				$qry = $this->db->query($sql);
				$project_id = $this->db->insert_id();
			}
		}
		$dyntg = '';
		$arr_val_ref = $this->getValReference();
		// unset all tag values in attachments
		$qry = 'UPDATE attachments SET project_id='.$project_id;
		foreach($val_refs as $i=>$tag){
			$dyntg .= '_';
			if(isset($post[$tag]) && isset($arr_val_ref[$post[$tag]]) && !empty($arr_val_ref[$post[$tag]])){
				$qry .= ','.$tag.'="'.$arr_val_ref[$post[$tag]].'"';
				$dyntg .= $post[$tag];
			}
			else{
				$qry .= ','.$tag.'=""';
			}
		}
		$dyn_str = $post['base_name'].'_'.$post['project_name'].$dyntg;
		$group_col = md5($dyn_str);
		$qry .= ',group_col="'.$group_col.'",modified_by='.$arr_user_details['id'].',modified_date="'.date('Y-m-d H:i:s').'",ipaddress="'.$_SERVER['REMOTE_ADDR'].'" WHERE group_col="'.$post['group_col'].'"';
		//echo $qry;
                //die;
                $query = $this->db->query($qry);// update project_id, tags of all attachments of group_col
		
		// delete tag_attachments of all attachments of group_col
		$qry = 'DELETE FROM tag_attachments where attachments_id IN('.$att_ids.')';
		$query = $this->db->query($qry);
		
		// insert tag_attachments of all attachments of group_col
		$arr_tag_ref = $this->getTagReferences();
		$tag_qry = '';
		foreach($arr_att_ids as $k=>$attid){
			// fetch all tag refernces
			foreach($arr_tg as $tag=>$val){
				$tag_qry .= '('.$attid.','.$arr_tag_ref[$tag].','.$val.','.$arr_user_details['id'].',"'.date('Y-m-d H:i:s').'","'.$_SERVER['REMOTE_ADDR'].'"),';
			}	
		}
		$tag_qry = trim($tag_qry,',');
		// insert attachment tag details
		$qry = 'INSERT INTO tag_attachments(attachments_id,tag_reference_id,tag_value,created_by,created_date,ipaddress) VALUES'.$tag_qry;
               
		$this->db->query($qry);	
		return true;
	}
	
	function getTagReferences(){
		$qry = 'SELECT id,val_ref FROM tag_reference WHERE `status`="a"';
		$query_object=$this->db->query($qry);
		$res = array();
		if($query_object->result()) { 
			foreach($query_object->result() as $row){
				$res[$row->val_ref] = $row->id;
			}
		}
		return $res;
	}
	
	function getValReference(){
		$qry = 'SELECT id, IF(val_ref IS NULL,name,val_ref) AS val FROM master_data';
		$query_object=$this->db->query($qry);
		$res = array();
		if($query_object->result()) { 
			foreach($query_object->result() as $row){
				$res[$row->id] = $row->val;
			}
		}
		return $res;
	}
	function getTagId($tag){
		
		$sql=$this->db->query("SELECT id
				FROM tag_reference tr 
				WHERE tr.val_ref='".$tag."' limit 1");
			if($sql->num_rows()>0){
				$row=$sql->row();
				$data=$row->id;
			}else{
				$data=0;
			}
			return $data;
		
	}
	function getChildsTags($id,$data=array()){
		
		$sql=$this->db->query("SELECT id,val_ref
				FROM tag_reference tr 
				WHERE tr.parent_id='".$id."' limit 1");
			if($sql->num_rows()>0){
				$row=$sql->row();
				$data[$row->val_ref]=$row->id;
				return $this->getChildsTags($row->id,$data);
			}
			return $data;
		
	}
        
        function update_cloudstatus($id,$status)
        {
            //echo $id."--".$status;
            $data = array(
               'cstatus' => $status,
               'modified_date' =>  date('Y-m-d H:i:s')
            );
            $this->db->where('id', $id);
            $this->db->update('cloud_attachments', $data);
           // echo $this->db->last_query();
            //die;
        }
        
        function run_cronstatus()
        {
             $sql_dateVise=$this->db->query("select * from cloud_attachments where rstatus='1' order by created_date");
             return $sql_dateVise->num_rows();
           // $sql_dateVise;
        }
        function set_cronstatus($id,$status)
        {
            $data = array('cstatus' => $status);
            $this->db->where('id', $id);
            $this->db->update('cloud_attachments', $data);
        }
         function get_catachments()
        {
            $sql_dateVise=$this->db->query("select * from cloud_attachments where cstatus='0' order by created_date");
            return $sql_dateVise;
            
        }
         function pdf_uploads_info()
        {
           $info = array();
           $info['sucess']=$info['failure']=array();
           $sql ='';
           $sql .= "SELECT * FROM `cloud_attachments` WHERE DATE(`created_date`) = CURDATE() - interval 1 day";
           $qry = $this->db->query($sql);
           $info['total'] = $qry->num_rows();
           $sql .= " and extension='pdf'";
            $qry = $this->db->query($sql);
           $info['pdf_count'] = $qry->num_rows();
           foreach ($qry->result() as $key=>$value)
           {
             if($value->cstatus==2){
             $info['sucess'][] = array("org_name"=>$value->attachment_orig_name,"pname"=>$value->project_name);
             }elseif($value->cstatus==3){
             $info['failure'][] = array("org_name"=>$value->attachment_orig_name,"pname"=>$value->project_name);
             }
           }
           return $info;
           
        }
        function delete_duplicateatt($id)
        {
           $this->db->delete('attachments', array('id' => $id));
           $this->db->delete('tag_attachments', array('attachments_id' => $id)); 
        }
	
	
}
