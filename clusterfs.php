<?php

uses('model', 'uuid');

require_once(dirname(__FILE__) . '/model.php');

/* cluster://[<cluster-name>]/[<uuid>/][<uuid>/...][<uuid>]
 *
 * e.g.:
 *
 * cluster:///0bf8dfe8-baca-4f7a-aee4-d32ca8d5cdb1
 * cluster://production/8f8cafa6-f116-4df5-9cc7-680a91c235de
 * cluster:///
 */
 
class ClusterFS extends Model
{
	protected $cluster;
	protected $clusterName;
	protected $instanceName;
	protected $root;
	
	public static function getInstance($args = null, $className = null)
	{
		if(null === $args) $args = array();
		if(!isset($args['db'])) $args['db'] = CLUSTERFS_IRI;
		if(null === $className) $className = 'ClusterFS';
		return Model::getInstance($args, $className);
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
				if(!($iri = parse_url($iri)))
				{
					return null;
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
	
	protected function traverse($path)
	{
		$entry =  array('cluster_name' => $path['host'], 'fsobject_parent' => null, 'fsobject_uuid' => null, 'fsobject_name' => null, 'fsobject_mode' => 0040755, 'fsobject_virtual' => 'Y', 'fsobject_deleted' => 'N');
		foreach($path['pathcomp'] as $el)
		{
			if(!strlen($el)) continue;
			if(!($entry = $this->locate($entry['cluster_name'], $entry['fsobject_uuid'], $el)))
			{
				return null;
			}
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
			));
		}
		while(!$this->db->commit());
		return $this->db->row('SELECT * FROM {cluster_fs_object} WHERE "cluster_name" = ? AND "fsobject_uuid" = ?', $parent['cluster_name'], $uuid);
	}
	
	public function entry($path)
	{
		if(!($path = $this->parse($path))) return null;
		return $this->traverse($path);
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

	public function adopt($path, $parent = null, $ownerScheme = null, $ownerUUID = null)
	{
		if(!($info = stat($path)))
		{
			return null;
		}
		if(!($parent = $this->traverse($parent)))
		{
			return null;
		}
		$uuid = UUID::generate();
		$this->db->insert('cluster_fs_object', array(
			'cluster_name' => $parent['cluster'],
			'fsobject_uuid' => $uuid,
			'fsobject_parent' => $parent['uuid'],
			'fsobject_dev' => 0,
			'fsobject_ino' => 0,
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
			'fsobject_deleted' => 'N',
		));
		$this->db->insert('cluster_fs_instance', array(
			'instance_name' => $instance,
			'cluster_name' => $this->clusterName,
			'fsobject_uuid' => $uuid,
			'fsinstance_path' => $path,
			'fsinstance_active' => 'Y',
			'@fsinstance_created' => $this->db->now(),
			'@fsinstance_modified' => $this->db->now(),
		));
		return 'cluster://'. $this->clusterName . '/' . $uuid;
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
			if(isset($row['fsobject_name'])) return $row['fsobject_name'];
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
	}
	
	public function stream_close()
	{
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
}
