<?php

namespace Snog\TV\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \Snog\TV\Entity\TVForum TVForum
 */
class Node extends XFCP_Node
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations['TVForum'] = [
			'entity' => 'Snog\TV:TVForum',
			'type' => entity::TO_ONE,
			'conditions' => 'node_id',
		];

		$structure->defaultWith += ['TVForum'];

		return $structure;
	}

	protected function _postDelete()
	{
		parent::_postDelete();
		$tv_node = $this->finder('Snog\TV:TVForum')->where('node_id', $this->node_id)->fetchOne();
		if ($tv_node) $tv_node->delete();
	}
}