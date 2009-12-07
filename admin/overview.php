<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterOverview extends ClusterAdminPage
{
	protected $title = 'Overview';
	protected $templateName = 'overview.phtml';

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
		}
		return true;
	}
	
}