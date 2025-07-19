<?php

namespace Snog\TV\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \Snog\TV\Entity\TVPost TVPost
 */
class Post extends XFCP_Post
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations['TVPost'] = [
			'entity' => 'Snog\TV:TVPost',
			'type' => entity::TO_ONE,
			'conditions' => 'post_id',
			'primary' => true
		];

		return $structure;
	}
}