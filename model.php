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

class ClusterModel extends Model
{
	public $writeable = true;
	
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
}

class ClusterFileModel extends Model
{
	protected $clusters = array();
	protected $inst = array();
	public $instanceName;
	public $clusterName;
	
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
				$iinfo['cluster'] = $info['name'];
				$this->inst[$iinfo['name']] = $iinfo;
				$ilist[$iinfo['name']] = $iinfo['name'];
			}
			$info['instances'] = array_values($ilist);
			$this->clusters[$info['name']] = $info;
		}
	}
}
