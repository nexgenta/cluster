<?php

/* Eregansu Clusters
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

uses('error');

require_once(dirname(__FILE__) . '/model.php');
require_once(dirname(__FILE__) . '/clusterfs.php');

class ClusterCLI extends App
{
	public function __construct()
	{
		parent::__construct();

		$this->sapi['cli']['list'] = array('class' => 'ClusterListCommand', 'description' => 'List the defined clusters');
		$this->sapi['cli']['adopt'] = array('class' => 'ClusterAdoptCommand', 'description' => 'Adopt a pathname into ClusterFS');
	}
}

class ClusterListCommand extends CommandLine
{
	protected $modelClass = 'ClusterModel';
	
	public function main($args)
	{
		$clusters = $this->model->clusters();
		foreach($clusters as $c)
		{
			echo sprintf("%-20s  %s\n", $c['name'], $c['title']);
		}
	}
}

class ClusterAdoptCommand extends CommandLine
{
	protected $modelClass = 'ClusterFS';
	
	protected function checkargs(&$args)
	{
		if(count($args) < 1)
		{
			return $this->error(Error::NO_OBJECT, null, null, 'Usage: adopt LOCAL-FILE [PARENT]');
		}
		if(count($args) > 2)
		{
			return $this->error(Error::BAD_REQUEST, null, null, 'Usage: adopt LOCAL-FILE [PARENT]');
		}
		return true;
	}

	public function main($args)
	{
		$parent = $scheme = $uuid = null;
		if(isset($args[1]))
		{
			$parent = $args[1];
		}
		if(isset($this->session->user))
		{
			$scheme = $this->session->user['scheme'];
			$uuid = $this->session->user['uuid'];
		}
		if(!($iri = $this->model->adopt($args[0], $parent, $scheme, $uuid)))
		{
			return 1;
		}
		echo "Adopted as $iri\n";
		return 0;
	}
}

