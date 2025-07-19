<?php

namespace Snog\TV\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int node_id
 * @property array tv_genre
 *
 * @property \XF\Entity\Node Node
 */
class Node extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_snog_tv_node';
		$structure->shortName = 'Snog\TV:Node';
		$structure->contentType = 'tvnode';
		$structure->primaryKey = 'node_id';
		$structure->columns = [
			'node_id' => ['type' => self::UINT],
			'tv_genre' => ['type' => self::SERIALIZED_ARRAY, 'default' => []],
		];

		$structure->relations = [
			'Node' => [
				'entity' => 'XF:Node',
				'type' => self::TO_ONE,
				'conditions' => 'node_id',
				'primary' => true
			],
		];

		return $structure;
	}
}