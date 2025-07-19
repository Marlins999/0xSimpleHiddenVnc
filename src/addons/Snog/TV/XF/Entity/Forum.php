<?php

namespace Snog\TV\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \Snog\TV\Entity\Node TVnode
 * @property \Snog\TV\Entity\TVForum TVForum
 */
class Forum extends XFCP_Forum
{
	protected function _postDelete()
	{
		$this->db()->delete('xf_snog_tv_forum', 'node_id = ?', $this->node_id);
		parent::_postDelete();
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations += [
			'TVnode' => [
				'entity' => 'Snog\TV:Node',
				'type' => entity::TO_ONE,
				'conditions' => 'node_id',
				'primary' => true
			],
			'TVForum' => [
				'entity' => 'Snog\TV:TVForum',
				'type' => entity::TO_ONE,
				'conditions' => 'node_id',
				'primary' => true
			],
		];

		$structure->defaultWith[] = 'TVForum';
		$structure->defaultWith[] = 'TVnode';

		return $structure;
	}
}