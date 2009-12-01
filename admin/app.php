<?php

require_once(APPS_ROOT . 'admin/router.php');
require_once(dirname(__FILE__) . '/../model.php');

class ClusterAdminApp extends App
{
	public $skin = 'cluster-admin';
	protected $crumbName = 'Clusters';
	protected $crumbClass = 'clusters';
	
	public function __construct()
	{
		parent::__construct();
		$this->routes['__NONE__'] = array('class' => 'ClusterOverview', 'file' => 'admin/overview.php', 'require' => 'com.nexgenta.admin.cluster');
	}
}

class ClusterAdminPage extends AdminPage
{
	protected $modelClass = 'ClusterModel';

	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->useGlitter('source-list');
		$this->vars['page_type'] = 'cluster-admin';
		$this->vars['clusters'] = $this->model->clusters();
		$this->vars['source_list_cookie'] = 'cluster';
		$this->vars['source_list'] = array(
			'clusters' => array('name' => 'Clusters', 'children' => array(
			)),
		);
		foreach($this->vars['clusters'] as $name => $cluster)
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
	}
}