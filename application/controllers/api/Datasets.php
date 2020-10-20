<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Datasets extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Catalog_model'); 	
		$this->load->helper("date");
		$this->load->model('Data_file_model');
		$this->load->model('Variable_model');	
		$this->load->model('Dataset_model');//remove with Datasets library
		$this->load->library("Dataset_manager");
		$this->is_admin_or_die();
	}

	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		//session user id
		if ($this->session->userdata('user_id'))
		{
			//var_dump($this->session->userdata('user_id'));
			return true;
		}

		parent::_auth_override_check();
	}


	//override to support sessions
	function get_api_user_id()
	{
		//session user id
		if ($this->session->userdata('user_id')){
			return $this->session->userdata('user_id');
		}

		if(isset($this->_apiuser) && isset($this->_apiuser->user_id)){
			return $this->_apiuser->user_id;
		}

		return false;
	}
	
	/**
	 * 
	 * 
	 * Return all datasets
	 * 
	 */
	function index_get($idno=null)
	{
		try{
			if($idno){
				return $this->single_get($idno);
			}
			
			$result=$this->dataset_manager->get_all();
			array_walk($result, 'unix_date_to_gmt',array('created','changed'));				
			$response=array(
				'status'=>'success',
				'found'=>count($result),
				'datasets'=>$result
			);		
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Get a single dataset
	 * 
	 */
	function single_get($idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);
			$result=$this->dataset_manager->get_row($sid);
			array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}

			$result['metadata']=$this->dataset_manager->get_metadata($sid);
			
			$response=array(
				'status'=>'success',
				'dataset'=>$result
			);			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Check if a study IDNO exists
	 * 
	 */
	function check_idno_get($idno=null)
	{
		try{
			$sid=$this->dataset_manager->find_by_idno($idno);
			
			if ($sid){
				$response=array(
					'status'=>'success',
					'idno'=>$idno,
					'id'=>$sid
				);			
				$this->set_response($response, REST_Controller::HTTP_OK);
			}
			else{
				$response=array(
					'status'=>'not-found',
					'idno'=>$idno,
					'message'=>'IDNO NOT FOUND'
				);
				$this->set_response($response, REST_Controller::HTTP_NOT_FOUND);
			}
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * Replace study IDNO
	 * 
	 */
	function replace_idno_post()
	{
		try{
			$input=$this->raw_json_input();			

				
			$old_idno=array_get_value($input,'old_idno');
			$new_idno=array_get_value($input,'new_idno');

			if (empty($old_idno) || empty($new_idno)){
				throw new Exception("OLD_IDNO and NEW_IDNO parameters not set");
			}
			
			$sid=$this->Dataset_model->get_id_by_idno($old_idno);

			if(!$sid){
				throw new Exception("OLD_IDNO was not found");
			}

			if($new_sid=$this->Dataset_model->get_id_by_idno($new_idno)){
				throw new Exception("NEW_IDNO already in use: ".$new_sid);
			}

			$options=array(
				'idno'=>$new_idno
			);
			
			$this->Dataset_model->update_options($sid,$options);

			$response=array(
				'status'=>'success',
				'new_idno'=>$new_idno,
				'id'=>$sid
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}




	/**
	 * 
	 * 
	 * Update dataset options
	 * 
	 * @idno - dataset IDNO
	 * 
	 * 
	 * 
	 */
	function index_put($idno=null)
	{
		$this->load->helper("array");

		try{
			$input=$this->raw_json_input();
			$sid=$this->get_sid_from_idno($idno);

			$options=array(				
				'repositoryid'			=> array_get_value($input,'owner_collection'),
				'formid'				=> array_get_value($input,'access_policy'),
				'link_da'				=> array_get_value($input,'data_remote_url'),
				'published'				=> array_get_value($input,'published'),
				'link_study'			=> array_get_value($input,'link_study'),
				'link_indicator'		=> array_get_value($input,'link_indicator'),
				'thumbnail'				=> array_get_value($input,'thumbnail')
			);

			if(!empty($options['formid'])){
				$options['formid']=$this->dataset_manager->get_data_access_type_id($options['formid']);
			}

			//remove options not set
			foreach($options as $key=>$value){
				if($value===false){
					unset($options[$key]);
				}
			}

			//validate
			//$this->dataset_manager->validate_options($options);
			
			//update
			$this->dataset_manager->update_options($sid,$options);

			$response=array(
				'status'=>'success'				
			);


			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>"VALIDATION_ERRORS",
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	

	/**
	 * 
	 * find a dataset by id
	 * 
	 **/ 
	function find_by_id_get($sid=null)
	{
		try{
			if(!$sid){
				throw new Exception("PARAM-MISSING::SID");
			}
			
			$idno=$this->dataset_manager->get_idno($sid);

			if(!$idno){
				throw new Exception("ID_NOT_FOUND");
			}

			return $this->single_get($idno);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Create timeseries database
	 * 
	 * 
	 */
	private function create_timeseries_database($idno=null)
	{
		$this->load->model('Timeseries_db_model');

		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
						
			//validate & create dataset
			$db_id=$this->Timeseries_db_model->create_database($options);

			if(!$db_id){
				throw new Exception("FAILED_TO_CREATE_DATABASE");
			}

			$database=$this->Timeseries_db_model->get_row($db_id);
			
			$response=array(
				'status'=>'success',
				'database'=>$database
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Create new study
	 * @type - survey, timesereis, geospatial
	 * 
	 */
	function create_post($type=null,$idno=null)
	{
		if($type=='timeseries-db'){
			return $this->create_timeseries_database($idno);
		}

		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			
			//set default repository if not set
			if(!isset($options['repositoryid'])){
				$options['repositoryid']='central';
			}

			//validate & create dataset
			$dataset_id=$this->dataset_manager->create_dataset($type,$options);

			if(!$dataset_id){
				throw new Exception("FAILED_TO_CREATE_DATASET");
			}

			$dataset=$this->dataset_manager->get_row($dataset_id);

			//create dataset project folder
			$dataset['dirpath']=$this->dataset_manager->setup_folder($repositoryid='central', $folder_name=md5($dataset['idno']));

			$update_options=array(
				'dirpath'=>$dataset['dirpath']
			);

			$this->dataset_manager->update_options($dataset_id,$update_options);
			$this->events->emit('db.after.update', 'surveys', $dataset_id,'import');

			$response=array(
				'status'=>'success',
				'dataset'=>$dataset
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage() 
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * 
	 * Update dataset
	 * @type - survey, timesereis, geospatial
	 * 
	 */
	function update_post($type=null,$idno=null)
	{
		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			
			//get sid from idno
			$sid=$this->get_sid_from_idno($idno);

			//load dataset
			$dataset=$this->dataset_manager->get_row($sid);

			$options['changed_by']=$user_id;
			$options['changed']=date("U");

			//default to merge metadata and update partial metadata
			$merge_metadata=true;

			if(isset($options['merge_options'])){
				if($options['merge_options']=='replace'){
					$merge_metadata=false;//replace instead of merge
				}
			}

			//merge dataset cataloging options
        	$options=array_merge($dataset,$options);
			
			//validate & update dataset			
			if ($type=='survey' || $type=='document'){
				$dataset_id=$this->dataset_manager->update_dataset($sid,$type,$options, $merge_metadata); 
			}
			else{
				//get existing metadata
				$metadata=$this->dataset_manager->get_metadata($sid);

				//unset($metadata['idno']);
				
				//replace metadata with new options
				if($merge_metadata==true){
					$options=array_replace_recursive($metadata,$options);
				}

				$dataset_id=$this->dataset_manager->create_dataset($type,$options);
			}

			//load updated dataset
			$dataset=$this->dataset_manager->get_row($dataset_id);

			$response=array(
				'status'=>'success',
				'dataset'=>$dataset				
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}





	/**
	 * 
	 * list study data files
	 * 
	 */
	function datafiles_get($idno=null)
	{
		try{			
			$sid=$this->get_sid_from_idno($idno);

			$user_id=$this->get_api_user_id();        
			$survey=$this->dataset_manager->get_row($sid);

			if(!$survey){
				throw new exception("STUDY_NOT_FOUND");
			}

			$survey_datafiles=$this->Data_file_model->get_all_by_survey($sid);
			
			//format dates
			//array_walk($project, 'unix_date_to_gmt_row',array('created','changed','submitted_date','administer_date'));

			$response=array(
				'datafiles'=>$survey_datafiles
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	

	/**
	 * 
	 * 
	 * Create new data file
	 * 
	 */
	function datafiles_post($idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;

			$options['sid']=$sid;

			//validate 
			if ($this->Data_file_model->validate_data_file($options)){
				
				$file_id=$this->Data_file_model->insert($sid,$options);
				$file=$this->Data_file_model->select_single($file_id);

				$response=array(
					'status'=>'success',
					'datafile'=>$file
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
			}
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}




	/**
	 * 
	 * List dataset variables
	 * 
	 */
	function variables_get($idno=null,$file_id=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);
			$user_id=$this->get_api_user_id();        
			$survey=$this->dataset_manager->get_row($sid);

			if(!$survey){
				throw new exception("STUDY_NOT_FOUND");
			}

			$survey_variables=$this->Variable_model->list_by_dataset($sid,$file_id);
			
			//format dates
			//array_walk($project, 'unix_date_to_gmt_row',array('created','changed','submitted_date','administer_date'));

			$response=array(
				'variables'=>$survey_variables
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 *  Return a single variable with full metadata
	 * 
	 */
	function variable_get($idno=null,$var_id=null)
	{
		try{						
			if(!$var_id){
				throw new Exception("MISSING_PARAM::VAR_ID");
			}

			$sid=$this->get_sid_from_idno($idno);
			$user_id=$this->get_api_user_id();        
			$variable=$this->Variable_model->get_var_by_vid($sid,$var_id);

			if(!$variable){
				throw new Exception("VARIABLE-NOT-FOUND");
			}
			
			//format dates
			//array_walk($project, 'unix_date_to_gmt_row',array('created','changed','submitted_date','administer_date'));

			$response=array(
				'variable'=>$variable
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * 
	 * Create variables for Datasets
	 * @idno - dataset IDNo
	 * @file_id - user defined file id e.g. F1
	 * 
	 */
	function variables_post($idno=null,$file_id=null,$type='survey')
	{
		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			$sid=$this->get_sid_from_idno($idno);

			//get file id
			$fid=$this->Data_file_model->get_fid_by_fileid($sid,$file_id);

			if(!$fid){
				throw new exception("FILE_NOT_FOUND: ".$file_id);
			}

			//check if a single variable input is provided or a list of variables
			$key=key($options);

			//convert to list of a list
			if(!is_numeric($key)){
				$tmp_options=array();
				$tmp_options[]=$options;
				$options=null;
				$options=$tmp_options;
			}
			
			//validate all variables
			foreach($options as $key=>$variable){
				$variable['fid']=$file_id;
				$this->Variable_model->validate_variable($variable);
			}

			$result=array();
			foreach($options as $variable)
			{
				$variable['fid']=$file_id;
				//all fields are stored as metadata
				$variable['metadata']=$variable;
				$variable_id=$this->Variable_model->insert($sid,$variable);
				//$variable=$this->Variable_model->select_single($variable_id);
				//$result[$variable['vid']]=$variable;
				$result[$variable['vid']]=$variable_id;
			}

			//update survey varcount
			$this->dataset_manager->update_varcount($sid);

			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Create series indicator [variable]
	 * 
	 */
	function series_post($idno=null,$file_id=null)
	{
		return $this->variables_post($idno,$file_id,$type='timeseries');
	}



	/**
	 * 
	 * Batch delete variables
	 * 
	 * @idno - string - dataset IDNo
	 * @file_id - string - (optional) file ID e.g. F1
	 **/ 
	function batch_delete_vars_delete($idno=null, $file_id=null)
	{
		try{
		
			$sid=$this->get_sid_from_idno($idno);
			$this->Dataset_model->remove_datafile_variables($sid,$file_id);

			$response=array(
				'status'=>'success',
				'message'=>'DELETED'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * 
	 * Return ID by IDNO
	 * 
	 * 
	 * @idno 		- ID | IDNO
	 * @id_format 	- ID | IDNO
	 * 
	 * Note: to use ID instead of IDNO, must pass id_format in querystring
	 * 
	 */
	private function get_sid_from_idno($idno=null)
	{		
		if(!$idno){
			throw new Exception("IDNO-NOT-PROVIDED");
		}

		$id_format=$this->input->get("id_format");

		if ($id_format=='id'){
			return $idno;
		}

		$sid=$this->dataset_manager->find_by_idno($idno);

		if(!$sid){
			throw new Exception("IDNO-NOT-FOUND");
		}

		return $sid;
	}

	/**
	 * 
	 * 
	 * 
	 * Update dataset internal database ID
	 * 
	 */
	function update_id_put($idno=null,$new_id=null)
	{
		try{
			if(!is_numeric($new_id)){
				throw new Exception("INVALID NEW ID");
			}
			
			$old_sid=$this->get_sid_from_idno($idno);

			if($old_sid == $new_id){
				$response=array(
					'status'=>'success',
					'message'=>'updated',
					"dataset"=>$this->dataset_manager->get_row($new_id)
				);
			}
			else{
				$survey=$this->dataset_manager->get_row($new_id);

				if($survey){
					throw new Exception("A DATASET EXISTS WITH THE ID: ".$new_id);
				}
				//update ID
				$result=$this->dataset_manager->update_sid($old_sid,$new_id);

				$response=array(
					'status'=>'success',
					'message'=>'updated',
					"dataset"=>$this->dataset_manager->get_row($new_id)
				);
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/** 
	 * 
	 * 
	 * Import a ddi 2 codebook xml file
	 * 
	 * @overwrite - yes|no - overwrite existing project
	 * @repositoryid - repository ID
	 * @file - uploaded file
	 * 
	 * 
	 **/ 
	function import_ddi_post()
	{
		$this->import_post('survey');
	}


	/** 
	 * 
	 * 
	 * Import a file
	 * 
	 * import a metadata file e.g. ddi or geospatial file
	 * 
	 * @overwrite - yes|no - overwrite existing project
	 * @repositoryid - repository ID
	 * @type - project type 
	 * @file - uploaded file
	 * 
	 * 
	 **/ 
	function import_post($type)
	{
		$this->load->library('ion_auth');
		$this->load->library('acl');

		$overwrite=$this->input->post("overwrite")=='yes' ? TRUE : FALSE;
		$repositoryid=$this->input->post("repositoryid");
		//$survey_type='geospatial';
		$dataset_type=$type;

		if (!$repositoryid){
			$repositoryid='central';
		}

		if(!$dataset_type){
			throw new Exception("DATASET_TYPE_NOT_SET");
		}

		try{
			//user has permissions on the repo or die
			$this->acl->user_has_repository_access($repositoryid,$this->get_api_user_id());
					
			//process form
			$temp_upload_folder=get_catalog_root().'/tmp';
			
			if (!file_exists($temp_upload_folder)){
				@mkdir($temp_upload_folder);
			}
			
			if (!file_exists($temp_upload_folder)){
				show_error('DATAFILES-TEMP-FOLDER-NOT-SET');
			}

			//process file urls
			$file_url=$this->input->post("file");
			
			if(empty($_FILES['file']) && !empty($file_url) && $this->form_validation->valid_url($file_url)) {
				$uploaded_ddi_path=$temp_upload_folder.'/'.md5($file_url).'.xml';
				
				//download file from URL 
				$file_content=@file_get_contents($file_url);
				if($file_content===FALSE){
					throw new Exception("FAILED-TO-READ-FILE-URL");
				}

				//save to tmp
				if (file_put_contents($uploaded_ddi_path,$file_content)===FALSE){
					throw new Exception("FILE-UPLOAD-VIA-URL-FAILED");
				}
			}
			//process file uploads
			else{
				$uploaded_ddi_path=$this->process_file_upload($temp_upload_folder,$allowed_file_types='xml',$file_field_name='file');
			}

			//data access type
			$form_id=$this->dataset_manager->get_data_access_type_id($this->input->post('access_policy'));

			//default
			if(!$form_id){
				$form_id=6;
			}

			//published
			$published=$this->input->post("published");

			if(!in_array($published,array(0,1))){
				$published=null;
			}
		
			$this->load->library('DDI2_import');
			$params=array(
				'file_type'=>$dataset_type, 
				'file_path'=>$uploaded_ddi_path,
				'repositoryid'=>$repositoryid,
				'published'=>$published,
				'user_id'=>$this->get_api_user_id(),
				'formid'=>$form_id,
				'overwrite'=>$overwrite
			);			
			
			$result=$this->ddi2_import->import($params);

			if(empty($result['sid'])){
				throw new Exception("SID_NOT_FOUND");
			}

			//Process RDF file if provided
			$rdf_result=array();
			$sid=$result['sid'];
			$this->load->model("Resource_model");
			
			if (!empty($_FILES['rdf']['name'])) {				
				$rdf_result=$this->Resource_model->import_uploaded_rdf($sid,$temp_upload_folder,$file_field='rdf');
			}
			else 
			{
				//process RDF URL link
				$rdf_url=$this->input->post("rdf");
				if(!empty($rdf_url)) {
					$tmp_rdf_file=$temp_upload_folder.'/'.md5($rdf_url).'.rdf';
					
					//download file from URL 
					$file_content=@file_get_contents($rdf_url);
					if($file_content===FALSE){
						throw new Exception("FAILED-TO-DOWNLOAD-RDF");
					}

					//save to tmp
					if (@file_put_contents($tmp_rdf_file,$file_content)===FALSE){
						throw new Exception("FAILED-SAVE-RDF-FILE");
					}

					//import
					$rdf_result=$this->Resource_model->import_rdf($sid,$tmp_rdf_file);
				}
			}

			$response=array(
				'status'=>'success',
				'survey'=>$result,
				'rdf'=>$rdf_result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}

	private function process_file_upload($temp_upload_folder,$allowed_file_types='xml',$file_field_name='file')
	{
		//upload class configurations for DDI
		$config['upload_path'] 	 = $temp_upload_folder;
		$config['overwrite'] 	 = FALSE;
		$config['encrypt_name']	 = TRUE;
		$config['allowed_types'] = 'xml';

		$this->load->library('upload', $config);

		//name of the field for file upload
		$file_field_name='file';
		
		//process uploaded ddi file
		$ddi_upload_result=$this->upload->do_upload($file_field_name);

		$uploaded_ddi_path=NULL;

		//ddi upload failed
		if (!$ddi_upload_result){
			$error = $this->upload->display_errors();
			$this->db_logger->write_log('ddi-upload',$error,'catalog');
			throw new Exception($error);
		}
		else //successful upload
		{
			//get uploaded file information
			$uploaded_ddi_path = $this->upload->data();
			$uploaded_ddi_path=$uploaded_ddi_path['full_path'];
			$this->db_logger->write_log('ddi-upload','success','catalog');
		}
		
		return $uploaded_ddi_path;
			
	}

	/**
	 * 
	 *  Delete by IDNO
	 * 
	 */
	public function delete_delete($idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);
			$this->dataset_manager->delete($sid);
			$this->events->emit('db.after.delete', 'surveys', $sid);
		
			$response=array(
				'status'=>'success',
				'message'=>'DELETED'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}	

	public function delete_by_id_delete($sid=null)
	{
		try{
			$this->dataset_manager->delete($sid);
			$this->events->emit('db.after.delete', 'surveys', $sid);
		
			$response=array(
				'status'=>'success',
				'message'=>'DELETED'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}




	/**
	 * 
	 *  Set study status
	 * 
	 * @sid - study id
	 * @publish_status - 1=publish, 0=unpublish
	 * 
	 */
	public function set_publish_status_put($sid=null,$publish_status=null)
	{		
		try{
			if(!is_numeric($sid) || !is_numeric($publish_status)){
				throw new Exception("MISSING_PARAMS");
			}
			$this->dataset_manager->set_publish_status($sid,$publish_status);
			$this->events->emit('db.after.update', 'surveys', $sid,'publish');
			$this->set_response('UPDATED', REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}				
	}

	


	/**
	 * 
	 * 
	 * Set the owner collection for the study or Transfer ownership
	 * 
	 * @sid - study id
	 * @repositoryid - collection numeric id
	 * 
	 */
	public function transfer_ownership_post(){
		
		$sid=$this->input->post("sid");
		$repositoryid=$this->input->post("repositoryid");

		try{
			if (!$sid || !$repositoryid){
				throw new Exception("PARAM_MISSING");
			}

			//user has permissions on the repo			
			//$this->acl->user_has_repository_access($repositoryid);
					
			//validate repository
			if ($repositoryid=='central'){
				$exists=true;
			}
			else{
				$exists=$this->Catalog_model->repository_exists($repositoryid);
			}

			if (!$exists){
				throw new Exception(t('COLLECTION_NOT_FOUND'));
			}

			//transfer ownership
			$this->Catalog_model->transfer_ownership($repositoryid,$sid);
			$this->events->emit('db.after.update', 'surveys', $sid);
			$this->set_response(t('msg_study_ownership_has_changed'), REST_Controller::HTTP_OK);		
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}	
	}

	//list data access types
	function list_data_access_types_get()
	{
		$this->load->model("Form_model");
		$types=$this->Form_model->data_access_types_list();
		$this->set_response($types, REST_Controller::HTTP_OK);		
	}

	/**
	 * 
	 * 
	 * set data access options
	 * 
	 * 
	 * @sid
	 * @da_type	- numeric data access type id
	 * @da_link	- only required for remote data access
	 * 
	 * Note: use list_data_access_types to get a list of available data access types
	 * 
	 **/ 
	function set_data_access_type_post()
	{
		$sid=$this->input->post("sid");
		$da_type=$this->input->post("da_type");
		$da_link=$this->input->post("da_link");		

		try{

			if (!$sid || !is_numeric($sid)){
				throw new Exception("INVALID_VALUE: SID");
			}

			if (!$da_type || !is_numeric($da_type)){
				throw new Exception("INVALID_VALUE: da_type");
			}

			if ($da_type==5 &&  !$da_link){
				throw new Exception("VALUE_MISSING: da_link");
			}

			$result=$this->dataset_manager->set_data_access_type($sid,$da_type,$da_link);			
			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
		
	}



	/**
	 * 
	 * upload dataset thumbnail
	 * 
	 **/ 
	function thumbnail_post($dataset_idno=null)
	{		
		try{
			$sid=$this->get_sid_from_idno($dataset_idno);

			$thumbnail_storage_path='files/thumbnails';

			//upload class configurations for RDF
			$config['upload_path'] = $thumbnail_storage_path;
			$config['overwrite'] = TRUE;
			$config['encrypt_name']=false;
			$config['file_name']='thumbnail-'.$sid;
			$config['file_ext_tolower']=true;
			$config['allowed_types'] = 'jpg|png|gif|jpeg';

			$this->load->library('upload', $config);

			//process uploaded file
			$upload_result=$this->upload->do_upload('file');

			if(!$upload_result){
				$error = $this->upload->display_errors();
				throw new Exception("FILE_UPLOAD::".$error);
			}
		
			$upload = $this->upload->data();

			$uploaded_file_name=$upload['file_name'];
			
			//attach to dataset
			$options=array(
				'thumbnail'=>$uploaded_file_name
			);

			$this->dataset_manager->update_options($sid,$options);

			$output=array(
				'status'=>'success',
				'uploaded_file_name'=>$uploaded_file_name				
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function thumbnail_delete($idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);

			$options=array(				
				'thumbnail'	=> null,
			);

			//update
			$this->dataset_manager->update_options($sid,$options);

			$response=array(
				'status'=>'success'				
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	*
	* Reload metadata from DDI
	*
	* Updates database with the metadata from DDI
	* 
	* partial - if yes, only update study level metadata
	*
	**/
	function reload_ddi_put($id=NULL,$partial=false)
	{
		//$this->acl->user_has_repository_access($repositoryid,$this->get_api_user_id());
		try{

			if (!is_numeric($id)){
				throw new Exception("ID_MISSING");
			}

			$this->load->model("Data_file_model");
			$this->load->library('DDI2_import');

			//get survey ddi file path by id
			$ddi_file=$this->Catalog_model->get_survey_ddi_path($id);

			if ($ddi_file===FALSE){
				throw new Exception("DDI_FILE_NOT_FOUND");
			}
			
			$dataset=$this->dataset_manager->get_row($id);

			$params=array(
				'file_type'=>'survey',
				'file_path'=>$ddi_file,
				'user_id'=>$this->get_api_user_id(),
				'repositoryid'=>$dataset['repositoryid'],
				'overwrite'=>'yes',
				'partial'=>$partial
			);
					
			$result=$this->ddi2_import->import($params,$id);

			//reset changed and created dates
			$update_options=array(
				'changed'=>$dataset['changed'],
				'created'=>$dataset['created'],
				'repositoryid'=>$dataset['repositoryid']
			);

			$this->dataset_manager->update_options($id,$update_options);
			$this->events->emit('db.after.update', 'surveys', $id,'refresh');

			$output=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);	
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);

			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/** 
	 * 
	 * 
	 * Convert DDI to array
	 * 
	 * @file - uploaded file
	 * 
	 * 
	 **/ 
	function ddi2array_post()
	{
		try{
					
			//process form
			$temp_upload_folder=get_catalog_root().'/tmp';
			
			if (!file_exists($temp_upload_folder)){
				@mkdir($temp_upload_folder);
			}
			
			if (!file_exists($temp_upload_folder)){
				show_error('DATAFILES-TEMP-FOLDER-NOT-SET');
			}

			//upload class configurations for DDI
			$config['upload_path'] 	 = $temp_upload_folder;
			$config['overwrite'] 	 = FALSE;
			$config['encrypt_name']	 = TRUE;
			$config['allowed_types'] = 'xml';

			$this->load->library('upload', $config);

			//name of the field for file upload
			$file_field_name='file';
			
			//process uploaded ddi file
			$ddi_upload_result=$this->upload->do_upload($file_field_name);

			$uploaded_ddi_path=NULL;

			//ddi upload failed
			if (!$ddi_upload_result){
				$error = $this->upload->display_errors();
				throw new Exception($error);
			}
			else //successful upload
			{
				//get uploaded file information
				$uploaded_ddi_path = $this->upload->data();
				$uploaded_ddi_path=$uploaded_ddi_path['full_path'];
			}		

			$parser_params=array(
				'file_type'=>'survey',
				'file_path'=>$uploaded_ddi_path
			);
	
			$this->load->library('Metadata_parser', $parser_params);
			$parser=$this->metadata_parser->get_reader();
			$output=$parser->get_metadata_array();
		
			$response=array(
				'status'=>'success',
				'ddi'=>array_keys($output)
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}



	/**
	*
	* Strip metadata elements from the DDI
	*
	* @strip - 'summary_stats', 'variables', 'keep_basic'
	*
	**/
	function strip_ddi_put($idno=NULL,$strip='')
	{
		$this->load->library("DDI_Utils");

		try{
			$sid=$this->get_sid_from_idno($idno);
			$user_id=$this->get_api_user_id();
			$result=$this->ddi_utils->strip_ddi($sid, $strip, $keep_original=true);

			if($result){
				$result=$this->ddi_utils->reload_ddi($sid, $user_id, $partial=false);
			}

			$output=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);	
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 *  Reload facets/filters
	 * 
	 * @sid - study id
	 * 
	 */
	public function refresh_filters_put($idno=null)
	{		
		try{
			$sid=$this->get_sid_from_idno($idno);
			$this->dataset_manager->refresh_filters($sid);

			$output=array(
				'status'=>'success'				
			);
			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}				
	}

	/**
	 * 
	 *  Reload year facets
	 * 
	 * @sid - study id
	 * 
	 */
	public function refresh_year_facets_get($start_row=NULL, $limit=1000)
	{		        
        try{
			$output=$this->Dataset_model->refresh_year_facets($start_row, $limit);
			$output=array(
                'status'=>'success',
                'result'=>$output
			);
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
            $error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);			
		}
    }

	/**
	 * 
	 *  Batch Reload facets/filters by dataset type
	 * 
	 * @dataset_type - dataset type - microdata, timeseries, etc
	 * @limit - number of items to process per request
	 * @start - starting dataset id
	 * 
	 */
	public function batch_refresh_filters_get($dataset_type=null, $limit=100, $start=0)
	{		
		try{
			$user_id=$this->get_api_user_id();
			
			if ($dataset_type==null){
				throw new Exception("DATASET_TYPE_IS_REQUIRED");
			}

			if(!is_numeric($start)){
				throw new Exception("PARAM:START-INVALID");
			}
			
			$datasets=$this->dataset_manager->get_list_by_type($dataset_type, $limit, $start);
			
			$output=array();
			foreach($datasets  as $dataset){
				$this->dataset_manager->refresh_filters($dataset['id']);
				$output[]=$dataset['id'];
				$last_processed=$dataset['id'];
			}
			
			$output=array(
				'status'=>'success',
				'datasets_updated'=>$output,
				'last_processed'=>$last_processed			
			);
			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}				
	}


	/**
	 * 
	 *  Batch repopulate index
	 * 	 
	 * @limit - number of items to process per request
	 * @start - starting dataset id
	 * 
	 */
	public function batch_repopulate_index_put($dataset_type=null, $limit=100, $start=0)
	{		
		try{
			$user_id=$this->get_api_user_id();
			
			if ($dataset_type==null){
				throw new Exception("DATASET_TYPE_IS_REQUIRED");
			}

			if(!is_numeric($start)){
				throw new Exception("PARAM:START-INVALID");
			}
			
			$datasets=$this->dataset_manager->get_list_by_type($dataset_type, $limit, $start);
			
			$output=array();
			foreach($datasets  as $dataset){
				$this->dataset_manager->repopulate_index($dataset['id']);
				$output[]=$dataset['id'];
				$last_processed=$dataset['id'];
			}
			
			$output=array(
				'status'=>'success',
				'datasets_updated'=>$output,
				'last_processed'=>$last_processed			
			);
			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}				
	}


	/**
	 * 
	 *  Repopulate index for a single study
	 * 	 
	 * 
	 */
	public function repopulate_index_get($idno=null)
	{		
		try{
			$user_id=$this->get_api_user_id();
			$sid=$this->get_sid_from_idno($idno);
						
			$result=$this->dataset_manager->repopulate_index($sid);
			
			$output=array(
				'status'=>'success',
				'result'=>$result				
			);
			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}				
	}



	function import_geospatial_post()
	{
		$overwrite=$this->input->post("overwrite");
		$repositoryid=$this->input->post("repositoryid");
		$dataset_type='geospatial';

		if (!$repositoryid){
			$repositoryid='central';
		}

		try{
					
			//process form
			$temp_upload_folder=get_catalog_root().'/tmp';
			
			if (!file_exists($temp_upload_folder)){
				@mkdir($temp_upload_folder);
			}
			
			if (!file_exists($temp_upload_folder)){
				show_error('DATAFILES-TEMP-FOLDER-NOT-SET');
			}

			//process file urls
			$file_url=$this->input->post("file");
			
			if(empty($_FILES['file']) && !empty($file_url) && $this->form_validation->valid_url($file_url)) {
				$uploaded_file_path=$temp_upload_folder.'/'.md5($file_url).'.xml';
				
				//download file from URL 
				$file_content=@file_get_contents($file_url);
				if($file_content===FALSE){
					throw new Exception("FAILED-TO-READ-FILE-URL");
				}

				if (!file_exists($uploaded_file_path)){
					//save to tmp 		
					if (file_put_contents($uploaded_file_path,$file_content)===FALSE){
						throw new Exception("FILE-UPLOAD-VIA-URL-FAILED");
					}
				}
			}
			//process file uploads
			else{
				$uploaded_file_path=$this->process_file_upload($temp_upload_folder,$allowed_file_types='xml',$file_field_name='file');
			}

			$options=array();
			$user_id=$this->get_api_user_id();
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			$options['published']=$this->input->post("published");			
			$options['overwrite']=$overwrite;
			$options['repositoryid']=$repositoryid;
		
			$this->load->library('Geospatial_import');
			$result=$this->geospatial_import->import($uploaded_file_path, $options);
			unlink($uploaded_file_path);
			
			$response=array(
				'status'=>'success',
				'dataset'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * 
	 * Return datasets list with tags
	 * 
	 */
	function tags_get($idno=null)
	{
		try{
			$result=$this->dataset_manager->get_dataset_with_tags($idno);
			$response=array(
				'status'=>'success',
				'found'=>count($result),
				'records'=>$result
			);		
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Return datasets aliases
	 * 
	 */
	function aliases_get($idno=null)
	{
		try{
			$result=$this->dataset_manager->get_dataset_aliases($idno);
			$response=array(
				'status'=>'success',
				'found'=>count($result),
				'records'=>$result
			);		
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}




	/**
	 * 
	 * 
	 * Generate PDF report
	 * 
	 * @IDNO - Survey IDNO
	 * 
	 */
	function generate_pdf_post($idno=null)
	{
		$this->load->helper('url_filter');

		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			
			//get sid from idno
			$sid=$this->get_sid_from_idno($idno);

			$dataset=$this->dataset_manager->get_row($sid);

			if($dataset['type']!='survey'){
				throw new Exception("PDF can only be generated for Surveys only");
			}

			$pdf_options=array(
				'publisher'=> $dataset['authoring_entity'],
				'website_title'=> $this->config->item("website_title"),
				'study_title'=> $dataset['title'],
				'website_url'=> site_url(),
				'toc_variable'=> isset($options['variable_toc']) ? (int)$options['variable_toc'] : 1,
				'data_dic_desc'=> isset($options['variable_description']) ? (int)$options['variable_description']: 1,
				'ext_resources'=> isset($options['include_resources']) ? (int)$options['include_resources'] : 1,
				'report_lang'=> isset($options['language']) ? $options['language'] : 'en'
			);

			//include external resources in the report?
			if($pdf_options['ext_resources']===1){
				$this->load->helper('Resource_helper');
				$this->load->model('Resource_model');
				
				$survey_resources=array();
				$survey_resources['resources']=$this->Resource_model->get_grouped_resources_by_survey($sid);
				$survey_resources['survey_folder']=$this->Catalog_model->get_survey_path_full($sid);

				$pdf_options['ext_resources_html']=$this->load->view('ddibrowser/report_external_resource',$survey_resources,TRUE);
			}

			$log_threshold= $this->config->item("log_threshold");
			$this->config->set_item("log_threshold",0);	//disable logging temporarily
			
			$report_link='';		
			$params=array('codepage'=>$pdf_options['report_lang']);

			$this->load->library('pdf_report',$params);// e.g. 'codepage' = 'zh-CN';
			$this->load->library('DDI_Browser','','DDI_Browser');
				
			//get ddi file path from db
			$ddi_file=$this->Catalog_model->get_survey_ddi_path($sid);
			$survey_folder=$this->Catalog_model->get_survey_path_full($sid);
			
			if ($ddi_file===FALSE || !file_exists($ddi_file)){
				throw new Exception('FILE_NOT_FOUND: '. $ddi_file);
			}
		
			//output report file name
			$report_file=unix_path($survey_folder.'/ddi-documentation-'.$this->config->item("language").'-'.$sid.'.pdf');
						
			if ($report_link=='')
			{			
				//change error logging to 0	
				$log_threshold= $this->config->item("log_threshold");
				$this->config->set_item("log_threshold",0);

				$start_time=date("H:i:s",date("U"));

				//write PDF report to a file
				$this->pdf_report->generate($report_file,$ddi_file,$pdf_options);
				$end_time=date("H:i:s",date("U"));
				
				//log
				$this->db_logger->write_log('survey','report generated '.$start_time.' -  '. $end_time,'ddi-report',$sid);

				//reset threshold level			
				$this->config->set_item("log_threshold",$log_threshold);
				
				$report_link=$report_file;
			}
			
			$response=array(
				'status'=>  'success',
				'options'=> $pdf_options,
				'dataset_id'=>$dataset['id'],
				'dataset_variables'=>$dataset['varcount'],
				'output'=>  $report_file
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * Return indexed keywords for the study
	 * 
	 */
	function keywords_get($idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($idno);
			$result=$this->Dataset_model->get_keywords($sid);			
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
			);			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
}
