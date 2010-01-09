<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterHost extends ClusterAdminPage
{
	protected $templateName = 'host.phtml';
	protected $supportedTypes = array('text/html', 'application/json', 'text/plain');

	protected function getObject()
	{
		parent::getObject();
		
		$this->request->addCrumb(array('name' => 'Hosts', 'link' => $this->request->pageUri . 'host', 'class' => 'hosts'), 'hosts');
		if(isset($this->request->objects[0]))
		{
			if(!($this->object = $this->model->host($this->request->objects[0])))
			{
				return $this->error(Error::OBJECT_NOT_FOUND);
			}
			$status = $this->model->hostStatus($this->object['name']);
			$this->object = array_merge($this->object, $status);
			$this->title = $this->object['title'];
			$this->request->addCrumb(array('name' => $this->object['title'], 'link' => $this->request->pageUri . 'host/-/' . $this->object['name'], 'class' => 'host'), 'host');
		}
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['page_type'] .= ' host-overview';
		if($this->object)
		{
			$this->setActiveSourceListEntry('hosts', $this->object['name']);
		}	
	}
}