<?php

require_once(dirname(__FILE__) . '/app.php');

class ClusterView extends ClusterAdminPage
{
	protected $templateName = 'view.phtml';
	protected $supportedTypes = array('text/html', 'application/json', 'text/plain');

	protected function getObject()
	{
		parent::getObject();
		$cluster = $this->request->objects[0];
		if(!($this->object = $this->model->cluster($cluster)))
		{
			return $this->error(Error::OBJECT_NOT_FOUND);
		}
		$status = $this->model->clusterStatus($cluster);
		$this->object = array_merge($this->object, $status);
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
		$this->object['instances'] = array();
		$instances = $this->model->instancesInCluster($this->object['name']);
		$now = time();
		$date = strftime('%Y-%m-%d', $now);
		$year = strftime('%Y', $now);
		foreach($instances as $k => $inst)
		{
			$status = $this->model->instanceStatus($inst['name'], $this->object['name']);
			unset($inst['cluster']);
			$instances[$k] = array_merge($inst, $status);
			if(strlen($instances[$k]['timestamp']))
			{
				$when = $instances[$k]['unixtime'];
				$diff = $now - $when;
				if($diff < 1)
				{
					$instances[$k]['last-checkin'] = 'Just now';
				}
				else if($diff <= 120)
				{
					$instances[$k]['last-checkin'] = ($diff == 1 ? '1 second':$diff . ' seconds') . ' ago';
				}
				else if(!strcmp($date, strftime('%Y-%m-%d', $when)))
				{
					$instances[$k]['last-checkin'] = strftime('%H:%M:%S', $when);
				}
				else
				{
					$instances[$k]['last-checkin'] = strftime('%A %e %B, %Y at %H:%M:%S', $when);				
				}
			}
			else
			{
				$instances[$k]['last-checkin'] = 'Never';
			}
		}	
		$this->object['instances'] = $instances;	
		return true;
	}
	
	protected function perform_GET_Text()
	{
		header('Content-type: text/plain; charset=UTF-8');
		$fmt = "%-20s %-20s %-10s %s\n";
		echo sprintf($fmt, 'NAME', 'HOST', 'STATUS', 'LAST CHECKED IN');
		foreach($this->object['instances'] as $object)	
		{
			echo sprintf($fmt, $object['name'], $object['host'], $object['tag'], $object['last-checkin']);
		}
	}
		
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['instances'] = $this->object['instances'];
		$this->setActiveSourceListEntry('clusters', $this->object['name']);
	}
}
