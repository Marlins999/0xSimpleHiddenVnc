<?php

namespace Snog\TV\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int rating_id
 * @property int node_id
 * @property int user_id
 * @property int rating
 */
class RatingNode extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_snog_tv_ratings_node';
		$structure->shortName = 'Snog\TV:RatingNode';
		$structure->contentType = 'rating';
		$structure->primaryKey = 'rating_id';
		$structure->columns = [
			'rating_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'node_id' => ['type' => self::UINT],
			'user_id' => ['type' => self::UINT],
			'rating' => ['type' => self::UINT],
		];

		return $structure;
	}
}
