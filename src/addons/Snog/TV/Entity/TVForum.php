<?php

namespace Snog\TV\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int node_id
 * @property int tv_parent
 * @property int tv_id
 * @property int tv_parent_id
 * @property int tv_season
 * @property string tv_image
 * @property string tv_genres
 * @property string tv_director
 * @property string tv_release
 * @property string tv_title
 * @property float tv_rating
 * @property int tv_votes
 * @property string tv_plot
 * @property string tv_cast
 * @property int tv_thread
 * @property int tv_issub
 * @property int tv_checked
 * @property int tv_poster
 *
 * RELATIONS
 * @property TVForum Parent
 * @property Rating[] Ratings
 */
class TVForum extends Entity
{
	public function hasRated(\XF\Entity\User $user = null)
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		if (!$user->user_id)
		{
			return false;
		}

		return !empty($this->Ratings[$user->user_id]);
	}

	public function getSeasonPosterUrl($noposter = null, $canonical = true)
	{
		$app = \XF::app();
		if (!$noposter)
		{
			return $app->applyExternalDataUrl("tv/SeasonPosters{$this->tv_image}", $canonical);
		}
		else
		{
			return $app->applyExternalDataUrl("tv/SeasonPosters/no-poster.png", $canonical);
		}
	}

	public function getForumPosterUrl($noposter = null, $canonical = true)
	{
		$app = \XF::app();
		if (!$noposter)
		{
			return $app->applyExternalDataUrl("tv/ForumPosters{$this->tv_image}", $canonical);
		}
		else
		{
			return $app->applyExternalDataUrl("tv/ForumPosters/no-poster.png", $canonical);
		}
	}

	public function rebuildRating($autoSave = false)
	{
		$rating = $this->db()->fetchRow("
			SELECT COUNT(*) AS total,
				SUM(rating) AS sum
			FROM xf_snog_tv_ratings_node
			WHERE node_id = ?
		", [$this->node_id]);

		$ratingSum = $rating['sum'] ?: 0;
		$total = $rating['total'] ?: 0;

		$this->tv_rating = round(($ratingSum / $total), 2);

		if ($autoSave)
		{
			$this->save();
		}
	}

	protected function _postDelete()
	{
		$db = $this->db();
		$db->delete('xf_snog_tv_ratings_node', 'node_id = ?', $this->node_id);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_snog_tv_forum';
		$structure->shortName = 'Snog\TV:TVForum';
		$structure->contentType = 'tvforum';
		$structure->primaryKey = 'node_id';
		$structure->columns = [
			'node_id' => ['type' => self::UINT],
			'tv_parent' => ['type' => self::UINT, 'default' => 0],
			'tv_id' => ['type' => self::UINT, 'default' => 0],
			'tv_parent_id' => ['type' => self::UINT, 'default' => 0],
			'tv_season' => ['type' => self::UINT, 'default' => 0],
			'tv_image' => ['type' => self::STR, 'default' => ''],
			'tv_genres' => ['type' => self::STR, 'default' => ''],
			'tv_director' => ['type' => self::STR, 'default' => ''],
			'tv_release' => ['type' => self::STR, 'default' => ''],
			'tv_title' => ['type' => self::STR, 'default' => ''],
			'tv_rating' => ['type' => self::FLOAT, 'default' => 0],
			'tv_votes' => ['type' => self::UINT, 'default' => 0],
			'tv_plot' => ['type' => self::STR, 'default' => ''],
			'tv_cast' => ['type' => self::STR, 'default' => ''],
			'tv_thread' => ['type' => self::UINT, 'default' => 0],
			'tv_issub' => ['type' => self::UINT, 'default' => 0],
			'tv_checked' => ['type' => self::UINT, 'default' => 0],
			'tv_poster' => ['type' => self::UINT, 'default' => 0],
		];

		$structure->relations = [
			'Parent' => [
				'entity' => 'Snog\TV:TVForum',
				'type' => self::TO_ONE,
				'conditions' => [
					['tv_id', '=', '$tv_parent_id']
				]
			],
			'Ratings' => [
				'entity' => 'Snog\TV:RatingNode',
				'type' => self::TO_MANY,
				'conditions' => 'node_id',
				'primary' => false
			],
		];

		return $structure;
	}
}
