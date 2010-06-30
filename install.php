<?php

/* Eregansu Custers
 *
 * Copyright 2010 Mo McRoberts.
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

class ClusterModuleInstall extends ModuleInstaller
{
	public $moduleOrder = 50;
	
	public function writeAppConfig($file)
	{
//		fwrite($file, "\$SETUP_MODULES[] = array('name' => 'clusterfs', 'file' => 'module.php', 'class' => 'ClusterFSModule');\n");

		fwrite($file, "\$CLI_ROUTES['cluster'] = array('class' => 'ClusterCLI', 'name' => 'cluster', 'file' => 'cli.php', 'description' => 'Cluster management commands');\n");

		fwrite($file, "\$AUTOLOAD['clusterfshandler'] = MODULES_ROOT . 'cluster/clusterfs.php';\n");

		fwrite($file, "\$VFS['cluster'] = 'ClusterFSHandler';\n");

		fwrite($file, "\$ADMIN_ROUTES['cluster'] = array('class' => 'ClusterAdminApp', 'file' => 'admin/app.php', 'adjustBase' => true, 'require' => 'com.nexgenta.admin.cluster', 'title' => 'Clusters', 'linkClass' => 'clusters', 'routes' => array(\n" .
			"\t'__NONE__' => array('class' => 'ClusterOverview', 'file' => 'admin/overview.php', 'require' => 'com.nexgenta.admin.cluster'),\n" .
			"\t'host' => array('class' => 'ClusterHost', 'file' => 'admin/host.php', 'require' => 'com.nexgenta.admin.cluster'),\n" .
			"));\n");

		fwrite($file, "\n");
	}
	
	public function writeInstanceConfig($file)
	{
		fwrite($file, '/* To configure clustering, use one of the following: */' . "\n\n");
		fwrite($file, '/* Read cluster configuration from a file named cluster.xml in your configuration directory: */' . "\n");
		fwrite($file, "/* define('CLUSTER_IRI', 'file:clusters.xml'); */\n");
		fwrite($file, '/* Read cluster configuration from a file named cluster.xml with an absolute path: */' . "\n");
		fwrite($file, "/* define('CLUSTER_IRI', 'file:/path/to/clusters.xml'); */\n\n");
		fwrite($file, '/* To enable ClusterFS, configure CLUSTERFS_IRI to be a database connection URL */' . "\n");
		$this->writePlaceholderDBIri($file, 'CLUSTERFS_IRI');
		fwrite($file, "\n");
	}

	public function createLinks()
	{
		$this->linkTemplates('admin/templates', 'cluster-admin');
	}

}
