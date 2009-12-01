<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterInstanceView extends ClusterAdminPage
{
	protected $templateName = 'instance.phtml';
	public $cluster;
	
	protected function getObject()
	{
		$inst = $this->request->objects[1];
		if(!($this->object = $this->model->instanceInCluster($inst, $this->cluster['name'])))
		{
			return $this->error(Error::OBJECT_NOT_FOUND);
		}
		$this->pageTitle = $this->object['title'];
		$this->request->addCrumb(array('name' => $this->object['title'], 'link' => $this->request->pageUri . '-/' . $this->cluster['name'] . '/' . $inst), 'instance');
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['page_title'] = $this->object['title'];
		$this->vars['cluster'] = $this->cluster;
		$this->setActiveSourceListEntry('clusters', $this->cluster['name'], $this->object['name']);
	}
}
