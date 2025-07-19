<?php

namespace Snog\TV\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 *
 * @property int post_id
 * @property int tv_id
 * @property int tv_season
 * @property int tv_episode
 * @property string tv_aired
 * @property string tv_image
 * @property string tv_title
 * @property string tv_plot
 * @property string tv_cast
 * @property string tv_guest
 * @property string message
 * @property int tv_checked
 * @property int tv_poster
 *
 * RELATIONS
 * @property \XF\Entity\Post Post
 */
class TVPost extends Entity
{
	public function getEpisodeImageUrl($noposter = null, $canonical = true)
	{
		$app = \XF::app();
		if (!$noposter)
		{
			$image = str_ireplace('/', '', $this->tv_image);
			return $app->applyExternalDataUrl("tv/EpisodePosters/{$this->post_id}-{$image}", $canonical);
		}
		else
		{
			return $app->applyExternalDataUrl("tv/EpisodePosters/no-poster.png", $canonical);
		}
	}

	public function getEpisodeImageCDN($noposter = null)
	{
		if (!$noposter)
		{
			$image = str_ireplace('/', '', $this->tv_image);
			return \XF::options()->TvThreads_cdn_path . '/tv/EpisodePosters/' . $this->post_id . '-' . $image;
		}
		else
		{
			return \XF::options()->TvThreads_cdn_path . '/tv/EpisodePosters/no-poster.png';
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_snog_tv_post';
		$structure->shortName = 'Snog\TV:TVPost';
		$structure->contentType = 'tvpost';
		$structure->primaryKey = 'post_id';
		$structure->columns = [
			'post_id' => ['type' => self::UINT, 'default' => 0],
			'tv_id' => ['type' => self::UINT, 'default' => ''],
			'tv_season' => ['type' => self::UINT, 'default' => 0],
			'tv_episode' => ['type' => self::UINT, 'default' => 0],
			'tv_aired' => ['type' => self::STR, 'default' => ''],
			'tv_image' => ['type' => self::STR, 'default' => ''],
			'tv_title' => ['type' => self::STR, 'default' => ''],
			'tv_plot' => ['type' => self::STR, 'default' => ''],
			'tv_cast' => ['type' => self::STR, 'default' => ''],
			'tv_guest' => ['type' => self::STR, 'default' => ''],
			'message' => ['type' => self::STR, 'default' => ''],
			'tv_checked' => ['type' => self::UINT, 'default' => 0],
			'tv_poster' => ['type' => self::UINT, 'default' => 0],
		];

		$structure->relations = [
			'Post' => [
				'entity' => 'XF:TVPost',
				'type' => self::TO_ONE,
				'conditions' => 'post_id'
			],
		];

		return $structure;
	}
}