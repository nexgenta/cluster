<?php

require_once(MODULES_ROOT . 'admin/router.php');
require_once(dirname(__FILE__) . '/../model.php');

class ClusterAdminApp extends App
{
	public $skin = 'cluster-admin';
	protected $crumbName = 'Clusters';
	protected $crumbClass = 'clusters';
}

class ClusterAdminPage extends AdminPage
{
	protected $modelClass = 'ClusterModel';
	protected $clusters;
	protected $hosts;
	
	protected function getObject()
	{
		$this->clusters = $this->model->clusters();
		$this->hosts = $this->model->hosts();
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->useGlitter('source-list');
		$this->vars['page_type'] = 'cluster-admin';
		$this->vars['clusters'] = $this->clusters;
		$this->vars['hosts'] = $this->hosts;
		$this->vars['source_list_cookie'] = 'cluster';
		if(isset($this->vars['global_nav']['cluster']))
		{
			$this->vars['global_nav']['cluster']['class'] .= ' active';
		}				
		$this->vars['source_list'] = array(
			'clusters' => array('name' => 'Clusters', 'children' => array(
			)),
			'hosts' => array('name' => 'Hosts', 'children' => array(
			)),
		);
		foreach($this->clusters as $name => $cluster)
		{
			$this->vars['source_list']['clusters']['children'][$name] = array(
				'name' => $cluster['title'],
				'link' => $this->request->base . '-/' . $name,
				'class' => 'cluster',
				'children' => array(),
			);
			$instances = $this->model->instancesInCluster($name);
			foreach($instances as $iname => $inst)
			{
				$this->vars['source_list']['clusters']['children'][$name]['children'][$iname] = array(
					'name' => $inst['title'],
					'link' => $this->request->base . '-/' . $name . '/' . $iname,
					'class' => 'instance',
				);
			}
		}
		foreach($this->vars['hosts'] as $name => $info)
		{
			$this->vars['source_list']['hosts']['children'][$name] = array(
				'name' => $name,
				'link' => $this->request->base . 'host/-/' . $name,
				'class' => 'host',
				'children' => array(),
			);			
		}
		
	}
}