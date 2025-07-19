<?php

namespace Snog\TV\Cron;

use XF\InputFilterer;
use XF\Util\File;

class EpisodeUpdate
{
	public static function Process()
	{
		$app = \XF::app();

		/** @var \Snog\TV\Entity\TVPost[]|\XF\Mvc\Entity\AbstractCollection $episodes */
		$episodes = $app->finder('Snog\TV:TVPost')
			->where('tv_id', '>', 0)
			->where('tv_checked', 0)
			->whereOr(
				['tv_image', '=', ''],
				['tv_plot', '=', '']
			)
			->fetch(3);

		foreach ($episodes as $episode)
		{
			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$tvepisode = $tmdbApi->getEpisode($episode->tv_id, $episode->tv_season, $episode->tv_episode, ['credits', 'videos']);

			if ($tmdbApi->getErrors())
			{
				continue;
			}

			// IMAGE MISSING - GET IT IF AVAILABLE
			if ($episode->tv_image == '' && !is_null($tvepisode['still_path']))
			{
				// GET EPISODE IMAGE
				if ($tvepisode['still_path'] > '')
				{
					$image = str_ireplace('/', '', $tvepisode['still_path']);
					$path = 'data://tv/EpisodePosters' . '/' . $episode->post_id . '-' . $image;
					$tempDir = FILE::getTempDir();
					$tempPath = $tempDir . $tvepisode['still_path'];

					$poster = $tmdbApi->getPoster($tvepisode['still_path'], 'w300');

					if (file_exists($tempPath))
					{
						continue;
					}
					file_put_contents($tempPath, $poster);

					File::copyFileToAbstractedPath($tempPath, $path);
					unlink($tempPath);

					$episode->tv_image = $tvepisode['still_path'];
				}
			}

			// PLOT MISSING - GET IT IF AVAILABLE
			if ($episode->tv_plot == '' && !is_null($tvepisode['overview']))
			{
				$episode->tv_plot = $tvepisode['overview'];
			}

			// CAST MISSING - GET IT IF AVAILABLE
			// THIS IS NOT A QUERY CONDITION BECAUSE THE CAST SHOULD NOT BE DIFFERENT FROM THE MAIN TV SHOW CAST
			// BUT IF IT EXISTS WE'LL ADD IT TO THIS EPISODE DATA
			$permstars = '';
			if ($episode->tv_cast == '' && !empty($tvepisode['credits']['cast']))
			{
				foreach ($tvepisode['credits']['cast'] as $cast)
				{
					if ($permstars) $permstars .= ', ';
					$permstars .= $cast['name'];
				}

				$episode->tv_cast = $permstars;
			}

			// GUEST STAR LIST MISSING - GET IT IF AVAILABLE
			// THIS IS NOT A QUERY CONDITION BECAUSE GUEST STARS ARE NOT ALWAYS LISTED FOR EPISODES
			// BUT IF IT EXISTS WE'LL ADD IT TO THIS EPISODE DATA
			if ($episode->tv_guest == '' && !empty($tvepisode['credits']['guest_stars']))
			{
				if (!$permstars)
				{
					$permstars = '';

					foreach ($tvepisode['credits']['cast'] as $cast)
					{
						if ($permstars) $permstars .= ', ';
						$permstars .= $cast['name'];
					}
				}

				$checkstars = explode(',', $permstars);

				foreach ($tvepisode['credits']['guest_stars'] as $guestStar)
				{
					if (!in_array($guestStar['name'], $checkstars))
					{
						if ($guest) $guest .= ', ';
						$guest .= $guestStar['name'];
					}
				}

				$episode->tv_guest = $guest;
			}

			$episode->tv_checked = 1;
			$episode->save(false);
		}

		// RESET ALL CHECKED - RESTARTS CHECK CYCLE FROM THE BEGINNING
		if (!$episodes->toArray())
		{
			$db = \XF::db();
			$db->query('UPDATE xf_snog_tv_post SET tv_checked = 0 WHERE tv_checked = 1');
		}
	}
}