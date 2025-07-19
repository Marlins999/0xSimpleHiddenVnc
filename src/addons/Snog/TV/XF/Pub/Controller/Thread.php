<?php

namespace Snog\TV\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Util\File;

class Thread extends XFCP_Thread
{
	public function actionIndex(ParameterBag $params)
	{
		$view = parent::actionIndex($params);

		if ($view instanceof \XF\Mvc\Reply\View)
		{
			$canAddTVInfo = false;
			$visitor = \XF::visitor();
			if ($visitor->hasPermission('tvthreads_interface', 'add_info'))
			{
				$canAddTVInfo = true;
			}
			$view->setParam('canAddTVInfo', $canAddTVInfo);
		}

		return $view;
	}

	public function actionAddReply(ParameterBag $params)
	{
		$this->assertPostOnly();
		$visitor = \XF::visitor();

		// HAVE TO QUERY FOR VIEWABLE TO GET THE NODE ID

		/** @var \Snog\TV\XF\Entity\Thread $thread */
		$thread = $this->assertViewableThread($params->thread_id, ['Watch|' . $visitor->user_id]);

		$node_id = $thread->node_id;
		if (!in_array($node_id, $this->options()->TvThreads_forum))
		{
			return parent::actionAddReply($params);
		}

		if (!$thread->canReply($error))
		{
			return $this->noPermission($error);
		}

		if ($this->filter('no_captcha', 'bool')) // JS is disabled so user hasn't seen Captcha.
		{
			$this->request->set('requires_captcha', true);
			return $this->rerouteController(__CLASS__, 'reply', $params);
		}
		else if (!$this->captchaIsValid())
		{
			return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
		}

		$showId = 0;
		$seasonId = $this->filter('season', 'uint');
		$episodeId = $this->filter('episode', 'uint');

		if (!$seasonId && !$episodeId)
		{
			return parent::actionAddReply($params);
		}

		if (isset($thread->TV->tv_id))
		{
			$showId = $thread->TV->tv_id;
		}

		if (($seasonId && !$episodeId) || (!$seasonId && $episodeId))
		{
			return $this->error(\XF::phrase('snog_tv_error_episode_link'));
		}

		/** @var \XF\ControllerPlugin\Editor $editorPlugin */
		$editorPlugin = $this->plugin('XF:Editor');
		$comment = $editorPlugin->fromInput('message');

		$message = '';
		$guest = '';
		$permStars = '';
		$originalImage = '';
		$episode = [];

		if ($seasonId && $episodeId && $showId)
		{
			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$episode = $tmdbApi->getEpisode($showId, $seasonId, $episodeId, ['credits']);

			if ($errors = $tmdbApi->getErrors())
			{
				return $this->error($errors);
			}

			if (!is_null($episode['still_path']))
			{
				// GET EPISODE IMAGE
				if ($episode['still_path'] > '')
				{
					$src = "http://image.tmdb.org/t/p/w300" . $episode['still_path'];
					$path = 'data://tv/EpisodePosters' . $episode['still_path'];
					$tempDir = FILE::getTempDir();
					$tempPath = $tempDir . $episode['still_path'];

					try
					{
						$response = \XF::app()->http()->client()->get($src);
					}
					catch (\GuzzleHttp\Exception\RequestException $e)
					{
						$error = $e->getMessage();
						return $this->error($error);
					}

					if (file_exists($tempPath)) unlink($tempPath);
					file_put_contents($tempPath, $response->getBody());

					File::copyFileToAbstractedPath($tempPath, $path);
					unlink($tempPath);
				}
			}
			else
			{
				$episode['still_path'] = '';
			}

			$episodeInfo = "[B]" . $thread['title'] . "[/B]" . "\r\n";
			$originalImage = '[IMG]' . $this->getEpisodeImageUrl(($episode['still_path'] ?: '/no-poster.jpg')) . '[/IMG]' . "\r\n";
			$episodeInfo .= $originalImage;

			if (!empty($episode['credits']['guest_stars']))
			{
				foreach ($episode['credits']['cast'] as $cast)
				{
					if ($permStars) $permStars .= ', ';
					$permStars .= $cast['name'];
				}

				$checkstars = explode(',', $permStars);
				foreach ($episode['credits']['guest_stars'] as $guestStar)
				{
					if (!in_array($guestStar['name'], $checkstars))
					{
						if ($guest) $guest .= ', ';
						$guest .= $guestStar['name'];
					}
				}
			}

			$episodeInfo .= "[B]" . $episode['name'] . "[/B]" . "\r\n";
			$episodeInfo .= "[B]" . \XF::phrase('snog_tv_season') . ":[/B] " . $episode['season_number'] . "\r\n";
			$episodeInfo .= "[B]" . \XF::phrase('snog_tv_episode') . ":[/B] " . $episode['episode_number'] . "\r\n";
			$episodeInfo .= "[B]" . \XF::phrase('snog_tv_air_date') . ":[/B] " . $episode['air_date'] . "\r\n\r\n";
			if (!empty($guest)) $episodeInfo .= "[B]" . \XF::phrase('snog_tv_guest_stars') . ":[/B] " . $guest . "\r\n\r\n";
			$episodeInfo .= $episode['overview'] . "\r\n";
			$message = $episodeInfo . "\r\n\r\n";
		}

		// REPLIER IS DONE HERE BECAUSE TMDB EPISODE INFO IS REQUIRED FOR MESSAGE CONSTRUCTION AND PROPER SAVE OF EPISODE INFO

		/** @var \XF\Service\Thread\Replier $replier */
		$replier = $this->service('XF:Thread\Replier', $thread);

		$message .= $comment;

		$replier->setMessage($message);

		if ($thread->Forum->canUploadAndManageAttachments())
		{
			$replier->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		$replier->checkForSpam();

		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}

		$this->assertNotFlooding('post');

		/** @var \Snog\TV\XF\Entity\Post $post */
		$post = $replier->save();

		$this->finalizeThreadReply($replier);

		if (!empty($episode))
		{
			/** @var \Snog\TV\Entity\TVPost $newEpisode */
			$newEpisode = $this->em()->create('Snog\TV:TVPost');
			$newEpisode->post_id = $post->post_id;
			$newEpisode->tv_id = $thread->TV->tv_id;
			$newEpisode->tv_season = $episode['season_number'];
			$newEpisode->tv_episode = $episode['episode_number'];
			$newEpisode->tv_aired = $episode['air_date'];
			$newEpisode->tv_image = $episode['still_path'];
			$newEpisode->tv_title = $episode['name'];
			$newEpisode->tv_plot = $episode['overview'];
			$newEpisode->tv_cast = $permStars;
			$newEpisode->tv_guest = $guest;
			$newEpisode->message = $comment;
			$newEpisode->save(true, true);
		}

		// MOVE EPISODE IMAGE TO POST ID + IMAGE NAME
		if ($episode['still_path'] && $originalImage)
		{
			// NEW IMAGE NAME THAT INLCUDES POST ID
			$imageName = $post->post_id . '-' . str_ireplace('/', '', $episode['still_path']);

			// MOVE IMAGE TO NEW NAME
			$oldPath = 'data://tv/EpisodePosters' . $episode['still_path'];
			$newPath = 'data://tv/EpisodePosters/' . $imageName;

			try
			{
				\XF::app()->fs()->move($oldPath, $newPath);
			}
			catch (\League\Flysystem\FileNotFoundException $e)
			{
			}

			// CHANGE IMAGE IN POST TO NEWLY NAMED IMAGE (INCLUDES POST ID)

			$episodePost = $post->TVPost;
			$newImage = '[IMG]' . $episodePost->getEpisodeImageUrl() . '[/IMG]' . "\r\n";
			$post->message = str_ireplace($originalImage, $newImage, $post->message);
			$post->save();
		}

		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $post->canView())
		{
			// request was from quick reply
			$lastDate = $this->filter('last_date', 'uint');
			//return $this->getNewPostsReply($thread, $lastDate);
			if (\XF::$versionId < 2020010)
			{
				/** @noinspection PhpUndefinedMethodInspection */
				return $this->getNewPostsReply($thread, $lastDate);
			}

			return $this->getNewPostsSinceDateReply($thread, $lastDate);
		}

		$this->getThreadRepo()->markThreadReadByVisitor($thread);
		$confirmation = \XF::phrase('your_message_has_been_posted');

		if ($post->canView())
		{
			return $this->redirect($this->buildLink('posts', $post), $confirmation);
		}

		return $this->redirect($this->buildLink('threads', $thread, ['pending_approval' => 1]), $confirmation);
	}

	public function actionPreview(ParameterBag $params)
	{
		// HAVE TO QUERY FOR VIEWABLE TO GET THE NODE ID

		/** @var \Snog\TV\XF\Entity\Thread $thread */
		$thread = $this->assertViewableThread($params->thread_id, ['FirstPost']);

		if ((!in_array($thread->node_id, $this->options()->TvThreads_forum) && !isset($thread->TV->tv_plot)) || !isset($thread->TV->tv_plot))
		{
			return parent::actionPreview($params);
		}

		$firstPost['user'] = $thread->user_id;
		$firstPost['message'] = $thread->TV->tv_plot;
		$viewParams = ['thread' => $thread, 'firstPost' => $firstPost];
		return $this->view('XF:Thread\Preview', 'thread_preview', $viewParams);
	}

	public function getEpisodeImageUrl($posterpath, $canonical = true)
	{
		return $this->app->applyExternalDataUrl("tv/EpisodePosters{$posterpath}", $canonical);
	}

	protected function getThreadViewExtraWith()
	{
		$extraWith = parent::getThreadViewExtraWith();
		$extraWith[] = 'TV';

		return $extraWith;
	}
}
