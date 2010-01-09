<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterOverview extends ClusterAdminPage
{
	protected $title = 'Overview';
	protected $templateName = 'overview.phtml';
	protected $supportedTypes = array('text/html', 'application/json', 'text/plain');
	protected $fs = array();
	
	protected function getObject()
	{
		parent::getObject();
		if(isset($this->request->objects[0]))
		{
			if($this->request->objects[0] == 'new')
			{
				require_once(dirname(__FILE__) . '/new.php');
				$r = new ClusterCreate();
			}
			else
			{
				require_once(dirname(__FILE__) . '/view.php');
				$r = new ClusterView();				
			}
			$r->process($this->request);
			return false;
		}
		foreach($this->clusters as $cluster => $info)
		{
			$status = $this->model->clusterStatus($cluster);
			$this->clusters[$cluster]['class'] = $status['tag'];
			$this->clusters[$cluster]['status'] = $status['description'];
			foreach($status['monfs'] as $k => $fs)
			{
				$this->fs[$k] = $fs;
			}
		}
		$this->objects = $this->clusters;
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['fs'] = $this->fs;
		$this->useGlitter('tabs');
	}
	
	protected function perform_GET_Text()
	{
		header('Content-type: text/plain; charset=UTF-8');
		$fmt = "%-20s %-20s %-10s %s\n";
		echo sprintf($fmt, 'NAME', 'TITLE', 'STATUS', 'DETAIL');
		foreach($this->objects as $object)	
		{
			$title = $object['title'];
			if(strlen($title) > 20)
			{
				$title = trim(substr($title, 0, 19)) . '…';
			}
			echo sprintf($fmt, $object['name'], $title, $object['class'], $object['status']);
		}
	}
	
}