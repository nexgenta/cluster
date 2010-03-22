<?php

uses('model', 'uuid', 'url');

require_once(dirname(__FILE__) . '/model.php');

/* cluster://[<cluster-name>]/[<name>|<uuid>/][<name>|<uuid>/...][<name>|<uuid>]
 *
 * e.g.:
 *
 * cluster:///0bf8dfe8-baca-4f7a-aee4-d32ca8d5cdb1
 * cluster://production/8f8cafa6-f116-4df5-9cc7-680a91c235de
 * cluster:///
 */
 
if(!defined('CLUSTERFS_IRI')) define('CLUSTERFS_IRI', null);

class ClusterFS extends Model
{
	protected $cluster;
	protected $clusterName;
	protected $instanceName;
	protected $root;
	
	public static function getInstance($args = null, $className = null, $defaultDbIri = null)
	{
		return Model::getInstance($args, ($className ? $className : 'ClusterFS'), ($defaultDbIri ? $defaultDbIri : CLUSTERFS_IRI));
	}
	
	public function __construct($args)
	{
		parent::__construct($args);
		$this->cluster = ClusterModel::getInstance();
		$this->clusterName = $this->cluster->clusterName;
		$this->instanceName = $this->cluster->instanceName;
	}
	
	protected function parse($iri)
	{
		if(!is_array($iri))
		{
			if($iri == null)
			{
				$iri = array();
			}
			else
			{
				if(!($iri = URL::parse($iri)))
				{
					return null;
				}
				if(!isset($iri['host']))
				{
					$iri['host'] = $this->clusterName;				
				}
			}
		}
		if(!isset($iri['scheme']) || !strlen($iri['scheme'])) $iri['scheme'] = 'cluster';
		if(!isset($iri['host']) || !strlen($iri['host'])) $iri['host'] = $this->clusterName;
		if(!isset($iri['path'])) $iri['path'] = '/';
		if(!isset($iri['pathcomp']))
		{
			$iri['pathcomp'] = array();
			$x = explode('/', $iri['path']);
			foreach($x as $p)
			{
				if(!strlen($p)) continue;
				$iri['pathcomp'][] = $p;
			}
		}
		return $iri;
	}
	
	protected function traverse($path, $returnIRI = false)
	{
		$entry = array('cluster_name' => $path['host'], 'fsobject_parent' => null, 'fsobject_uuid' => null, 'fsobject_name' => null, 'fsobject_mode' => 0040755, 'fsobject_virtual' => 'Y', 'fsobject_deleted' => 'N');
		$root = $iri = 'cluster://' . $path['host'];
		foreach($path['pathcomp'] as $el)
		{
			if(!strlen($el)) continue;
			if(!($entry = $this->locate($entry['cluster_name'], $entry['fsobject_uuid'], $el)))
			{
				return null;
			}
			if(isset($entry['cluster_name']))
			{
				$iri .= $entry['cluster_name'] . '/';
			}
			else
			{
				$iri .= $entry['fsobject_uuid'] . '/';			
			}
		}
		if($returnIRI)
		{
			if(!strcmp($root, $iri)) $iri .= '/';
			$entry['iri'] = $iri;
		}
		return $entry;
	}
		
	protected function locate($clusterName, $parent, $nameOrUUID)
	{
		$row = null;
		if(strlen($nameOrUUID) == 36)
		{
			if($parent === null)
			{
				$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" IS NULL AND "fsobject_uuid" = ?', $clusterName, $nameOrUUID);
			}
			else
			{
				$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" = ? AND "fsobject_uuid" = ?', $clusterName, $parent, $nameOrUUID);
			}
		}
		if(!$row)
		{
			if($parent === null)
			{
				$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" IS NULL AND "fsobject_name" = ?', $clusterName, $nameOrUUID);
			}
			else
			{
				$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" = ? AND "fsobject_name" = ?', $clusterName, $parent, $nameOrUUID);
			}	
		}
		return $row;
	}
	
	public function create($path, $virtual = true, $ownerScheme = null, $ownerUUID = null, $mode = 0666)
	{
		if(!($path = $this->parse($path))) return null;
		if(!count($path['pathcomp'])) return null;
		$child = array_pop($path['pathcomp']);
		if(preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $child))
		{
			trigger_error('ClusterFS cannot create new directory entries where the name matches the format of UUIDs', E_USER_WARNING);
			return null;
		}
		if(!($parent = $this->traverse($path)))
		{
			return null;
		}
		if($virtual && $parent['fsobject_virtual'] != 'Y')
		{
			trigger_error('ClusterFS cannot create new virtual directory entries where the parent is not virtual', E_USER_WARNING);
			return null;
		}
		if(!strcmp($child, '<uuid>')) $child = null;
		if(strlen($child))
		{
			$cname = strtolower($child);
		}
		else
		{
			$cname = null;
		}
		$uuid = UUID::generate();		
		do
		{
			$this->db->begin();
			if(strlen($cname))
			{
				if($parent['fsobject_uuid'] == null)
				{
					if($this->db->value('SELECT "fsobject_uuid" FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" IS NULL AND "fsobject_cname" = ? ', $parent['cluster_name'], $cname))
					{
						$this->db->rollback();
						return null;
					}
				}
				else
				{
					if($this->db->value('SELECT "fsobject_uuid" FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" = ? AND "fsobject_cname" = ?', $parent['cluster_name'], $parent['fsobject_uuid'], $cname))
					{
						$this->db->rollback();
						return null;
					}
					
				}
			}
			$this->db->insert('cluster_fs_object', array(
				'cluster_name' => $parent['cluster_name'],
				'fsobject_uuid' => $uuid,
				'fsobject_name' => $child,
				'fsobject_cname' => $cname,
				'fsobject_parent' => $parent['fsobject_uuid'],
				'fsobject_dev' => 0,
				'fsobject_ino' => 0,
				'fsobject_mode' => $mode,
				'fsobject_nlink' => 1,
				'fsobject_uid' => 0,
				'fsobject_gid' => 0,
				'fsobject_rdev' => 0,
				'fsobject_size' => 0,
				'@fsobject_ctime' => $this->db->now(),
				'@fsobject_mtime' => $this->db->now(),
				'@fsobject_atime' => $this->db->now(),
				'fsobject_blksize' => 4096,
				'fsobject_blocks' => 0,
				'fsobject_creator_scheme' => $ownerScheme,
				'fsobject_creator_uuid' => $ownerUUID,
				'fsobject_modifier_scheme' => $ownerScheme,
				'fsobject_modifier_uuid' => $ownerUUID,
				'fsobject_deleted' => 'N',
				'fsobject_virtual' => ($virtual ? 'Y' : 'N'),
				'fsobject_remote' => null,
			));
		}
		while(!$this->db->commit());
		return $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_uuid" = ?', $parent['cluster_name'], $uuid);
	}
	
	public function entry($path, $returnIRI = false)
	{
		if(!($path = $this->parse($path))) return null;
		return $this->traverse($path, $returnIRI);
	}
	
	public function opendir($path)
	{
		if(!($path = $this->parse($path))) return null;
		if(!($path = $this->traverse($path)))
		{
			return null;
		}
		if($path['fsobject_uuid'] === null)
		{
			return $this->db->query('SELECT "fsobject_uuid", "fsobject_name" FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" IS NULL', $path['cluster_name']);
		}
		return $this->db->query('SELECT "fsobject_uuid", "fsobject_name" FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" = ?', $path['cluster_name'], $path['fsobject_uuid']);
	}

	public function autoName($path, $name)
	{
		if(!strlen($name))
		{
			trigger_error('ClusterFS: Attempt to rename ' . $path . ' to an empty name', E_USER_NOTICE);
			return false;
		}
		$name = explode('.', trim($name), 2);
		$base = $name[0];
		if(isset($name[1]))
		{
			$ext = '.' . $name[1];
		}
		else
		{
			$ext = '';
		}
		$c = 0;
		if(!($path = $this->parse($path))) return null;
		if(!($path = $this->traverse($path, true)))
		{
			return null;
		}
		if($path['fsobject_uuid'] === null)
		{
			trigger_error('ClusterFS: Attempt to rename the root directory denied', E_USER_NOTICE);
			return false;
		}
		do
		{
			if($c)
			{
				$name = $base . '-' . $c . $ext;
				$cname = strtolower($name);
			}
			else
			{			
				$name = $base . $ext;
				$cname = strtolower($name);
			}
			$exists = false;
			do
			{
				$this->db->begin();
				if($path['fsobject_parent'] === null)
				{
					$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" IS NULL AND "fsobject_cname" = ?', $path['cluster_name'], $cname);
				}
				else
				{
					$row = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_parent" = ? AND "fsobject_cname" = ?', $path['cluster_name'], $path['fsobject_parent'], $cname);			
				}
				if($row && strcmp($row['fsobject_uuid'], $path['fsobject_uuid']))
				{
					$c++;
					$exists = true;
					$this->db->rollback();
					break;
				}
				$this->db->exec('UPDATE {cluster_fs_object} SET "fsobject_name" = ?, "fsobject_cname" = ? WHERE "cluster_name" = ? AND "fsobject_uuid" = ?', $name, $cname, $path['cluster_name'], $path['fsobject_uuid']);
			}
			while(!$this->db->commit());
			if($exists)
			{
				continue;
			}
			return true;
		}
		while(true);
	}

	public function adopt($path, $parent = null, $ownerScheme = null, $ownerUUID = null)
	{
		if(stream_is_local($path))
		{	
			if(!($info = stat($path)))
			{
				return null;
			}
			$path = realpath($path);
		}
		if(!($parent = $this->parse($parent))) return null;
		if(!($parent = $this->traverse($parent, true)))
		{
			return null;
		}		
		if($parent['fsobject_virtual'] != 'Y')
		{
			trigger_error('ClusterFS: Cannot adopt a file into a non-virtual container', E_USER_ERROR);
			return null;
		}
		$baseIRI = $parent['iri'];
		if(substr($baseIRI, -1) != '/') $baseIRI .= '/';
		
		if(stream_is_local($path))
		{
			$path = str_replace('//', '/', $path);
			while(substr($path, -1) == '/') $path = substr($path, 0, -1);
			if(!strlen($path)) $path = '/';
			if(!($uuid = $this->_adopt($path, $info, $parent, null, $ownerScheme, $ownerUUID)))
			{
				return false;
			}
			$iri = $baseIRI . $uuid;
			$this->autoName($iri, basename($path));
		}
		else
		{
			if(!($uuid = $this->_adoptRemote($path, $parent, $ownerScheme, $ownerUUID)))
			{
				return false;
			}
			$iri = $baseIRI . $uuid;			
			if(@$info = parse_url($path))
			{
				if(isset($info['path']) && strlen($info['path']))
				{
					$base = basename($info['path']);
				}
				else
				{
					$base = '';
				}
				if(strlen($base))
				{
					$this->autoName($iri, basename($info['path']));
				}
				else if(isset($info['host']) && strlen($info['host']))
				{
					$this->autoName($iri, $info['host']);
				}
			}
		}
		$me = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_uuid" = ?', $this->clusterName, $uuid);
		print_r($me);
		$iri = $baseIRI;
		if(isset($me['fsobject_name']) && strlen($me['fsobject_name']))
		{
			$iri .= $me['fsobject_name'];
		}
		else
		{
			$iri .= $me['fsobject_uuid'];		
		}
		return $iri;
	}
	
	protected function _adopt($path, $info, $parent, $name, $ownerScheme, $ownerUUID)
	{
		$uuid = UUID::generate();
		$name = trim($name);
		if(strlen($name))
		{
			$cname = strtolower($name);
		}
		else
		{
			$name = $cname = null;
		}
		$this->db->insert('cluster_fs_object', array(
			'cluster_name' => $parent['cluster_name'],
			'fsobject_uuid' => $uuid,
			'fsobject_parent' => $parent['fsobject_uuid'],
			'fsobject_name' => $name,
			'fsobject_cname' => $cname,
			'fsobject_dev' => $info['dev'],
			'fsobject_ino' => $info['ino'],
			'fsobject_mode' => $info['mode'],
			'fsobject_nlink' => 1,
			'fsobject_uid' => $info['uid'],
			'fsobject_gid' => $info['gid'],
			'fsobject_rdev' => $info['rdev'],
			'fsobject_size' => $info['size'],
			'fsobject_ctime' => strftime('%Y-%m-%d %H:%M:%S', $info['ctime']),
			'fsobject_mtime' => strftime('%Y-%m-%d %H:%M:%S', $info['ctime']),
			'fsobject_atime' => null,
			'fsobject_blksize' => $info['blksize'],
			'fsobject_blocks' => $info['blocks'],
			'fsobject_creator_scheme' => $ownerScheme,
			'fsobject_creator_uuid' => $ownerUUID,
			'fsobject_modifier_scheme' => $ownerScheme,
			'fsobject_modifier_uuid' => $ownerUUID,
			'fsobject_virtual' => 'N',
			'fsobject_deleted' => 'N',
			'fsobject_remote' => null,
		));
		$me = $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_uuid" = ?', $this->clusterName, $uuid);
		$iri = $parent['iri'];
		if(substr($iri, -1) != '/') $iri .= '/';
		if(isset($me['fsobject_name']))
		{
			$iri .= $me['fsobject_name'];
		}
		else
		{
			$iri .= $me['fsobject_uuid'];		
		}
		$me['iri'] = $iri;
		$this->db->insert('cluster_fs_instance', array(
			'instance_name' => $this->instanceName,
			'cluster_name' => $this->clusterName,
			'fsobject_uuid' => $uuid,
			'fsinstance_path' => $path,
			'fsinstance_active' => 'Y',
			'@fsinstance_created' => $this->db->now(),
			'@fsinstance_modified' => $this->db->now(),
		));
		if(is_dir($path))
		{
			$d = opendir($path);
			while(($entry = readdir($d)))
			{
				if(!strcmp($entry, '.') || !strcmp($entry, '..')) continue;
				$info = stat($path . '/' . $entry);
				if(!($this->_adopt($path . '/' . $entry, $info, $me, $entry, $ownerScheme, $ownerUUID)))
				{
					closedir($d);
					$this->recursiveRemove($me);			
					return false;
				}
			}
			closedir($d);
		}
		return $uuid;
	}

	protected function _adoptRemote($iri, $parent, $ownerScheme, $ownerUUID)
	{
		$uuid = UUID::generate();
		$this->db->insert('cluster_fs_object', array(
			'cluster_name' => $parent['cluster_name'],
			'fsobject_uuid' => $uuid,
			'fsobject_parent' => $parent['fsobject_uuid'],
			'fsobject_name' => null,
			'fsobject_cname' => null,
			'fsobject_dev' => 0,
			'fsobject_ino' => 0,
			'fsobject_mode' => 0120666,
			'fsobject_nlink' => 1,
			'fsobject_uid' => 0,
			'fsobject_gid' => 0,
			'fsobject_rdev' => 0,
			'fsobject_size' => 0,
			'@fsobject_ctime' => $this->db->now(),
			'@fsobject_mtime' => $this->db->now(),
			'fsobject_atime' => null,
			'fsobject_blksize' => 4096,
			'fsobject_blocks' => 0,
			'fsobject_creator_scheme' => $ownerScheme,
			'fsobject_creator_uuid' => $ownerUUID,
			'fsobject_modifier_scheme' => $ownerScheme,
			'fsobject_modifier_uuid' => $ownerUUID,
			'fsobject_virtual' => 'N',
			'fsobject_deleted' => 'N',
			'fsobject_remote' => $iri,
		));
		return $uuid;
	}
	
	public function localPath($iri)
	{
		global $CLUSTERFS_TRANSPORT;
		
		if(!($iri = $this->parse($iri)))
		{
			return null;
		}
		if(!($iri = $this->traverse($iri)))
		{
			return null;
		}
		if(($row = $this->db->row('SELECT * FROM {cluster_fs_instance} WHERE "cluster_name" = ? AND "instance_name" = ? AND  "fsobject_uuid" = ?', $iri['cluster_name'], $this->instanceName, $iri['fsobject_uuid'])))
		{
			if($row['fsinstance_active'] == 'Y')
			{
				return $row['fsinstance_path'];
			}
		}
		if(($rrow = $this->db->rows('SELECT * FROM {cluster_fs_instance} WHERE "cluster_name" = ? AND "fsobject_uuid" = ? AND "fsinstance_active" = ?', $iri['cluster_name'], $iri['fsobject_uuid'], 'Y')))
		{
			foreach($rrow as $remote)
			{
				if(!($inst = $this->cluster->instanceInCluster($remote['instance_name'], $remote['cluster_name'])))
				{
					continue;
				}
				$host = $inst['host'];
				$transport = null;
				if(isset($CLUSTERFS_TRANSPORT[$host]))
				{
					$transport = $CLUSTERFS_TRANSPORT[$host];
				}
				else
				{
					$host = $this->cluster->host($host);
					if(isset($host['transport']))
					{
						$transport = $host['transport'];
					}
				}
				if(!$transport) continue;
				if(($localFile = $this->transport($remote, $inst, $transport)))
				{
					echo "Have $localFile!\n";
					return $localFile;
				}
				else
				{
					return null;
				}
			}
		}
	}
	
	protected function transport($remoteFsInst, $instance, $transport)
	{
		if(!defined('CLUSTERFS_ROOT'))
		{
			trigger_error('ClusterFS: Cannot transport resources because CLUSTERFS_ROOT is not defined', E_USER_NOTICE);
			return false;
		}
		$base = CLUSTERFS_ROOT;
		if(!file_exists($base)) mkdir($base);
		$base .= '/' . $remoteFsInst['cluster_name'];
		if(!file_exists($base)) mkdir($base);
		$base .= '/' . $remoteFsInst['fsobject_uuid'];
		$source = $transport['scheme'] . '://' . $transport['base'] . $remoteFsInst['fsinstance_path'];
		if(isset($transport['options']))
		{
			$options = $transport['options'];
		}
		else
		{
			$options = null;
		}
		uses('netcopy');
		if(NetCopy::copy($source, $base, $options))
		{
			return $base;
		}
		return null;
	}
}

class ClusterFSHandler
{
	public $context;

	protected $dir;
	protected $stream;

	protected static $model;
	
	protected static function init()
	{
		if(!self::$model) self::$model = ClusterFS::getInstance();
	}
	
	public function dir_opendir($path, $options)
	{
		self::init();
		if(($this->dir = self::$model->opendir($path)))
		{
			$this->dirPath = $path;
			return true;
		}
		return false;
	}
	
	public function dir_closedir()
	{
		$this->dir = null;
		$this->dirPath = null;
	}
	
	public function dir_readdir()
	{
		if(($row = $this->dir->next()))
		{
			if(isset($row['fsobject_name']) && strlen($row['fsobject_name'])) return $row['fsobject_name'];
			return $row['fsobject_uuid'];
		}
		return null;
	}
	
	public function dir_rewinddir()
	{
		$this->dir = null;
		if(($this->dir = self::$model->opendir($this->dirPath)))
		{
			return true;
		}
		return false;		
	}
	
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		self::init();
		if(!($opened_path = self::$model->localPath($path)))
		{
			if($options & STREAM_REPORT_ERRORS)
			{
				trigger_error('ClusterFS: Unable to locate resource for ' . $path, E_USER_WARNING);
			}
			return false;
		}
		if(!($this->stream = fopen($opened_path, $mode)))
		{
			return false;
		}
		return true;
	}
	
	public function stream_close()
	{
		fclose($this->stream);
	}
	
	public function stream_cast($as)
	{
		return $this->stream;
	}
	
	public function stream_eof()
	{
		return feof($this->stream);
	}
	
	public function stream_flush()
	{
		return fflush($this->stream);
	}
	
	public function stream_lock($operation)
	{
		return flock($this->stream, $operation);
	}
	
	public function stream_read($count)
	{
		return fread($this->stream, $count);
	}
	
	public function stream_write($data)
	{
		return fwrite($this->stream, $data);
	}
	
	public function stream_seek($offset, $whence = SEEK_SET)
	{
		return fseek($this->stream, $offset, $whence);
	}
	
	public function stream_stat()
	{
		return fstat($this->stream);
	}
	
	public function stream_tell()
	{
		return ftell($this->stream);
	}
	
	public function mkdir($path, $mode, $options)
	{
		self::init();
		if(!($info = self::$model->create($path, true, null, null, $mode | 0040000)))
		{
			return false;
		}
		return true;
	}
	
	public function url_stat($path, $flags)
	{
		self::init();
		if(!($info = self::$model->entry($path)))
		{
			return null;
		}
		$time = strtotime('2000-01-01 00:00:00');
		$stat = array(
			'dev' => 0,
			'ino' => 0,
			'mode' => 0644,
			'nlink' => 1,
			'uid' => 0,
			'gid' => 0,
			'rdev' => 0,
			'size' => 0,
			'atime' => $time,
			'mtime' => $time,
			'ctime' => $time,
			'blksize' => 4096,
			'blocks' => 0,
		);
		foreach($stat as $k => $v)
		{
			if(isset($info['fsobject_' . $k]))
			{
				if($k == 'atime' || $k == 'ctime' || $k == 'mtime')
				{
					$stat[$k] = strtotime($info['fsobject_' . $k]);
				}
				else
				{
					$stat[$k] = $info['fsobject_' . $k];
				}
			}
		}
		return $stat;
	}
	
	public function readlink($path)
	{
		self::init();
		if(!($info = self::$model->entry($path)))
		{
			return false;
		}
		if(!($info['fsobject_mode'] & 0120000))
		{
			trigger_error('ClusterFS::readlink(): ' . $path . ' is not a symbolic link', E_USER_WARNING);
			return false;
		}
		return $info['fsobject_remote'];
	}
	
	public function realpath($iri)
	{
		self::init();
		if(!($info = self::$model->entry($iri, true)))
		{
			return null;
		}
		return $info['iri'];
	}	
	
}
