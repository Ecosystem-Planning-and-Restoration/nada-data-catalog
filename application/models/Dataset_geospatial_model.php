<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;


/**
 * 
 * Geospatial
 * 
 */
class Dataset_geospatial_model extends Dataset_model {
 
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Data_file_model');
        $this->load->model('Variable_model');
    }

    function create_dataset($type,$options)
	{
		//validate schema
        $this->validate_schema($type,$options);

        //get core fields for listing datasets in the catalog
        $core_fields=$this->get_core_fields($options);
        $options=array_merge($options,$core_fields);
		
		if(!isset($core_fields['idno']) || empty($core_fields['idno'])){
			throw new exception("IDNO-NOT-SET");
		}

		//validate IDNO field
        $dataset_id=$this->find_by_idno($core_fields['idno']); 

		//overwrite?
		if($dataset_id>0 && isset($options['overwrite']) && $options['overwrite']!=='yes'){
			throw new ValidationException("VALIDATION_ERROR", "IDNO already exists. ".$dataset_id);
        }
        
        //fields to be stored as metadata
        $study_metadata_sections=array('metadata_maintenance','dataset_description','additional');

        //external resources
        $external_resources=$this->get_array_nested_value($options,'dataset_description/distribution_info/online_resource');
        
        //remove external resource from metadata
        if(isset($options['dataset_description']['distribution_info']['online_resource'])){
            unset($options['dataset_description']['distribution_info']['online_resource']);
        }

        foreach($study_metadata_sections as $section){		
			if(array_key_exists($section,$options)){
                $options['metadata'][$section]=$options[$section];
                unset($options[$section]);
            }
        }                

		//start transaction
		$this->db->trans_start();
        
        if($dataset_id>0){
            $this->update($dataset_id,$type,$options);
        }
        else{
            $dataset_id=$this->insert($type,$options);
        }

		//update years
        $this->update_years($dataset_id,$core_fields['year_start'],$core_fields['year_end']);
        
        //import external resources
        $this->update_resources($dataset_id,$external_resources);

		//set topics

        //update related countries

		//set aliases

		//set geographic locations (bounding box)

		//complete transaction
		$this->db->trans_complete();

		return $dataset_id;
    }


    function update_dataset($sid,$type,$options, $merge_metadata=false)
	{
        //need this to validate IDNO for uniqueness
        $options['sid']=$sid;
        
        //merge/replace metadata
        if ($merge_metadata==true){
            $metadata=$this->get_metadata($sid);
            if(is_array($metadata)){
                unset($metadata['idno']);                
                $options=$this->array_merge_replace_metadata($metadata,$options);
                $options=array_remove_nulls($options);
            }
        }

        //validate schema
        $this->validate_schema($type,$options);

        //get core fields for listing datasets in the catalog
        $core_fields=$this->get_core_fields($options);
        $options=array_merge($options,$core_fields);
		
		//validate IDNO field
		$new_id=$this->find_by_idno($core_fields['idno']);

		//if IDNO is changed, it should not be an existing IDNO
		if(is_numeric($new_id) && $sid!=$new_id ){
			throw new ValidationException("VALIDATION_ERROR", "IDNO matches an existing dataset: ".$new_id.':'.$core_fields['idno']);
        }                

        $options['changed']=date("U");
        
        //fields to be stored as metadata
        $study_metadata_sections=array('metadata_maintenance','dataset_description','additional');

        foreach($study_metadata_sections as $section){		
			if(array_key_exists($section,$options)){
                $options['metadata'][$section]=$options[$section];
                unset($options[$section]);
            }
        }

		//start transaction
		$this->db->trans_start();
        
        $this->update($sid,$type,$options);

		//update years
		$this->update_years($sid,$core_fields['year_start'],$core_fields['year_end']);

		//set topics

        //update related countries

		//set aliases

		//set geographic locations (bounding box)

		//complete transaction
		$this->db->trans_complete();

		return $sid;
    }


    

    /**
	 * 
	 * get core fields 
	 * 
	 * core fields are: idno, title, nation, year, authoring_entity
	 * 
	 * 
	 */
	function get_core_fields($options)
	{        
        $output=array();
        $output['title']=$this->get_array_nested_value($options,'dataset_description/identification_info/title');
        $output['idno']=$this->get_array_nested_value($options,'dataset_description/file_identifier');

        //todo
        //$nations=$this->get_array_nested_value($options,'database_description/geographic_units');	
        $output['nation']='';//todo

        $output['abbreviation']=$this->get_array_nested_value($options,'dataset_description/identification_info/alternate_title');
        
        //$auth_entity=$this->get_array_nested_value($options,'database_description/authoring_entity');
        $output['authoring_entity']='';

        $years=$this->get_years($options);
        $output['year_start']=$years['start'];
        $output['year_end']=$years['end'];
        
        return $output;
    }
    


    /**
     * 
     * get years
     * 
     **/
	function get_years($options)
	{
		$years=explode("-",$this->get_array_nested_value($options,'dataset_description/date_stamp'));

		if(is_array($years)){
            $start=(int)$years[0];
            $end=(int)$years[0];			
        }

		return array(
			'start'=>$start,
			'end'=>$end
		);
    }
    

    //returns survey metadata array
    function get_metadata($sid)
    {
        $metadata= parent::get_metadata($sid);

        $res_fields="resource_id,dctype,dcformat,title,author,dcdate,country,language,contributor,publisher,rights,description, abstract,toc,filename";
        $external_resources=$this->Survey_resource_model->get_survey_resources($sid, $res_fields);
        
        //add download link
        foreach($external_resources as $resource_filename => $resource){
            if (!$this->form_validation->valid_url($resource['filename']) && !empty($resource['filename'])){
                $external_resources[$resource_filename]['filename']=site_url("catalog/{$sid}/download/{$resource['resource_id']}/".rawurlencode($resource['filename']) );
            }  
        }
        
        //add external resources
        $metadata['dataset_description']['distribution_info']['online_resource']=$external_resources;
       return $metadata;
	}

}