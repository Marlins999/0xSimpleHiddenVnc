<?php

namespace Snog\TV\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \Snog\TV\Entity\TV TV
 */
class Thread extends XFCP_Thread
{
	protected function _postDelete()
	{
		if ($this->TV)
		{
			$this->TV->delete();
		}

		parent::_postDelete();
	}

	protected function _postDeletePosts(array $postIds)
	{
		$this->db()->delete('xf_snog_tv_post', 'post_id IN (' . $this->db()->quote($postIds) . ')');
		parent::_postDeletePosts($postIds);
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations['TV'] = [
			'entity' => 'Snog\TV:TV',
			'type' => entity::TO_ONE,
			'conditions' => 'thread_id',
			'primary' => true
		];

		$structure->defaultWith[] = 'TV';

		return $structure;
	}
}