<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;


/**
 * 
 * Document
 * 
 */
class Dataset_document_model extends Dataset_model {
 
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 
     * Update dataset
     * 
     * @merge_metadata - boolean
     *  true  - merge/update individual values
     *  false - replace all metadata with new values (no merge)
     * 
     */
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

        return $this->create_dataset($type,$options,$sid);
    }
    
    function create_dataset($type,$options, $sid=null)
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
                
        if(!empty($sid)){//for updating a study
            //if IDNO is changed, it should not be an existing IDNO
            if(is_numeric($dataset_id) && $sid!=$dataset_id ){
                throw new ValidationException("VALIDATION_ERROR", "IDNO matches an existing dataset: ".$dataset_id.':'.$core_fields['idno']);
            }

            $dataset_id=$sid;
        }
        else{//for creating new study or overwritting existing one
            if($dataset_id>0 && isset($options['overwrite']) && $options['overwrite']!=='yes'){
                throw new ValidationException("VALIDATION_ERROR", "IDNO already exists. ".$dataset_id);
            }
        }

        $options['changed']=date("U");
        
        //fields to be stored as metadata
        $study_metadata_sections=array('metadata_information','document_description','files','resources','provenance','embeddings','lda_topics','tags','additional');

        //external resources
        $external_resources=$this->get_array_nested_value($options,'resources');
        
        //remove external resource from metadata
        if(isset($options['resources'])){
            unset($options['resources']);
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

		//update tags
        $this->update_survey_tags($dataset_id, $this->get_tags($options['metadata']));

        //import external resources
        $this->update_resources($dataset_id,$external_resources);

        //update related countries
        $this->Survey_country_model->update_countries($dataset_id,$core_fields['nations']);

		//set aliases

		//set geographic locations (bounding box)

		//complete transaction
		$this->db->trans_complete();

		return $dataset_id;
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
        $output['title']=$this->get_array_nested_value($options,'document_description/title_statement/title');
        $output['idno']=$this->get_array_nested_value($options,'document_description/title_statement/idno');

        $nations=(array)$this->get_array_nested_value($options,'document_description/ref_country');
        $nations=array_column($nations,'name');

        $output['nations']=$nations;
        $output['nation']=$this->get_country_names_string($nations);

        $output['abbreviation']=$this->get_array_nested_value($options,'document_description/title_statement/alternate_title');            
        $authors=$this->get_array_nested_value($options,'document_description/authors');
        
        $output['authoring_entity']='';

        if(is_array($authors)){
            $authors_str=array();
            foreach($authors as $author){
                $tmp=array();
                $tmp[]=$this->get_array_nested_value($author,'first_name');
                $tmp[]=$this->get_array_nested_value($author,'last_name');

                $authors_str[]=implode(" ", $tmp);
            }

            $output['authoring_entity']=implode(", ",$authors_str);
        }

        

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
        $years=explode("-",$this->get_array_nested_value($options,'document_description/date_published'));

        if(is_array($years)){
            $start=(int)$years[0];
            $end=(int)$years[0];			
        }

		return array(
			'start'=>$start,
			'end'=>$end
		);
    }
    


    /**
     * 
     * Update all related tables used for facets/filters
     * 
     * 
     */
    function update_filters($sid, $metadata)
    {
        $core_fields=$this->get_core_fields($metadata);

        //update years
		$this->update_years($sid,$core_fields['year_start'],$core_fields['year_end']);

		//set topics

        //update related countries
        $this->Survey_country_model->update_countries($sid,$core_fields['nations']);
    }


    /**
     * 
     * get tags
     * 
     **/
	function get_tags($options)
	{
        $tags=$this->get_array_nested_value($options,'tags');

        if(!is_array($tags)){
           return false;
        }

        $output=array();
        foreach($tags as $tag){
            $output[]=$tag['tag'];
        }

        return $output;
    }


    function get_metadata($sid)
    {
        $metadata= parent::get_metadata($sid);

        $res_fields="resource_id,dctype,dcformat,title,author,dcdate,country,language,contributor,publisher,rights,description, abstract,toc,filename";
        $external_resources=$this->Survey_resource_model->get_survey_resources($sid, $res_fields);
        
        //add download link
        foreach($external_resources as $resource_filename => $resource){

            if (!$this->form_validation->valid_url($resource['filename'])){
                $external_resources[$resource_filename]['filename']=site_url("catalog/{$sid}/download/{$resource['resource_id']}/".rawurlencode($resource['filename']) );
            }
        }
        
        //add external resources
        $metadata['resources']=$external_resources;
       return $metadata;
	}
}