<?php

/* Eregansu Clustering
 *
 * Copyright 2009 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

uses('model');

if(!defined('INSTANCE_NAME')) define('INSTANCE_NAME', php_uname('n'));

if(!defined('CLUSTER_HEARTBEAT_THRESHOLD')) define('CLUSTER_HEARTBEAT_THRESHOLD', 60);

class ClusterModel extends Model
{
	public $writeable = true;
	protected $heartbeat;
	public $instanceName;
	public $clusterName;
	public $hostName;

	public static function getInstance($args = null, $className = null)
	{
		if(null === $args) $args = array();
		if(!isset($args['db'])) $args['db'] = CLUSTER_IRI;
		if(null === $className)
		{
			$className = 'ClusterModel';
			if(!strncmp($args['db'], 'file:', 5))
			{
				$className = 'ClusterFileModel';
			}
		}
		return Model::getInstance($args, $className);
	}
	
	public function clusterStatus($cluster)
	{
		$fs = array();
		if(!defined('HEARTBEAT_IRI'))
		{
			return array('tag' => 'unknown', 'description' => 'Unknown', 'monfs' => $fs);
		}
		require_once(APPS_ROOT . 'heartbeat/model.php');
		if(!$this->heartbeat) $this->heartbeat = HeartbeatModel::getInstance();
		$instances = $this->instancesInCluster($cluster);
		$online = 0;
		$offline = 0;
		$alert = 0;
		$now = time();
		foreach($instances as $inst)
		{
			$status = $this->instanceStatus($inst['name'], $cluster);
			if(!$status) continue;
			if($status['tag'] == 'offline')
			{
				$offline++;
			}
			else if($status['tag'] == 'online')
			{
				$online++;
			}
			else if($status['tag'] == 'alert')
			{
				$alert++;
			}
			if(isset($status['monfs']))
			{
				foreach($status['monfs'] as $k => $mfs)
				{
					$fs[$k] = $mfs;
				}
			}
/*			$host = $this->host($inst['host']);
			$status = $this->heartbeat->lastPulse($inst['host']);
			if(!$status) continue;
			if($status['unixtime'] + CLUSTER_HEARTBEAT_THRESHOLD < $now) continue;
			$online++; */
		}
		if(!$online)
		{
			return array('tag' => 'offline', 'description' => 'No instances are online', 'monfs' => $fs);
		}
		if($online == count($instances))
		{
			return array('tag' => 'online', 'description' => 'All instances are online', 'monfs' => $fs);
		}
		return array('tag' => 'alert', 'description' => 'Some instances are offline', 'monfs' => $fs);
	}
	
	public function instanceStatus($instance, $clusterName = null)
	{
		if(!defined('HEARTBEAT_IRI'))
		{
			return array('tag' => 'unknown', 'status' => 'Unknown', 'timestamp' => null, 'unixtime' => null);
		}
		require_once(APPS_ROOT . 'heartbeat/model.php');
		if(!$this->heartbeat) $this->heartbeat = HeartbeatModel::getInstance();		
		$inst = $this->instanceInCluster($instance, $clusterName);
		$info = $this->heartbeat->lastPulse($inst['host']);
		$host = $this->host($inst['host']);		
		if(!$info || $info['unixtime'] + CLUSTER_HEARTBEAT_THRESHOLD < time())
		{
			$info['tag'] = 'offline';
			$info['status'] = 'This instance is currently offline';
		}
		else
		{
			$info['tag'] = 'online';
			$info['status'] = 'Online';
		}
		if(!isset($info['timestamp']))
		{
			$info['timestamp'] = $info['unixtime'] = null;
		}
		$info['monfs'] = array();
		if(isset($info['mib']['fs']))
		{
			foreach($host['fs'] as $mfs)
			{
				foreach($info['mib']['fs'] as $hfs)
				{
					if(isset($mfs['mountpoint']) && !strcmp($mfs['mountpoint'], $hfs['mountpoint']))
					{
						$hfs['host'] = $inst['host'];
						$info['monfs'][$inst['host'] . ':' . $hfs['mountpoint']] = $hfs;
					}
				}
			}
		}
		return $info;
	}
	
	public function hostStatus($host)
	{
		if(!defined('HEARTBEAT_IRI'))
		{
			return array('tag' => 'unknown', 'status' => 'Unknown', 'timestamp' => null, 'unixtime' => null);
		}
		require_once(APPS_ROOT . 'heartbeat/model.php');
		if(!$this->heartbeat) $this->heartbeat = HeartbeatModel::getInstance();		
		$info = $this->heartbeat->lastPulse($host);
		if(!$info || $info['unixtime'] + CLUSTER_HEARTBEAT_THRESHOLD < time())
		{
			$info['tag'] = 'offline';
			$info['status'] = 'This instance is currently offline';
		}
		else
		{
			$info['tag'] = 'online';
			$info['status'] = 'Online';
		}
		if(!isset($info['timestamp']))
		{
			$info['timestamp'] = $info['unixtime'] = null;
		}
		return $info;		
	}
}

class ClusterFileModel extends ClusterModel
{
	protected $clusters = array();
	protected $inst = array();
	protected $hosts = array();
	
	public function __construct($args)
	{
		$this->writeable = false;
		$path = $args['db'];
		if(strncmp($args['db'], 'file:', 5))
		{
			trigger_error('Invalid file: IRI passed to ClusterFileModel::__construct()', E_USER_ERROR);
			return;
		}
		$path = substr($args['db'], 5);
		while(substr($path, 0, 2) == '//') $path = substr($path, 1);
		if(!strlen($path))
		{		
			trigger_error('Empty file: IRI passed to ClusterFileModel::__construct()', E_USER_ERROR);
			return;
		}
		if($path[0] != '/') $path = CONFIG_ROOT . $path;
		$this->readClustersFromFile($path);
		$this->instanceName = INSTANCE_NAME;
		if(!isset($this->inst[INSTANCE_NAME]))
		{
			trigger_error('Current instance ' . INSTANCE_NAME . ' is not defined in any cluster (define INSTANCE_NAME to override the default or modify ' . $path . ' to add it to a cluster)', E_USER_ERROR);
		}
		$this->clusterName = $this->inst[INSTANCE_NAME]['cluster'];
		$this->hostName = $this->inst[INSTANCE_NAME]['host'];
	}
	
	public function hosts()
	{
		return $this->hosts;
	}
	
	public function instancesOnHost($host)
	{
		if(isset($this->hosts[$host])) return $this->hosts[$host]['instances'];
		return null;
	}
	
	public function host($name)
	{
		if(!isset($this->hosts[$name])) return null;
		return $this->hosts[$name];
	}
	
	public function clusters()
	{
		return $this->clusters;
	}
	
	public function cluster($name)
	{
		if(isset($this->clusters[$name])) return $this->clusters[$name];
		return null;
	}
	
	public function clusterNameOfInstance($inst)
	{
		if(isset($this->inst[$inst]))
		{
			return $this->inst[$inst]['cluster'];
		}
		return null;
	}
	
	public function instancesInCluster($clusterName = null)
	{
		if(!strlen($clusterName)) $clusterName = $this->clusterName;
		$list = array();
		if(!isset($this->clusters[$clusterName])) return null;
		foreach($this->clusters[$clusterName]['instances'] as $inst)
		{
			$list[$inst] = $this->inst[$inst];
		}
		return $list;
	}
	
	public function instanceInCluster($instanceName, $clusterName = null)
	{
		if(!strlen($clusterName)) $clusterName = $this->clusterName;
		if(!isset($this->clusters[$clusterName])) return null;
		if(!in_array($instanceName, $this->clusters[$clusterName]['instances'])) return null;
		return $this->inst[$instanceName];
	}
		
	protected function readClustersFromFile($path)
	{
		$xml = simplexml_load_file($path);
		foreach($xml->host as $host)
		{
			$info = array();
			$attrs = $host->attributes();
			foreach($attrs as $a)
			{
				$k = $a->getName();
				$v = trim($a);
				$info[$k] = $v;
			}
			$info['fs'] = array();
			foreach($host->fs as $fs)
			{
				$fsinfo = array();
				$attrs = $fs->attributes();
				foreach($attrs as $a)
				{
					$k = $a->getName();
					$v = trim($a);
					$fsinfo[$k] = $v;
				}
				$info['fs'][] = $fsinfo;	
			}
			if(!isset($info['name']))
			{
				trigger_error('Skipping defined host with no name', E_USER_WARNING);
				continue;
			}		
			$info['instances'] = array();
			if(!isset($info['title'])) $info['title'] = $info['name'];
			if(isset($this->hosts[$info['name']]))
			{
				trigger_error('Cluster ' . $info['name'] . ' is defined more than once; the most recent definition will be used', E_USER_NOTICE);				
			}
			$this->hosts[$info['name']] = $info;
		}
		foreach($xml->cluster as $cluster)
		{
			$info = array();
			$attrs = $cluster->attributes();
			foreach($attrs as $a)
			{
				$k = $a->getName();
				$v = trim($a);
				$info[$k] = $v;
			}
			if(!isset($info['name']))
			{
				trigger_error('Skipping defined cluster with no name', E_USER_WARNING);
				continue;
			}
			if(!isset($info['title'])) $info['title'] = $info['name'];
			if(isset($this->cluster[$info['name']]))
			{
				trigger_error('Cluster ' . $info['name'] . ' is defined more than once; the most recent definition will be used', E_USER_NOTICE);
			}
			$ilist = array();
			foreach($cluster->instance as $inst)
			{
				$iinfo = array();
				$attrs = $inst->attributes();
				foreach($attrs as $a)
				{
					$k = $a->getName();
					$v = trim($a);
					$iinfo[$k] = $v;
				}
				if(!isset($iinfo['name']))
				{
					trigger_error('Skipping defined instance with no name', E_USER_WARNING);
					continue;
				}
				if(!isset($iinfo['title'])) $iinfo['title'] = $iinfo['name'];				
				if(isset($this->inst[$iinfo['name']]))
				{
					trigger_error('Instance ' . $iinfo['name'] . ' is defined more than once; the most recent definition will be used', E_USER_NOTICE);
				}
				if(!isset($iinfo['host'])) $iinfo['host'] = $iinfo['name'];
				if(!isset($this->hosts[$iinfo['host']])) $this->hosts[$iinfo['host']] = array('name' => $iinfo['host'], 'title' => $iinfo['host'], 'instances' => array(), 'fs' => array());
				$this->hosts[$iinfo['host']]['instances'][] = $iinfo['name'];
				$iinfo['cluster'] = $info['name'];
				$this->inst[$iinfo['name']] = $iinfo;
				$ilist[$iinfo['name']] = $iinfo['name'];
			}
			$info['instances'] = array_values($ilist);
			$this->clusters[$info['name']] = $info;
		}
	}
}
