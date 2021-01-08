<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * NADA Site Menu
 * 
 *
 * @category	Site Menu/Navigation
 *
 */ 
class Site_Menu
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "Site_Menu Class Initialized.");
		$this->ci =& get_instance();
		
		$this->ci->lang->load('site_menu');
		//$this->ci->output->enable_profiler(TRUE);
	}

	/**
	*
	* return an array of all menu items
	**/
	function get_menu_items_array()
	{
		//get parent items
		$parents=$this->ci->db->query('select * from site_menu where pid=0')->result_array();
		
		//get child items
		$children_rows=$this->ci->db->query('select * from site_menu where pid >0 order by weight DESC')->result_array();
		
		return $output=array(
			'parents'	=>$parents,
			'children'	=>$children_rows
		);
	}

	/**
	* Returns a formatted menu true for site navigation
	*
	**/
	function get_formatted_menu_tree($items=NULL)
	{
		if($items==NULL)
		{
			//get parent items
			$parents=$this->ci->db->query('select * from site_menu where pid=0')->result_array();
			
			//get child items
			$children_rows=$this->ci->db->query('select * from site_menu where pid >0 order by weight DESC')->result_array();
		}
		else{
			$parents=$items['parents'];
			$children_rows=isset($items['children']) ? $items['children'] : array() ;
		}		

		$children=array();
		foreach($children_rows as $item)
		{
			$children[$item['pid']][]=$item;
		}
		
		$menu_tree=array();
		foreach($parents as $parent)
		{
			$child_items=array();			
			foreach($children as $key=>$value)
			{
				if ($key!=$parent['id'])
				{
					continue;
				}
					foreach($value as $child)
					{
						if ($child['title']=='-')
						{
							//separator
							$child_items[]='<li class="dropdown-divider"></li>';
						}
						else
						{
							//submenu
							$submenu='';
							
							//for manage studies, add submenu
							if ($child['id']==34)
							{
								$submenu=$this->get_collections_submenu();
							}

							if ($submenu=='')
							{
								//first find children for the item
								$child_items[]=sprintf('<li class="dropdown-submenu"><a href="%s">%s</a>%s</li>',site_url($child['url']),t($child['title']),$submenu);
							}
							else
							{
								//with submenu - currently only for MANAGE STUDIES
								$child_items[]=sprintf('<li class="dropdown-submenu"><a tabindex="-1" data-toggle="dropdown" class="dropdown-item dropdown-toggle" href="%s">%s</a>%s</li>',site_url($child['url']),t($child['title']),$submenu);
							}							
						}	
					}	
			}
			
			if (count($child_items)>0)
			{
				//add parent + children
				$menu_tree[]=sprintf('<li class="dropdown"><a href="%s" class="dropdown-toggle" data-toggle="dropdown">%s<b class="caret"></b></a><ul class="dropdown-menu shadow-sm rounded-0x">%s</ul>'
									,$parent['url'],t($parent['title']),implode('',$child_items));
			}
			else
			{
				//parents with no children
				$menu_tree[]=sprintf('<li class="dropdown-submenu"><a href="%s">%s</a></li>',site_url($parent['url']),t($parent['title']));
			}						
		}
		return sprintf('<ul class="nav navbar-nav rounded-0x shadow-sm">%s</ul>',implode('',$menu_tree));
	}
	
	
	/**
	*
	* Formatted list of collections
	**/
	function get_collections_submenu()
	{
		//get active users repositories
		$repos=$this->ci->Repository_model->select_all();
		
		//add central collection
		array_unshift($repos, $this->ci->Repository_model->get_central_catalog_array()	);
		
		$output='<ul class="dropdown-menu rounded-0x">';
		
		foreach($repos as $repo)
		{
			$output.=sprintf('<li><a tabindex="-1" href="%s">%s</a></li>',site_url('admin/repositories/active/'.$repo['id'].'?destination=admin/catalog'),t($repo['title']));
		}
		
		$output.='</ul>';
				  
		return $output;
	}
	
}

