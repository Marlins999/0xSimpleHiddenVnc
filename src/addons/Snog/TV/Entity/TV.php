<?php

namespace Snog\TV\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int thread_id
 * @property string tv_image
 * @property string tv_genres
 * @property string tv_director
 * @property string tv_cast
 * @property string tv_release
 * @property string tv_title
 * @property string tv_id
 * @property int tv_season
 * @property int tv_episode
 * @property float tv_rating
 * @property int tv_votes
 * @property string tv_plot
 * @property int tv_thread
 * @property int tv_checked
 * @property string tv_trailer
 * @property string comment
 * @property int tv_poster
 *
 * RELATIONS
 * @property \XF\Entity\Thread Thread
 * @property Rating[] Ratings
 */
class TV extends Entity
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

	public function getImageUrl($sizeCode, $noposter = null, $canonical = true)
	{
		$app = \XF::app();
		if ($sizeCode == 'l')
		{
			if (!$noposter)
			{
				$image = str_ireplace('/', '', $this->tv_image);
				return $app->applyExternalDataUrl("tv/LargePosters/{$this->thread_id}-{$image}", $canonical);
			}
			else
			{
				return $app->applyExternalDataUrl("tv/LargePosters/no-poster.png", $canonical);
			}
		}

		if ($sizeCode == 's')
		{
			if (!$noposter)
			{
				$image = str_ireplace('/', '', $this->tv_image);
				return $app->applyExternalDataUrl("tv/SmallPosters/{$this->thread_id}-{$image}", $canonical);
			}
			else
			{
				return $app->applyExternalDataUrl("tv/SmallPosters/no-poster.png", $canonical);
			}
		}

		return null;
	}

	public function getEpisodePosterUrl($noposter = null, $canonical = true)
	{
		$app = \XF::app();
		if (!$noposter)
		{
			return $app->applyExternalDataUrl("tv/EpisodePosters{$this->tv_image}", $canonical);
		}

		return $app->applyExternalDataUrl("tv/EpisodePosters/no-poster.png", $canonical);
	}

	public function getEpisodePosterCDN($noposter = null)
	{
		if (!$noposter)
		{
			return \XF::options()->TvThreads_cdn_path . '/tv/EpisodePosters' . $this->tv_image;
		}

		return \XF::options()->TvThreads_cdn_path . '/tv/EpisodePosters/no-poster.png';
	}

	public function rebuildRating($autoSave = false)
	{
		$rating = $this->db()->fetchRow("
			SELECT COUNT(*) AS total,
				SUM(rating) AS sum
			FROM xf_snog_tv_ratings
			WHERE thread_id = ?
		", [$this->thread_id]);

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
		$db->delete('xf_snog_tv_ratings', 'thread_id = ?', $this->thread_id);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_snog_tv_thread';
		$structure->shortName = 'Snog\TV:TV';
		$structure->contentType = 'tv';
		$structure->primaryKey = 'thread_id';
		$structure->columns = [
			'thread_id' => ['type' => self::UINT],
			'tv_image' => ['type' => self::STR, 'default' => ''],
			'tv_genres' => ['type' => self::STR, 'default' => ''],
			'tv_director' => ['type' => self::STR, 'default' => ''],
			'tv_cast' => ['type' => self::STR, 'default' => ''],
			'tv_release' => ['type' => self::STR, 'default' => ''],
			'tv_title' => ['type' => self::STR, 'default' => ''],
			'tv_id' => ['type' => self::STR, 'default' => ''],
			'tv_season' => ['type' => self::UINT, 'default' => 0],
			'tv_episode' => ['type' => self::UINT, 'default' => 0],
			'tv_rating' => ['type' => self::FLOAT, 'default' => 0],
			'tv_votes' => ['type' => self::UINT, 'default' => 0],
			'tv_plot' => ['type' => self::STR, 'default' => ''],
			'tv_thread' => ['type' => self::UINT, 'default' => 0],
			'tv_checked' => ['type' => self::UINT, 'default' => 0],
			'tv_trailer' => ['type' => self::STR, 'default' => ''],
			'comment' => ['type' => self::STR, 'default' => ''],
			'tv_poster' => ['type' => self::UINT, 'default' => 0],
		];

		$structure->relations = [
			'Thread' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => 'thread_id'
			],
			'Ratings' => [
				'entity' => 'Snog\TV:Rating',
				'type' => self::TO_MANY,
				'conditions' => 'thread_id',
				'primary' => false
			],
		];

		return $structure;
	}
}