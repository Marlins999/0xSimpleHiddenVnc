<?php

namespace Snog\TV\Cron;

use XF\InputFilterer;
use XF\Util\File;

class ReplaceAll
{
	public static function Process()
	{
		$options = \XF::options();

		if ($options->TvThreads_replace_all)
		{
			$app = \XF::app();
			$threadsComplete = false;
			$episodesComplete = false;
			$forumsComplete = false;
			$seasonsComplete = false;

			// TV THREADS

			/** @var \Snog\TV\Entity\TV[]|\XF\Mvc\Entity\AbstractCollection $threads */
			$threads = $app->finder('Snog\TV:TV')
				->where('tv_id', '>', 0)
				->where('tv_poster', 0)
				->where('tv_season', 0)
				->where('tv_episode', 0)
				->order('thread_id', 'DESC')
				->fetch(3);

			$threadCount = $threads->count();
			if ($threadCount > 0)
			{
				foreach ($threads as $thread)
				{
					$tmdbApi = new \Snog\TV\Util\TmdbApi();
					$showInfo = $tmdbApi->getShow($thread->tv_id);

					if ($tmdbApi->getErrors())
					{
						$thread->tv_poster = 1;
						$thread->save(false);
						continue;
					}

					if (!is_null($showInfo['poster_path']))
					{
						if ($showInfo['poster_path'] > '')
						{
							$tempDir = FILE::getTempDir();
							$tempPath = $tempDir . $showInfo['poster_path'];
							$posterName = '/' . $thread->thread_id . '-' . str_ireplace('/', '', $showInfo['poster_path']);

							$path = 'data://tv/SmallPosters' . $posterName;
							$smallPosterSuccess = self::getTvImage($showInfo['poster_path'], 'w92', $path, $tempPath);

							$path = 'data://tv/LargePosters' . $posterName;
							$largePosterSuccess = self::getTvImage($showInfo['poster_path'], 'w185', $path, $tempPath);

							if ($smallPosterSuccess && $largePosterSuccess)
							{
								// REMOVE OLD IMAGES WITHOUT THREAD ID
								$path = 'data://tv/SmallPosters' . $thread->tv_image;
								File::deleteFromAbstractedPath($path);
								$path = 'data://tv/LargePosters' . $thread->tv_image;
								File::deleteFromAbstractedPath($path);

								if ($thread->tv_image !== $showInfo['poster_path'])
								{
									// REMOVE OLD IMAGES WITH THREAD ID
									$posterName = '/' . $thread->thread_id . '-' . str_ireplace('/', '', $thread->tv_image);

									$path = 'data://tv/LargePosters' . $posterName;
									File::deleteFromAbstractedPath($path);
									$path = 'data://tv/LargePosters' . $posterName;
									File::deleteFromAbstractedPath($path);
								}

								$thread->tv_image = $showInfo['poster_path'];
								$thread->tv_poster = 1;
								$thread->save();

								$threadXf = $app->finder('XF:Thread')
									->where('thread_id', $thread->thread_id)
									->fetchOne();

								if ($threadXf)
								{
									/** @var \Snog\TV\XF\Entity\Post $firstPost */
									$firstPost = $app->finder('XF:Post')
										->where('post_id', $threadXf->first_post_id)
										->fetchOne();

									if ($firstPost)
									{
										// CHECK TO BE SURE FIRST POST CONTAINS SHOW INFO AND REPLACE IF IT DOES
										$checkMessage = "[B]" . \XF::phrase('title') . ":[/B] " . $thread->tv_title;

										if (stristr($firstPost->message, $checkMessage))
										{
											$message = "[img]" . self::getTVImageUrl(($posterName ?: '/no-poster.jpg')) . "[/img]" . "\r\n\r\n";
											$message .= "[B]" . \XF::phrase('title') . ":[/B] " . $thread->tv_title . "\r\n\r\n";
											if ($thread->tv_genres) $message .= "[B]" . \XF::phrase('snog_tv_genre') . ":[/B] " . $thread->tv_genres . "\r\n\r\n";
											if ($thread->tv_release) $message .= "[B]" . \XF::phrase('snog_tv_first_aired') . ":[/B] " . $thread->tv_release . "\r\n\r\n";
											if ($thread->tv_director) $message .= "[B]" . \XF::phrase('snog_tv_creator') . ":[/B] " . $thread->tv_director . "\r\n\r\n";
											if ($thread->tv_cast) $message .= "[B]" . \XF::phrase('snog_tv_cast') . ":[/B] " . $thread->tv_cast . "\r\n\r\n";
											if ($thread->tv_plot) $message .= "[B]" . \XF::phrase('snog_tv_overview') . ":[/B] " . $thread->tv_plot . "\r\n\r\n";
											if ($thread->tv_trailer) $message .= "[MEDIA=youtube]" . $thread->tv_trailer . "[/MEDIA]" . "\r\n\r\n";
											if ($thread->comment && !$options->TvThreads_force_comments) $message .= $thread->comment;

											$firstPost->message = $message;
											$firstPost->save(false);
										}
									}
								}
							}
						}
						else
						{
							// NO TV POSTER AVAILABLE
							$thread->tv_poster = 1;
							$thread->save(false);
						}
					}
					else
					{
						// NO TV POSTER AVAILABLE
						$thread->tv_poster = 1;
						$thread->save(false);
					}
				}
			}
			else
			{
				$threadsComplete = true;

				// EPISODE POSTS

				/** @var \Snog\TV\Entity\TVPost[]|\XF\Mvc\Entity\AbstractCollection $episodePosts */
				$episodePosts = $app->finder('Snog\TV:TVPost')
					->where('tv_id', '>', 0)
					->where('tv_poster', 0)
					->where('tv_season', '>', 0)
					->where('tv_episode', '>', 0)
					->order('post_id', 'DESC')
					->fetch(3);

				$postCount = $episodePosts->count();
				if ($postCount > 0)
				{
					foreach ($episodePosts as $episodePost)
					{
						$tmdbApi = new \Snog\TV\Util\TmdbApi();
						$tvepisode = $tmdbApi->getEpisode($episodePost->tv_id, $episodePost->tv_season, $episodePost->tv_episode);

						if ($tmdbApi->getErrors())
						{
							// THIS IS A CRON - IGNORE ERROR AND MOVE TO NEXT ENTRY TO BE PROCESSED
							$episodePost->tv_poster = 1;
							$episodePost->save(false);
							continue;
						}

						if (!is_null($tvepisode['still_path']))
						{
							// GET EPISODE IMAGE
							if ($tvepisode['still_path'] > '')
							{
								$imageName = '/' . $episodePost->post_id . '-' . str_ireplace('/', '', $tvepisode['still_path']);

								$path = 'data://tv/EpisodePosters' . $imageName;
								$tempDir = FILE::getTempDir();
								$tempPath = $tempDir . $tvepisode['still_path'];

								$episodePosterSuccess = self::getTvImage($tvepisode['still_path'], 'w300', $path, $tempPath);

								if ($episodePosterSuccess)
								{
									// REMOVE OLD IMAGE WITHOUT POST ID
									$path = 'data://tv/EpisodePosters' . $episodePost->tv_image;
									File::deleteFromAbstractedPath($path);

									if ($episodePost->tv_image !== $tvepisode['still_path'])
									{
										// REMOVE OLD IMAGE WITH POST ID
										$oldImageName = '/' . $episodePost->post_id . '-' . str_ireplace('/', '', $episodePost->tv_image);
										$path = 'data://tv/EpisodePosters' . $oldImageName;
										File::deleteFromAbstractedPath($path);
									}

									$episodePost->tv_image = $tvepisode['still_path'];
									$episodePost->tv_poster = 1;
									$episodePost->save(false);

									$postXf = $episodePost->Post;

									if ($postXf)
									{
										$postThread = $postXf->Thread;

										$episodeInfo = "[B]" . $postThread->title . "[/B]" . "\r\n";
										$episodeInfo .= '[IMG]' . self::getEpisodeImageUrl(($imageName ?: '/no-poster.jpg')) . '[/IMG]' . "\r\n";
										$episodeInfo .= "[B]" . $episodePost->tv_title . "[/B]" . "\r\n";
										$episodeInfo .= "[B]" . \XF::phrase('snog_tv_season') . ":[/B] " . $episodePost->tv_season . "\r\n";
										$episodeInfo .= "[B]" . \XF::phrase('snog_tv_episode') . ":[/B] " . $episodePost->tv_episode . "\r\n";
										$episodeInfo .= "[B]" . \XF::phrase('snog_tv_air_date') . ":[/B] " . $episodePost->tv_aired . "\r\n\r\n";
										if (!empty($guest)) $episodeInfo .= "[B]" . \XF::phrase('snog_tv_guest_stars') . ":[/B] " . $episodePost->tv_guest . "\r\n\r\n";
										$episodeInfo .= $episodePost->tv_plot . "\r\n";
										$message = $episodeInfo . "\r\n\r\n";
										if (!empty($episodePost->message)) $message .= $episodePost->message;

										$postXf->message = $message;
										$postXf->save(false);
									}
								}
							}
							else
							{
								// NO EPISODE IMAGE AVAILABLE
								$episodePost->tv_poster = 1;
								$episodePost->save(false);
							}
						}
						else
						{
							// NO EPISODE IMAGE AVAILABLE
							$episodePost->tv_poster = 1;
							$episodePost->save(false);
						}
					}
				}
				else
				{
					$episodesComplete = true;

					// TV FORUMS

					/** @var \Snog\TV\Entity\TVForum[]|\XF\Mvc\Entity\AbstractCollection $tvForums */
					$tvForums = $app->finder('Snog\TV:TVForum')
						->where('tv_poster', 0)
						->where('tv_id', '>', 0)
						->fetch(3);

					$forumCount = $tvForums->count();

					if ($forumCount > 0)
					{
						foreach ($tvForums as $tvForum)
						{
							if ($tvForum->tv_season > 0)
							{
								$tmdbid = $tvForum->tv_season > 0 ? $tvForum->tv_parent_id : $tvForum->tv_id;
							}
							else
							{
								$tmdbid = $tvForum->tv_id;
							}

							$tmdbApi = new \Snog\TV\Util\TmdbApi();
							$showInfo = $tmdbApi->getShow($tmdbid, ['credits', 'videos']);

							if ($tmdbApi->getErrors())
							{
								// THIS IS A CRON - IGNORE ERROR AND MOVE TO NEXT ENTRY TO BE PROCESSED
								$tvForum->tv_poster = 1;
								$tvForum->save(false);
								continue;
							}

							if (!is_null($showInfo['poster_path']))
							{
								if ($showInfo['poster_path'] > '')
								{
									$tempDir = FILE::getTempDir();
									$tempPath = $tempDir . $showInfo['poster_path'];

									if ($tvForum->tv_season > 0)
									{
										$path = 'data://tv/SeasonPosters' . $showInfo['poster_path'];
									}
									else
									{
										$path = 'data://tv/ForumPosters' . $showInfo['poster_path'];
									}

									$posterSuccess = self::getTvImage($showInfo['poster_path'], 'w92', $path, $tempPath);

									if ($posterSuccess)
									{
										if ($tvForum->tv_image !== $showInfo['poster_path'])
										{
											$deletePath = 'data://tv/SeaspmPosters' . $tvForum->tv_image;
											File::deleteFromAbstractedPath($deletePath);

											$deletePath = 'data://tv/ForumPosters' . $tvForum->tv_image;
											File::deleteFromAbstractedPath($deletePath);
										}

										$tvForum->tv_image = $showInfo['poster_path'];
										$tvForum->tv_poster = 1;
										$tvForum->save(false);
									}
								}
								else
								{
									$tvForum->tv_poster = 1;
									$tvForum->save(false);
								}
							}
							else
							{
								$tvForum->tv_poster = 1;
								$tvForum->save(false);
							}
						}
					}
					else
					{
						$forumsComplete = true;

						// SEASON THREADS

						/** @var \Snog\TV\Entity\TV[]|\XF\Mvc\Entity\AbstractCollection $seasonThreads */
						$seasonThreads = $app->finder('Snog\TV:TV')
							->where('tv_id', '>', 0)
							->where('tv_poster', 0)
							->where('tv_season', '>', 0)
							->where('tv_episode', '>', 0)
							->fetch(6);

						$threadCount = $seasonThreads->count();
						if ($threadCount > 0)
						{
							foreach ($seasonThreads as $seasonThread)
							{
								// IMAGE NOT NEEDED FOR THESE THREADS
								$seasonThread->tv_image = '';
								$seasonThread->tv_poster = 1;
								$seasonThread->save(false);
							}
						}
						else
						{
							$seasonsComplete = true;
						}
					}
				}
			}

			// ALL DONE - RESET OPTION VALUE AND CLEAR FLAGS
			if ($threadsComplete && $episodesComplete && $forumsComplete && $seasonsComplete)
			{
				/** @var \XF\Repository\Option $optionRepo */
				$optionRepo = $app->repository('XF:Option');
				$optionRepo->updateOption('TvThreads_replace_all', 0);

				$db = \XF::db();
				$update = ['tv_poster' => 0];
				$db->update('xf_snog_tv_thread', $update, 'tv_poster = 1');
				$db->update('xf_snog_tv_post', $update, 'tv_poster = 1');
				$db->update('xf_snog_tv_forum', $update, 'tv_poster = 1');
			}
		}
	}

	protected static function getTvImage($srcPath, $size, $localPath, $tempPath)
	{
		$tmdbApi = new \Snog\TV\Util\TmdbApi();
		$poster = $tmdbApi->getPoster($srcPath, $size);

		if (file_exists($tempPath))
		{
			unlink($tempPath);
		}
		file_put_contents($tempPath, $poster);
		File::copyFileToAbstractedPath($tempPath, $localPath);
		unlink($tempPath);

		return true;
	}

	public static function getTVImageUrl($posterpath, $canonical = true)
	{
		$app = \XF::app();
		return $app->applyExternalDataUrl("tv/LargePosters{$posterpath}", $canonical);
	}

	public static function getEpisodeImageUrl($imagepath, $canonical = true)
	{
		$app = \XF::app();
		return $app->applyExternalDataUrl("tv/EpisodePosters{$imagepath}", $canonical);
	}
}