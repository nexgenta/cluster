<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterView extends ClusterAdminPage
{
	protected $templateName = 'view.phtml';

	protected function getObject()
	{
		parent::getObject();
		$cluster = $this->request->objects[0];
		if(!($this->object = $this->model->cluster($cluster)))
		{
			return $this->error(Error::OBJECT_NOT_FOUND);
		}
		$this->title = $this->object['title'];
		$this->request->addCrumb(array('name' => $this->object['title'], 'link' => $this->request->pageUri . '-/' . $cluster,  'class' => 'cluster'), 'cluster');
		if(isset($this->request->objects[1]))
		{
			require_once(dirname(__FILE__) . '/instance.php');
			$r = new ClusterInstanceView();
			$r->cluster = $this->object;
			$r->process($this->request);
			return false;
		}
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['instances'] = $this->model->instancesInCluster($this->object['name']);
		$this->setActiveSourceListEntry('clusters', $this->object['name']);
	}
}
