<?php

namespace Snog\TV\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Util\File;

class TV extends AbstractController
{
	public function actionRate(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		$thread = $this->assertViewableThread($params->thread_id);
		$show = $thread->TV;
		if (!$show)
		{
			return $this->notFound();
		}

		$userRating = null;
		if ($show->hasRated())
		{
			$userRating = $show->Ratings[$visitor->user_id];
		}

		if ($this->isPost())
		{
			$thisRating = $this->filter('rating', 'uint');

			if (!isset($userRating->user_id))
			{
				/** @var \Snog\TV\Entity\Rating $userRating */
				$userRating = $this->em()->create('Snog\TV:Rating');
				$userRating->thread_id = $show->thread_id;
				$userRating->user_id = $visitor->user_id;
				$userRating->rating = $thisRating;
			}
			else
			{
				$userRating->rating = $thisRating;
			}

			$userRating->save();

			return $this->redirect($this->buildLink('threads', $thread));
		}

		$currentRating = 0;
		if (isset($userRating->rating))
		{
			$currentRating = $userRating->rating;
		}

		$viewParams = ['tvshow' => $show, 'currentRating' => $currentRating];
		return $this->view('Snog:TV\TV', 'snog_tv_rate', $viewParams);
	}

	public function actionRateShow(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		$forum = $this->assertViewableForum($params->node_id);
		$show = $forum->TVForum;
		if (!$show)
		{
			return $this->notFound();
		}

		$userRating = null;
		if ($show->hasRated())
		{
			$userRating = $show->Ratings[$visitor->user_id];
		}

		if ($this->isPost())
		{
			$thisRating = $this->filter('rating', 'uint');

			if (!$userRating)
			{
				/** @var \Snog\TV\Entity\RatingNode $userRating */
				$userRating = $this->em()->create('Snog\TV:RatingNode');
				$userRating->node_id = $show->node_id;
				$userRating->user_id = $visitor->user_id;
				$userRating->rating = $thisRating;
			}
			else
			{
				$userRating->rating = $thisRating;
			}

			$userRating->save();

			return $this->redirect($this->buildLink('forums', $forum));
		}

		$currentRating = 0;
		if (isset($userRating->rating))
		{
			$currentRating = $userRating->rating;
		}
		$viewParams = ['tvshow' => $show, 'currentRating' => $currentRating];
		return $this->view('Snog:TV\TV', 'snog_tv_rate_show', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		$post = $this->assertViewablePost($thread->first_post_id, ['Thread.Prefix']);

		/** @var \XF\Entity\Post $error */
		if (!$post->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$forum = $post->Thread->Forum;

		/** @var \Snog\TV\Entity\TV $tvShow */
		$tvShow = $this->finder('Snog\TV:TV')->where('thread_id', $params->thread_id)->fetchOne();
		$porta = $this->filter('porta', 'bool');

		if ($this->isPost())
		{
			$editor = $this->service('XF:Post\Editor', $post);
			$threadChanges = [];

			$input = $this->filter([
				'tv_title' => 'str',
				'tv_genres' => 'str',
				'tv_director' => 'str',
				'tv_cast' => 'str',
				'tv_release' => 'str',
				'tv_plot' => 'str'
			]);

			$releaseExploded = explode('-', $input['tv_release']);
			if (!isset($releaseExploded[0]) || strlen($releaseExploded[0]) !== 4)
			{
				return $this->error(\XF::phrase('snog_tv_error_aired_date'));
			}

			$comment = $this->plugin('XF:Editor')->fromInput('message');
			$url = $this->filter('tv_trailer', 'str');
			$trailer = '';

			if (stristr($url, 'youtube'))
			{
				$mediaRepo = $this->repository('XF:BbCodeMediaSite');
				$sites = $mediaRepo->findActiveMediaSites()->fetch();
				$match = $mediaRepo->urlMatchesMediaSiteList($url, $sites);
				if ($match) $trailer = $match['media_id'];
			}
			else
			{
				$trailer = $url;
			}

			$message = "[img]" . $this->getImageUrl(($tvShow->tv_image ?: '/no-poster.jpg'), $tvShow) . "[/img]" . "\r\n\r\n";
			$message .= "[B]" . \XF::phrase('title') . ":[/B] " . $input['tv_title'] . "\r\n\r\n";
			if ($input['tv_genres']) $message .= "[B]" . \XF::phrase('snog_tv_genre') . ":[/B] " . $input['tv_genres'] . "\r\n\r\n";
			if ($input['tv_release']) $message .= "[B]" . \XF::phrase('snog_tv_first_aired') . ":[/B] " . $input['tv_release'] . "\r\n\r\n";
			if ($input['tv_director']) $message .= "[B]" . \XF::phrase('snog_tv_creator') . ":[/B] " . $input['tv_director'] . "\r\n\r\n";
			if ($input['tv_cast']) $message .= "[B]" . \XF::phrase('snog_tv_cast') . ":[/B] " . $input['tv_cast'] . "\r\n\r\n";
			if ($input['tv_plot']) $message .= "[B]" . \XF::phrase('snog_tv_overview') . ":[/B] " . $input['tv_plot'] . "\r\n\r\n";
			if ($trailer) $message .= "[MEDIA=youtube]" . $trailer . "[/MEDIA]" . "\r\n\r\n";
			if ($comment && !$this->options()->TvThreads_force_comments) $message .= $comment;

			$editor->logEdit(false);
			$editor->setMessage($message);
			if ($forum->canUploadAndManageAttachments())
			{
				$editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
			}

			$editor->save();

			$tvShow->tv_title = $input['tv_title'];
			$tvShow->tv_plot = $input['tv_plot'];
			$tvShow->tv_genres = $input['tv_genres'];
			$tvShow->tv_director = $input['tv_director'];
			$tvShow->tv_cast = $input['tv_cast'];
			$tvShow->tv_trailer = $trailer;
			$tvShow->tv_release = $input['tv_release'];
			if (!$this->options()->TvThreads_force_comments) $tvShow->comment = $comment;
			if ($this->options()->TvThreads_force_comments) $tvShow->comment = '';
			$tvShow->save(false, false);

			if ($input['tv_title'] !== $thread->title)
			{
				$thread->title = $input['tv_title'];
				$thread->save();
				$threadChanges = ['title' => 1];
			}

			if ($this->filter('_xfWithData', 'bool'))
			{
				$viewParams = ['post' => $post, 'thread' => $thread];

				if (!$porta)
				{
					$reply = $this->view('XF:Post\EditNewPost', 'post_edit_new_post', $viewParams);
				}
				else
				{
					$reply = $this->view('XF:Post\EditNewPost', 'snog_tv_XenPorta_show', $viewParams);
				}

				$reply->setJsonParams([
					'message' => \XF::phrase('your_changes_have_been_saved'),
					'threadChanges' => ($porta ? null : $threadChanges)
				]);
				return $reply;
			}

			return $this->redirect($this->buildLink('posts', $post));
		}

		if ($forum->canUploadAndManageAttachments())
		{
			$attachmentRepo = $this->repository('XF:Attachment');
			$attachmentData = $attachmentRepo->getEditorData('post', $post);
		}
		else
		{
			$attachmentData = null;
		}

		$prefix = $thread->Prefix;
		$prefixes = $forum->getUsablePrefixes($prefix);

		$viewParams = [
			'tvshow' => $tvShow,
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
			'porta' => $porta,
			'prefixes' => $prefixes,
			'attachmentData' => $attachmentData,
			'quickEdit' => $this->filter('_xfWithData', 'bool')
		];
		return $this->view('XF:Post\Edit', 'snog_tv_edit_show', $viewParams);
	}

	public function actionEditepisode(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		/** @var \XF\Entity\Post $error */
		if (!$post->canEdit($error))
		{
			return $this->noPermission($error);
		}
		$forum = $post->Thread->Forum;
		$thread = $post->Thread;
		$porta = $this->filter('porta', 'bool');
		$tvParent = '';

		if ($post->post_id == $thread->first_post_id)
		{
			$tvParent = $this->finder('Snog\TV:TVForum')->where('node_id', $forum->Node->parent_node_id)->fetchOne();
		}

		/** @var \Snog\TV\Entity\TVPost $tvShow */
		$tvShow = $this->finder('Snog\TV:TVPost')->where('post_id', $params->post_id)->fetchOne();

		if ($this->isPost())
		{
			$editor = $this->service('XF:Post\Editor', $post);

			$input = $this->filter([
				'tv_title' => 'str',
				'tv_season' => 'uint',
				'tv_episode' => 'uint',
				'tv_aired' => 'str',
				'tv_guest' => 'str',
				'tv_plot' => 'str'
			]);

			$releaseExploded = explode('-', $input['tv_aired']);
			if (!isset($releaseExploded[0]) || strlen($releaseExploded[0]) !== 4)
			{
				return $this->error($this->error(\XF::phrase('snog_tv_error_aired_date')));
			}

			$comment = $this->plugin('XF:Editor')->fromInput('message');

			$message = "[img]" . $this->getEpisodeImageUrl(($tvShow->tv_image ?: '/no-poster.jpg'), $post) . "[/img]" . "\r\n";
			$message .= "[B]" . $input['tv_title'] . "[/B]" . "\r\n\r\n";
			$message .= "[B]" . \XF::phrase('snog_tv_season') . ":[/B] " . $input['tv_season'] . "\r\n";
			$message .= "[B]" . \XF::phrase('snog_tv_episode') . ":[/B] " . $input['tv_episode'] . "\r\n";
			if ($input['tv_aired']) $message .= "[B]" . \XF::phrase('snog_tv_air_date') . ":[/B] " . $input['tv_aired'] . "\r\n\r\n";
			if ($input['tv_guest']) $message .= "[B]" . \XF::phrase('snog_tv_guest_stars') . ":[/B] " . $input['tv_guest'] . "\r\n\r\n";
			if ($input['tv_plot']) $message .= $input['tv_plot'] . "\r\n\r\n";
			$message .= $comment;

			$editor->logEdit(false);
			$editor->setMessage($message);
			if ($forum->canUploadAndManageAttachments())
			{
				$editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
			}
			$editor->save();

			$tvShow->tv_title = $input['tv_title'];
			$tvShow->tv_season = $input['tv_season'];
			$tvShow->tv_episode = $input['tv_episode'];
			$tvShow->tv_aired = $input['tv_aired'];
			$tvShow->tv_guest = $input['tv_guest'];
			$tvShow->tv_plot = $input['tv_plot'];
			$tvShow->message = $comment;
			$tvShow->save(false, false);

			$testTitle = '';
			if (!$tvParent) $testTitle = $thread->title;

			if ($tvParent && !$this->options()->TvThreads_episode_exclude)
			{
				$testTitle .= $tvParent->tv_title . ': ';
				$testTitle .= 'S' . str_pad($input['tv_season'], 2, '0', STR_PAD_LEFT);
				$testTitle .= 'E' . str_pad($input['tv_episode'], 2, '0', STR_PAD_LEFT);
				$testTitle .= " " . $input['tv_title'];
			}

			if ($testTitle !== $thread->title)
			{
				$thread->title = $testTitle;
				$thread->save();
			}

			if ($this->filter('_xfWithData', 'bool'))
			{
				if ($porta)
				{
					$articlePost = $post;
					$viewParams = ['post' => $post, 'thread' => $thread, 'porta' => $porta, 'articlePost' => $articlePost];
					$reply = $this->view('XF:Post\EditNewPost', 'snog_tv_XenPorta_episode', $viewParams);
				}
				else
				{
					$viewParams = ['post' => $post, 'thread' => $thread, 'porta' => $porta];
					$reply = $this->view('XF:Post\EditNewPost', 'post_edit_new_post', $viewParams);
				}

				$reply->setJsonParams(['message' => \XF::phrase('your_changes_have_been_saved')]);
				return $reply;
			}

			return $this->redirect($this->buildLink('posts', $post));
		}

		if ($forum->canUploadAndManageAttachments())
		{
			$attachmentRepo = $this->repository('XF:Attachment');
			$attachmentData = $attachmentRepo->getEditorData('post', $post);
		}
		else
		{
			$attachmentData = null;
		}

		$viewParams = [
			'tvshow' => $tvShow,
			'post' => $post,
			'forum' => $forum,
			'porta' => $porta,
			'attachmentData' => $attachmentData,
			'quickEdit' => $this->filter('_xfWithData', 'bool')
		];
		return $this->view('XF:Post\Edit', 'snog_tv_edit_episode', $viewParams);
	}

	/**
	 * @param $threadId
	 * @param array $extraWith
	 * @return \Snog\TV\XF\Entity\Thread
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$extraWith[] = 'Forum';
		$extraWith[] = 'Forum.Node';
		$extraWith[] = 'Forum.Node.Permissions|' . $visitor->permission_combination_id;

		if ($visitor->user_id)
		{
			$extraWith[] = 'Read|' . $visitor->user_id;
			$extraWith[] = 'Forum.Read|' . $visitor->user_id;
		}

		/** @var \Snog\TV\XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $threadId, $extraWith);
		if (!$thread)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_thread_not_found')));
		}

		if (!$thread->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		/** @var \XF\ControllerPlugin\Node $nodePlugin */
		$nodePlugin = $this->plugin('XF:Node');
		$nodePlugin->applyNodeContext($thread->Forum->Node);
		$this->setContentKey('thread-' . $thread->thread_id);

		return $thread;
	}

	protected function assertViewablePost($postId, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$extraWith[] = 'Thread';
		$extraWith[] = 'Thread.Forum';
		$extraWith[] = 'Thread.Forum.Node';
		$extraWith[] = 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id;

		/** @var \Snog\TV\XF\Entity\Post $post */
		$post = $this->em()->find('XF:Post', $postId, $extraWith);

		if (!$post)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_post_not_found')));
		}

		/** @var \XF\Entity\Post $error */
		if (!$post->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		/** @var \XF\ControllerPlugin\Node $nodePlugin */
		$nodePlugin = $this->plugin('XF:Node');
		$nodePlugin->applyNodeContext($post->Thread->Forum->Node);

		return $post;
	}

	public function actionPoster(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		if ($visitor['user_id'] < 1 || (!$visitor->is_moderator && !$visitor->is_admin))
		{
			throw $this->exception($this->noPermission());
		}

		if ($this->isPost())
		{
			/** @var \Snog\TV\Entity\TV $tvShow */
			$tvShow = $this->finder('Snog\TV:TV')->where('thread_id', $params->thread_id)->fetchOne();

			$oldposter = $tvShow->thread_id . '-' . str_ireplace('/', '', $tvShow->tv_image);
			$posterPath = $this->filter('posterpath', 'str');
			$newposterpath = $tvShow->thread_id . '-' . str_ireplace('/', '', $posterPath);

			$thread = $this->assertViewableThread($params->thread_id);
			$post = $this->assertViewablePost($thread->first_post_id, ['Thread.Prefix']);

			$tempDir = FILE::getTempDir();
			$tempPath = $tempDir . $posterPath;

			$path = 'data://tv/SmallPosters/' . $newposterpath;
			$this->getImage($posterPath, 'w92', $path, $tempPath);

			$path = 'data://tv/LargePosters/' . $newposterpath;
			$this->getImage($posterPath, 'w185', $path, $tempPath);

			$tvShow->tv_image = $posterPath;
			$tvShow->save(false, false);

			$message = "[img]" . $this->getImageUrl(($newposterpath ?: '/no-poster.jpg'), $tvShow) . "[/img]" . "\r\n\r\n";
			$message .= "[B]" . \XF::phrase('title') . ":[/B] " . $tvShow->tv_title . "\r\n\r\n";
			if ($tvShow->tv_genres) $message .= "[B]" . \XF::phrase('snog_tv_genre') . ":[/B] " . $tvShow->tv_genres . "\r\n\r\n";
			if ($tvShow->tv_release) $message .= "[B]" . \XF::phrase('snog_tv_first_aired') . ":[/B] " . $tvShow->tv_release . "\r\n\r\n";
			if ($tvShow->tv_director) $message .= "[B]" . \XF::phrase('snog_tv_creator') . ":[/B] " . $tvShow->tv_director . "\r\n\r\n";
			if ($tvShow->tv_cast) $message .= "[B]" . \XF::phrase('snog_tv_cast') . ":[/B] " . $tvShow->tv_cast . "\r\n\r\n";
			if ($tvShow->tv_plot) $message .= "[B]" . \XF::phrase('snog_tv_overview') . ":[/B] " . $tvShow->tv_plot . "\r\n\r\n";
			if ($tvShow->tv_trailer) $message .= "[MEDIA=youtube]" . $tvShow->tv_trailer . "[/MEDIA]" . "\r\n\r\n";
			if ($tvShow->comment && !$this->options()->TvThreads_force_comments) $message .= $tvShow->comment;

			$post->message = $message;
			$post->last_edit_date = 0;
			$post->save();

			if ($oldposter)
			{
				$path = sprintf('data://tv/LargePosters/%s', $oldposter);
				File::deleteFromAbstractedPath($path);

				$path = sprintf('data://tv/SmallPosters/%s', $oldposter);
				File::deleteFromAbstractedPath($path);
			}

			return $this->redirect($this->buildLink('posts', $post));
		}

		/** @var \Snog\TV\Entity\TV $tvShow */
		$tvShow = $this->finder('Snog\TV:TV')->where('thread_id', $params->thread_id)->fetchOne();
		$newPoster = false;
		$posterPath = '';

		$tmdbApi = new \Snog\TV\Util\TmdbApi();
		$tvinfo = $tmdbApi->getShow($tvShow->tv_id, ['credits', 'videos']);

		if ($errors = $tmdbApi->getErrors())
		{
			return $this->error($errors);
		}

		if (!isset($tvinfo['id']))
		{
			return $this->error(\XF::phrase('snog_tv_error_not_returned'));
		}

		if (isset($tvinfo['poster_path']))
		{
			$posterPath = $tvinfo['poster_path'];
		}
		if ($posterPath !== $tvShow->tv_image)
		{
			$newPoster = true;
		}

		$viewParams = ['tvshow' => $tvShow, 'newposter' => $newPoster, 'posterpath' => $posterPath];
		return $this->view('Snog\TV:TV', 'snog_tv_new_poster', $viewParams);
	}

	public function actionAddInfo(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		if ($visitor['user_id'] < 1 || !$visitor->hasPermission('tvthreads_interface', 'add_info'))
		{
			throw $this->exception($this->noPermission());
		}

		if ($this->isPost())
		{
			$thread = $this->assertViewableThread($params->thread_id);
			$post = $this->assertViewablePost($thread->first_post_id, ['Thread.Prefix']);
			$title = $this->filter('tmdb', 'str');
			$changeTitle = $this->filter('changetitle', 'uint');
			$genres = '';
			$directors = '';
			$cast = '';
			$releaseDate = '';

			if (!$title)
			{
				return $this->error(\XF::phrase('snog_tv_error_no_show'));
			}

			$showId = \Snog\TV\Util\Tmdb::parseShowId($title);

			if (stristr($showId, '?'))
			{
				$showIdParts = explode('?', $showId);
				$showId = $showIdParts[0];
			}

			if (!$showId)
			{
				return $this->error(\XF::phrase('snog_tv_error_id_not_valid'));
			}

			/** @var \Snog\TV\Entity\TV $exists */
			$exists = $this->em()->findOne('Snog\TV:TV', ['tv_id' => $showId]);
			$comment = $post->message;

			// SHOW ALREADY EXISTS - IF COMMENTS MADE POST TO EXISTING THREAD
			if (!$this->options()->TvThreads_multiple)
			{
				if (isset($exists->tv_id))
				{
					return $this->error(\XF::phrase('snog_tv_error_show_posted'));
				}
			}

			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$show = $tmdbApi->getShow($showId, ['credits', 'videos']);

			if ($errors = $tmdbApi->getErrors())
			{
				return $this->error($errors);
			}

			foreach ($show['genres'] as $genre)
			{
				if ($genres) $genres .= ', ';
				$genres .= $genre['name'];
			}

			if (isset($show['created_by']))
			{
				foreach ($show['created_by'] as $director)
				{
					if ($directors) $directors .= ', ';
					$directors .= $director['name'];
				}
			}

			if (isset($show['credits']))
			{
				foreach ($show['credits']['cast'] as $member)
				{
					if ($cast) $cast .= ', ';
					$cast .= str_replace(',', '', $member['name']);
				}
			}

			$trailer = '';
			if (isset($show['videos']['results']['0']))
			{
				foreach ($show['videos']['results'] as $video)
				{
					if ($video['site'] == 'YouTube')
					{
						$trailer = $video['key'];
						break;
					}
				}
			}

			if (!empty($show['first_air_date'])) $releaseDate = $show['first_air_date'];
			$tvtitle = html_entity_decode($show['name']);
			$plot = html_entity_decode($show['overview']);

			if (isset($show['poster_path']))
			{
				$tempDir = FILE::getTempDir();
				$tempPath = $tempDir . $show['poster_path'];

				$path = 'data://tv/SmallPosters' . $show['poster_path'];
				$this->getImage($show['poster_path'], 'w92', $path, $tempPath);

				$path = 'data://tv/LargePosters' . $show['poster_path'];

				$this->getImage($show['poster_path'], 'w185', $path, $tempPath);
			}

			$message = "[img]" . $this->getImageUrl(($show['poster_path'] ?: '/no-poster.jpg'), $thread) . "[/img]" . "\r\n\r\n";
			$message .= "[B]" . \XF::phrase('title') . ":[/B] " . $tvtitle . "\r\n\r\n";
			if ($genres) $message .= "[B]" . \XF::phrase('snog_tv_genre') . ":[/B] " . $genres . "\r\n\r\n";
			if ($releaseDate) $message .= "[B]" . \XF::phrase('snog_tv_first_aired') . ":[/B] " . $releaseDate . "\r\n\r\n";
			if ($directors) $message .= "[B]" . \XF::phrase('snog_tv_creator') . ":[/B] " . $directors . "\r\n\r\n";
			if ($cast) $message .= "[B]" . \XF::phrase('snog_tv_cast') . ":[/B] " . $cast . "\r\n\r\n";
			if ($plot) $message .= "[B]" . \XF::phrase('snog_tv_overview') . ":[/B] " . $plot . "\r\n\r\n";
			if ($trailer) $message .= "[MEDIA=youtube]" . $trailer . "[/MEDIA]" . "\r\n\r\n";
			if (!$this->options()->TvThreads_force_comments) $message .= $comment;

			if ($changeTitle)
			{
				$thread->title = $tvtitle;
				$thread->save();
			}

			$post->message = $message;
			$post->last_edit_date = 0;
			$post->save();

			if ($comment && $this->options()->TvThreads_force_comments)
			{
				$newFirstPost = false;
				$newLastPost = false;

				/** @var \XF\Entity\Post $newPost */
				$newPost = $this->em()->create('XF:Post');
				$newPost->thread_id = $thread->thread_id;
				$newPost->user_id = $thread->user_id;
				$newPost->username = $thread->username;
				$newPost->post_date = $thread->post_date;
				$newPost->message = $comment;
				$newPost->ip_id = $post->ip_id;
				$newPost->position = 1;
				$newPost->last_edit_date = 0;
				$newPost->save();
				$newPostId = $newPost->getEntityId();

				if ($thread->first_post_id > 0 && $thread->first_post_id <> $post->post_id)
				{
					$newFirstPost = true;
				}
				if ($thread->first_post_id == $thread->last_post_id)
				{
					$newLastPost = true;
				}

				if ($newFirstPost)
				{
					$thread->first_post_id = $newPostId;
				}
				if ($newLastPost)
				{
					$thread->last_post_date = $thread->post_date;
					$thread->last_post_id = $newPostId;
					$thread->last_post_user_id = $thread->user_id;
					$thread->last_post_username = $thread->username;
				}

				if ($thread->isChanged('first_post_id') || $thread->isChanged('last_post_id'))
				{
					$thread->save();
				}

				/** @var \XF\Entity\Post[] $postOrder */
				$postOrder = $this->finder('XF:Post')
					->where('thread_id', $thread->thread_id)
					->order('post_date')
					->fetch();

				$order = 1;
				foreach ($postOrder as $changeorder)
				{
					if ($changeorder->post_id <> $thread->first_post_id)
					{
						$changeorder->position = $order;
						$changeorder->save();
						$order = $order + 1;
					}
				}
			}

			// SETUP POSTER WITH THREAD ID
			if ($show['poster_path'] > '')
			{
				$posterName = $thread->thread_id . '-' . str_ireplace('/', '', $show['poster_path']);

				// SMALL POSTER
				$tempPath = File::copyAbstractedPathToTempFile('data://tv/SmallPosters' . $show['poster_path']);
				$path = 'data://tv/SmallPosters/' . $posterName;
				File::copyFileToAbstractedPath($tempPath, $path);
				unlink($tempPath);
				$path = sprintf('data://tv/SmallPosters%s', $show['poster_path']);
				File::deleteFromAbstractedPath($path);

				// LARGE POSTER
				$tempPath = File::copyAbstractedPathToTempFile('data://tv/LargePosters' . $show['poster_path']);
				$path = 'data://tv/LargePosters/' . $posterName;
				File::copyFileToAbstractedPath($tempPath, $path);
				unlink($tempPath);
				$path = sprintf('data://tv/LargePosters%s', $show['poster_path']);
				File::deleteFromAbstractedPath($path);
			}

			/** @var \Snog\TV\Entity\TV $tv */
			$tv = $this->em()->create('Snog\TV:TV');
			$tv->thread_id = $thread->thread_id;
			$tv->tv_id = $showId;
			$tv->tv_title = $tvtitle;
			$tv->tv_plot = $plot;
			$tv->tv_image = $show['poster_path'];
			$tv->tv_trailer = $trailer;
			$tv->tv_genres = $genres;
			$tv->tv_director = $directors;
			$tv->tv_cast = $cast;
			$tv->tv_release = $releaseDate;
			if (!$this->options()->TvThreads_force_comments)
			{
				$tv->comment = $comment;
			}

			$tv->save(false, false);

			return $this->redirect($this->buildLink('threads', $thread));
		}

		/** @var \Snog\TV\XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $params->thread_id);
		$viewParams = ['thread' => $thread];

		return $this->view('Snog\TV:TV', 'snog_tv_add_info', $viewParams);
	}

	/**
	 * @param $nodeIdOrName
	 * @param array $extraWith
	 * @return \Snog\TV\XF\Entity\Forum
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableForum($nodeIdOrName, array $extraWith = [])
	{
		if ($nodeIdOrName === null)
		{
			throw new \InvalidArgumentException("Node ID/name not passed in correctly");
		}

		$visitor = \XF::visitor();
		$extraWith[] = 'Node.Permissions|' . $visitor->permission_combination_id;
		if ($visitor->user_id)
		{
			$extraWith[] = 'Read|' . $visitor->user_id;
		}

		$finder = $this->em()->getFinder('XF:Forum');
		$finder->with('Node', true)->with($extraWith);
		if (is_int($nodeIdOrName) || $nodeIdOrName === strval(intval($nodeIdOrName)))
		{
			$finder->where('node_id', $nodeIdOrName);
		}
		else
		{
			$finder->where(['Node.node_name' => $nodeIdOrName, 'Node.node_type_id' => 'Forum']);
		}

		/** @var \Snog\TV\XF\Entity\Forum $forum */
		$forum = $finder->fetchOne();
		if (!$forum)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_forum_not_found')));
		}

		if (!$forum->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		/** @var \XF\ControllerPlugin\Node $nodePlugin */
		$nodePlugin = $this->plugin('XF:Node');
		$nodePlugin->applyNodeContext($forum->Node);

		return $forum;
	}

	public function getImage($srcPath, $size, $localPath, $tempPath)
	{
		$tmdbApi = new \Snog\TV\Util\TmdbApi();
		$poster = $tmdbApi->getPoster($srcPath, $size);

		if ($errors = $tmdbApi->getErrors())
		{
			throw $this->exception($this->error($errors));
		}

		if (file_exists($tempPath))
		{
			unlink($tempPath);
		}
		file_put_contents($tempPath, $poster);

		File::copyFileToAbstractedPath($tempPath, $localPath);
		unlink($tempPath);
	}

	public function getImageUrl($posterpath, $thread, $canonical = true)
	{
		$image = str_ireplace('/', '', $posterpath);
		return $this->app->applyExternalDataUrl("tv/LargePosters/{$thread->thread_id}-{$image}", $canonical);
	}

	public function getEpisodeImageUrl($posterpath, $post, $canonical = true)
	{
		$image = str_ireplace('/', '', $posterpath);
		return $this->app->applyExternalDataUrl("tv/EpisodePosters/{$post->post_id}-{$image}", $canonical);
	}
}