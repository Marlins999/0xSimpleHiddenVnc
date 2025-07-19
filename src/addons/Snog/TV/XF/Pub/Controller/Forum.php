<?php

namespace Snog\TV\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Util\File;

class Forum extends XFCP_Forum
{
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionPostThread(ParameterBag $params)
	{
		if (!$this->isPost())
		{
			return parent::actionPostThread($params);
		}

		if (!$params->node_id && !$params->node_name)
		{
			return parent::actionPostThread($params);
		}

		$visitor = \XF::visitor();

		/** @var \Snog\TV\XF\Entity\Forum $forum */
		$forum = $this->assertViewableForum($params->node_id ?: $params->node_name, ['DraftThreads|' . $visitor->user_id]);

		if (!in_array($forum->node_id, $this->options()->TvThreads_forum))
		{
			return parent::actionPostThread($params);
		}

		$originalNode = $forum->node_id;
		$title = $this->filter('title', 'str');

		if ($this->options()->TvThreads_mix && !stristr($title, "themoviedb") && !is_numeric($title))
		{
			return parent::actionPostThread($params);
		}

		if (!$forum->canCreateThread($error))
		{
			return $this->noPermission($error);
		}

		$switches = $this->filter(['inline-mode' => 'bool', 'more-options' => 'bool']);
		if ($switches['more-options'])
		{
			return parent::actionPostThread($params);
		}

		if ($switches['more-options'])
		{
			$switches['inline-mode'] = false;
		}

		$allowed = !$this->options()->TvThreads_use_genres;

		if (!$allowed && empty($forum->TVnode->tv_genre))
		{
			return $this->error(\XF::phrase('snog_tv_error_no_genre'));
		}

		$crossLink = $this->options()->TvThreads_use_genres && $this->options()->TvThreads_crosslink;
		if ($visitor->isShownCaptcha() && !$this->app->captcha()->isValid())
		{
			return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
		}

		/** @var \XF\ControllerPlugin\Editor $editorPlugin */
		$editorPlugin = $this->plugin('XF:Editor');
		$comment = $editorPlugin->fromInput('message');

		if (stristr($title, 'themoviedb.org/movie/'))
		{
			return $this->error(\XF::phrase('snog_tv_error_movie_id'));
		}

		if (stristr($title, 'themoviedb.org/search'))
		{
			return $this->error(\XF::phrase('snog_tv_error_id_not_valid'));
		}

		$showId = \Snog\TV\Util\Tmdb::parseShowId($title);

		if (!$showId)
		{
			return $this->error(\XF::phrase('snog_tv_error_id_not_valid'));
		}

		if (!$this->options()->TvThreads_multiple)
		{
			/** @var \Snog\TV\Entity\TV $exists */
			$exists = $this->finder('Snog\TV:TV')->where('tv_id', $showId)->fetchOne();

			// SHOW ALREADY EXISTS - IF COMMENTS MADE POST TO EXISTING THREAD
			if (isset($exists->tv_id) && $comment)
			{
				/** @var \XF\Entity\Thread $thread */
				$thread = $exists->getRelationOrDefault('Thread');

				/** @var \XF\Service\Thread\Replier $replier */
				$replier = $this->service('XF:Thread\Replier', $thread);
				$replier->setMessage($comment);

				if ($forum->canUploadAndManageAttachments())
				{
					$replier->setAttachmentHash($this->filter('attachment_hash', 'str'));
				}

				$post = $replier->save();

				/** @var \XF\ControllerPlugin\Thread $threadPlugin */
				$threadPlugin = $this->plugin('XF:Thread');
				return $this->redirect($threadPlugin->getPostLink($post), 'Your comments have been posted in the existing thread for the TV show');
			}

			// SHOW ALREADY EXISTS - NO COMMENTS - SEND TO EXISTING THREAD
			if (isset($exists->tv_id))
			{
				/** @var \XF\Entity\Thread $thread */
				$thread = $exists->getRelationOrDefault('Thread');
				return $this->redirect($this->buildLink('threads', $thread));
			}
		}

		$tmdbApi = new \Snog\TV\Util\TmdbApi();
		$tv = $tmdbApi->getShow($showId, ['credits', 'videos']);

		if ($errors = $tmdbApi->getErrors())
		{
			return $this->error($errors);
		}

		$genres = '';
		foreach ($tv['genres'] as $genre)
		{
			if ($genres) $genres .= ', ';
			$genres .= $genre['name'];

			// CHECK IF GENRE IS ALLOWED IN THIS FORUM
			if (!$allowed)
			{
				if (in_array($genre['name'], $forum->TVnode->tv_genre)) $allowed = true;
			}
		}

		// GENRE NOT ALLOWED? CHECK ALL TV FORUMS
		if (!$allowed)
		{
			/** @var \Snog\TV\XF\Entity\Forum[] $tvForums */
			$tvForums = $this->finder('XF:Forum')
				->with('TVnode')
				->where('TVnode.node_id', '>', '')
				->fetch();

			$checkGenres = explode(',', $genres);

			foreach ($tvForums as $genreForum)
			{
				foreach ($checkGenres as $genre)
				{
					if (in_array(trim($genre), $genreForum->TVnode->tv_genre))
					{
						$allowed = true;

						/** @var \XF\Entity\Forum $forum */
						$forum = $this->em()->find('XF:Forum', $genreForum->node_id);
						break;
					}
				}
			}

			// LAST CHANCE CHECK FOR CATCHALL FORUM
			if (!$allowed)
			{
				foreach ($tvForums as $genreForum)
				{
					if (in_array('All', $genreForum->TVnode->tv_genre))
					{
						$allowed = true;

						/** @var \XF\Entity\Forum $forum */
						$forum = $this->em()->find('XF:Forum', $genreForum->node_id);
						break;
					}
				}
			}

			if (!$allowed) return $this->error(\XF::phrase('snog_tv_error_genre_not_allowed'));
		}

		$directors = '';
		if (isset($tv['created_by']))
		{
			foreach ($tv['created_by'] as $director)
			{
				if ($directors) $directors .= ', ';
				$directors .= $director['name'];
			}
		}

		$cast = '';
		if (isset($tv['credits']))
		{
			foreach ($tv['credits']['cast'] as $member)
			{
				if ($cast) $cast .= ', ';
				$cast .= $member['name'];
			}
		}

		$trailer = '';
		if (isset($tv['videos']['results']['0']))
		{
			foreach ($tv['videos']['results'] as $video)
			{
				if ($video['site'] == 'YouTube')
				{
					$trailer = $video['key'];
					break;
				}
			}
		}

		$releasedate = '';
		if (!empty($tv['first_air_date'])) $releasedate = $tv['first_air_date'];
		$tvtitle = html_entity_decode($tv['name']);
		$plot = html_entity_decode($tv['overview']);

		if (isset($tv['poster_path']))
		{
			$tempDir = FILE::getTempDir();
			$tempPath = $tempDir . $tv['poster_path'];

			$path = 'data://tv/SmallPosters' . $tv['poster_path'];
			$this->getTvImage($tv['poster_path'], 'w92', $path, $tempPath);

			$path = 'data://tv/LargePosters' . $tv['poster_path'];
			$this->getTvImage($tv['poster_path'], 'w185', $path, $tempPath);
		}

		// CREATE DEFAULT THREAD/MESSAGE WITHOUT PRETTY FORMATTING
		$message = "[img]" . $this->getTVImageUrl(($tv['poster_path'] ?: '/no-poster.jpg')) . "[/img]" . "\r\n\r\n";
		$message .= "[B]" . \XF::phrase('title') . ":[/B] " . $tvtitle . "\r\n\r\n";
		if ($genres) $message .= "[B]" . \XF::phrase('snog_tv_genre') . ":[/B] " . $genres . "\r\n\r\n";
		if ($directors) $message .= "[B]" . \XF::phrase('snog_tv_creator') . ":[/B] " . $directors . "\r\n\r\n";
		if ($cast) $message .= "[B]" . \XF::phrase('snog_tv_cast') . ":[/B] " . $cast . "\r\n\r\n";
		if ($releasedate) $message .= "[B]" . \XF::phrase('snog_tv_first_aired') . ":[/B] " . $releasedate . "\r\n\r\n";
		if ($plot) $message .= "[B]" . \XF::phrase('snog_tv_overview') . ":[/B] " . $plot . "\r\n\r\n";
		if ($trailer) $message .= "[MEDIA=youtube]" . $trailer . "[/MEDIA]" . "\r\n\r\n";
		if (!$this->options()->TvThreads_force_comments) $message .= $comment;
		$title = $tvtitle;

		/** @var \XF\Service\Thread\Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $forum);
		$creator->setContent($title, $message);

		$addOns = $this->app()->container('addon.cache');
		$isMultiPrefix = isset($addOns['SV/MultiPrefix']);

		$prefixId = $isMultiPrefix
			? $this->filter('prefix_id', 'array-uint')
			: $this->filter('prefix_id', 'uint');

		if ($prefixId && $forum->isPrefixUsable($prefixId))
		{
			$creator->setPrefix($prefixId);
		}

		if ($forum->canEditTags())
		{
			$creator->setTags($this->filter('tags', 'str'));
		}

		if (!$this->options()->TvThreads_force_comments && $forum->canUploadAndManageAttachments())
		{
			$creator->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		$setOptions = $this->filter('_xfSet', 'array-bool');
		if ($setOptions)
		{
			$thread = $creator->getThread();

			if (isset($setOptions['discussion_open']) && $thread->canLockUnlock())
			{
				$creator->setDiscussionOpen($this->filter('discussion_open', 'bool'));
			}
			if (isset($setOptions['sticky']) && $thread->canStickUnstick())
			{
				$creator->setSticky($this->filter('sticky', 'bool'));
			}
		}

		$customFields = $this->filter('custom_fields', 'array');
		$creator->setCustomFields($customFields);
		$creator->checkForSpam();

		/** @var \XF\Service\Thread\Creator $errors */
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$this->assertNotFlooding('post');

		$thread = $creator->save();

		// SETUP POSTER WITH THREAD ID
		if ($tv['poster_path'] > '')
		{
			$posterName = '/' . $thread->thread_id . '-' . str_ireplace('/', '', $tv['poster_path']);

			// SMALL POSTER
			$oldpath = 'data://tv/SmallPosters' . $tv['poster_path'];
			$newpath = 'data://tv/SmallPosters' . $posterName;

			try
			{
				\XF::app()->fs()->move($oldpath, $newpath);
			}
			catch (\League\Flysystem\FileNotFoundException $e)
			{
			}

			// LARGE POSTER
			$oldpath = 'data://tv/LargePosters' . $tv['poster_path'];
			$newpath = 'data://tv/LargePosters' . $posterName;

			try
			{
				\XF::app()->fs()->move($oldpath, $newpath);
			}
			catch (\League\Flysystem\FileNotFoundException $e)
			{
			}
		}
		else
		{
			$posterName = '';
		}

		$replaceMessage = "[img]" . $this->getTVImageUrl(($tv['poster_path'] ?: '/no-poster.jpg')) . "[/img]" . "\r\n\r\n";
		$withReplacement = "[img]" . $this->getTVImageUrl(($posterName ?: '/no-poster.jpg')) . "[/img]" . "\r\n\r\n";

		if ($tv['poster_path'])
		{
			$post = $thread->FirstPost;
			$post->message = str_ireplace($replaceMessage, $withReplacement, $post->message);
			$post->save();
		}

		/** @var \Snog\TV\Entity\TV $tvem */
		$tvem = $this->em()->create('Snog\TV:TV');
		$tvem->thread_id = $thread->thread_id;
		$tvem->tv_id = $showId;
		$tvem->tv_title = $title;
		$tvem->tv_plot = $plot;
		$tvem->tv_image = (is_null($tv['poster_path']) ? '' : $tv['poster_path']);
		$tvem->tv_trailer = $trailer;
		$tvem->tv_genres = $genres;
		$tvem->tv_director = $directors;
		$tvem->tv_cast = $cast;
		$tvem->tv_release = $releasedate;
		if ($comment && !$this->options()->TvThreads_force_comments)
		{
			$tvem->comment = $comment;
		}
		$tvem->save(false, false);

		if ($comment && $this->options()->TvThreads_force_comments)
		{
			/** @var \XF\Service\Thread\Replier $replier */
			$replier = $this->service('XF:Thread\Replier', $thread);
			$replier->setMessage($comment);
			if ($forum->canUploadAndManageAttachments())
				$replier->setAttachmentHash($this->filter('attachment_hash', 'str'));

			$replier->save();
		}

		if ($crossLink)
		{
			/** @var \Snog\TV\XF\Entity\Forum[] $tvForums */
			$tvForums = $this->finder('XF:Forum')
				->with('TVnode')
				->where('TVnode.node_id', '>', '')
				->fetch();

			$checkGenres = explode(',', $genres);
			$data = $thread->toArray(false);
			$noLink[] = $data['node_id'];
			unset($data['thread_id'], $data['node_id']);
			$data['first_post_id'] = 0;
			$data['discussion_type'] = 'redirect';

			foreach ($tvForums as $genreForum)
			{
				foreach ($checkGenres as $genre)
				{
					if (in_array(trim($genre), $genreForum->TVnode->tv_genre) && !in_array($genreForum->TVnode->node_id, $noLink))
					{
						$noLink[] = $genreForum->node_id;
						$data['node_id'] = $genreForum->node_id;

						/** @var \XF\Entity\Thread $crossLink */
						$crossLink = $this->em()->create('XF:Thread');
						$crossLink->bulkSet($data);
						$crossLink->save();
						$crosslinkThreadId = $crossLink->getEntityId();

						/** @var \XF\Entity\ThreadRedirect $redirect */
						$redirect = $this->em()->create('XF:ThreadRedirect');
						$redirect->thread_id = $crosslinkThreadId;
						$redirect->target_url = $this->app()->router('public')->buildLink('nopath:threads', $thread);
						$redirect->redirect_key = "thread-{$thread->thread_id}-{$thread->node_id}-";
						$redirect->expiry_date = 0;
						$redirect->save();

						/** @var \Snog\TV\Entity\TV $tvem */
						$tvem = $this->em()->create('Snog\TV:TV');
						$tvem->thread_id = $crosslinkThreadId;
						$tvem->tv_id = $showId;
						$tvem->tv_title = $title;
						$tvem->tv_plot = $plot;
						$tvem->tv_image = $tv['poster_path'];
						$tvem->tv_genres = $genres;
						$tvem->tv_director = $directors;
						$tvem->tv_cast = $cast;
						$tvem->tv_release = $releasedate;
						$tvem->save(false, false);
					}
				}
			}
		}

		// MOVED HERE FOR COMPATIBLITY ISSUES WITH XenPorta REDIRECTING IN THE FUNCTION

		$this->finalizeThreadCreate($creator);

		if ($switches['inline-mode'] && $forum->node_id == $originalNode)
		{
			$viewParams = ['thread' => $thread, 'forum' => $forum];
			return $this->view('XF:Forum\ThreadItem', 'thread_list_item', $viewParams);
		}
		else if (!$thread->canView())
		{
			return $this->redirect($this->buildLink('forums', $forum, ['pending_approval' => 1]));
		}

		return $this->redirect($this->buildLink('threads', $thread));
	}

	public function actionAddTVForum(ParameterBag $params)
	{
		$nodeId = $params->node_id;

		if ($this->isPost())
		{
			$genres = '';
			$directors = '';
			$cast = '';
			$parentNodeId = $this->filter('node_id', 'uint');

			/** @var \XF\Entity\Node $parent */
			$parent = $this->em()->find('XF:Node', $parentNodeId);

			$title = $this->filter('tvlink', 'str');

			$showId = \Snog\TV\Util\Tmdb::parseShowId($title);

			if (!$showId)
			{
				return $this->error(\XF::phrase('snog_tv_error_id_not_valid'));
			}

			/** @var \Snog\TV\Entity\TVForum $exists */
			$exists = $this->finder('Snog\TV:TVForum')->where('tv_id', $showId)->fetchOne();

			if (isset($exists->tv_id))
			{
				return $this->error(\XF::phrase('snog_tv_error_forum_exists'));
			}

			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$show = $tmdbApi->getShow($showId, ['credits']);

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
					$cast .= $member['name'];
				}
			}

			$tvTitle = html_entity_decode($show['name']);

			if (isset($show['poster_path']))
			{
				$tempDir = FILE::getTempDir();
				$path = 'data://tv/ForumPosters' . $show['poster_path'];
				$tempPath = $tempDir . $show['poster_path'];
				$this->getTvImage($show['poster_path'], 'w92', $path, $tempPath);
			}

			// CREATE NODE
			$newForumName = substr($tvTitle, 0, 50);

			/** @var \XF\Entity\Node $node */
			$node = $this->em()->create('XF:Node');
			$node->title = $newForumName;
			$node->node_type_id = 'Forum';
			$node->parent_node_id = $parentNodeId;
			$node->save(false, false);
			$nodeId = $node->getEntityId();

			// CREATE FORUM

			/** @var \XF\Entity\Forum $forum */
			$forum = $this->em()->create('XF:Forum');
			$forum->node_id = $nodeId;
			$forum->save(false, false);
			$forum_id = $forum->getEntityId();

			// CREATE TV INFO

			/** @var \Snog\TV\Entity\TVForum $tvForum */
			$tvForum = $this->em()->create('Snog\TV:TVForum');
			$tvForum->node_id = $forum_id;
			$tvForum->tv_id = $show['id'];
			$tvForum->tv_genres = $genres;
			$tvForum->tv_director = $directors;
			$tvForum->tv_cast = $cast;
			if ($show['poster_path'] > '') $tvForum->tv_image = $show['poster_path'];
			$tvForum->tv_release = $show['first_air_date'];
			$tvForum->tv_title = $newForumName;
			$tvForum->tv_plot = $show['overview'];
			$tvForum->save(false, false);

			// REBUILD PERMISSIONS SO USER ADDING FORUM CAN SEE FORUM WITHOUT REFRESH

			/** @var \XF\Repository\PermissionCombination $combinationRepo */
			$combinationRepo = $this->repository('XF:PermissionCombination');
			$combination = $combinationRepo->updatePermissionCombinationForUser(\XF::visitor(), false);
			$this->app->permissionBuilder()->rebuildCombination($combination);

			// SORT FORUMS ALPHABETICALLY

			/** @var \XF\Entity\Node[] $nodeSort */
			$nodeSort = $this->finder('XF:Node')
				->where('parent_node_id', $parentNodeId)
				->order('title')
				->fetch();

			$order = 100;

			foreach ($nodeSort as $node)
			{
				$node->display_order = $order;
				$node->save();
				$order = $order + 100;
			}

			return $this->redirect($this->buildLink('categories', $parent));
		}

		$viewParams = ['node_id' => $nodeId];
		return $this->view('Snog\TV:TV', 'snog_tv_newshow', $viewParams);
	}

	public function actionNewSeason(ParameterBag $params)
	{
		$node_id = $params->node_id;

		if ($this->isPost())
		{
			$parentNodeId = $this->filter('node_id', 'uint');
			$season = $this->filter('season', 'uint');

			/** @var \Snog\TV\XF\Entity\Node $show */
			$show = $this->em()->find('XF:Node', $parentNodeId);
			$showId = $show->TVForum->tv_id;
			$newForumName = $show->TVForum->tv_title;

			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$seasonInfo = $tmdbApi->getSeason($showId, $season);

			if ($errors = $tmdbApi->getErrors())
			{
				return $this->error($errors);
			}

			$newForumName .= ": " . html_entity_decode($seasonInfo['name']);

			// DOWNLOAD SEASON POSTER
			if (isset($seasonInfo['poster_path']))
			{
				$tempDir = FILE::getTempDir();
				$path = 'data://tv/SeasonPosters' . $seasonInfo['poster_path'];
				$tempPath = $tempDir . $seasonInfo['poster_path'];
				$this->getTvImage($seasonInfo['poster_path'], 'w92', $path, $tempPath);
			}

			// CREATE NODE

			/** @var \XF\Entity\Node $node */
			$node = $this->em()->create('XF:Node');
			//$node->title = $newForumName;
			$node->title = substr($newForumName, 0, 50);
			$node->node_type_id = 'Forum';
			$node->display_order = $season * 100;
			$node->parent_node_id = $parentNodeId;
			$node->save(false, false);
			$node_id = $node->getEntityId();

			// CREATE FORUM

			/** @var \XF\Entity\Forum $forum */
			$forum = $this->em()->create('XF:Forum');
			$forum->node_id = $node_id;
			$forum->save(false, false);
			$forum_id = $forum->getEntityId();

			// CREATE TV INFO

			/** @var \Snog\TV\Entity\TVForum $tvForum */
			$tvForum = $this->em()->create('Snog\TV:TVForum');
			$tvForum->node_id = $forum_id;
			$tvForum->tv_parent = $parentNodeId;
			$tvForum->tv_id = $seasonInfo['id'];
			$tvForum->tv_parent_id = $show->TVForum->tv_id;
			$tvForum->tv_season = $season;
			if ($seasonInfo['poster_path'] > '') $tvForum->tv_image = $seasonInfo['poster_path'];
			$tvForum->tv_release = $seasonInfo['air_date'];
			$tvForum->tv_title = $newForumName;
			if ($seasonInfo['overview'] > '') $tvForum->tv_plot = $seasonInfo['overview'];
			$tvForum->save(false, false);

			// REBUILD PERMISSIONS SO USER ADDING FORUM CAN SEE FORUM WITHOUT REFRESH

			/** @var \XF\Repository\PermissionCombination $combinationRepo */
			$combinationRepo = $this->repository('XF:PermissionCombination');
			$combination = $combinationRepo->updatePermissionCombinationForUser(\XF::visitor(), false);
			$this->app->permissionBuilder()->rebuildCombination($combination);

			/** @var \XF\Entity\Node[] $tvNodes */
			$tvNodes = $this->finder('XF:Node')
				->where('parent_node_id', $parentNodeId)
				->order('TVForum.tv_season', $this->options()->TvThreads_sort)
				->fetch();

			$order = 100;

			foreach ($tvNodes as $node)
			{
				$node->display_order = $order;
				$node->save();
				$order = $order + 100;
			}

			return $this->redirect($this->buildLink('forums', $show));
		}

		$viewParams = ['node_id' => $node_id];
		return $this->view('Snog\TV:TV', 'snog_tv_newseason', $viewParams);
	}

	public function actionNewEpisode(ParameterBag $params)
	{
		if (!$this->isPost())
		{
			return parent::actionPostThread($params);
		}

		$nodeId = $params->node_id;

		$episode = $this->filter('title', 'uint');

		if (!is_numeric($episode) || $episode <= 0)
		{
			return $this->error(\XF::phrase('snog_tv_error_episode_number'));
		}

		$forum = $this->assertViewableForum($params->node_id ?: $params->node_name, ['DraftThreads|' . \XF::visitor()->user_id]);

		if (!$forum->canCreateThread($error))
		{
			return $this->noPermission($error);
		}

		$switches = $this->filter(['inline-mode' => 'bool', 'more-options' => 'bool']);
		$newThreadTitle = '';

		/** @var \Snog\TV\XF\Entity\Node $show */
		$show = $this->finder('XF:Node')->where('node_id', $nodeId)->fetchOne();

		/** @var \Snog\TV\XF\Entity\Node $parent */
		$parent = $this->finder('XF:Node')->where('node_id', $show->TVForum->tv_parent)->fetchOne();

		$tvShow = $show->TVForum->tv_parent_id;
		$tvSeason = $show->TVForum->tv_season;

		if (!$this->options()->TvThreads_episode_exclude)
		{
			$newThreadTitle = $parent->TVForum->tv_title;
		}

		/** @var \XF\ControllerPlugin\Editor $editorPlugin */
		$editorPlugin = $this->plugin('XF:Editor');
		$comment = $editorPlugin->fromInput('message');

		$tmdbApi = new \Snog\TV\Util\TmdbApi();
		$episodeInfo = $tmdbApi->getEpisode($tvShow, $tvSeason, $episode, ['credits']);

		if ($errors = $tmdbApi->getErrors())
		{
			return $this->error($errors);
		}

		if (!$this->options()->TvThreads_episode_exclude)
		{
			$newThreadTitle .= ': ';
		}

		$newThreadTitle .= 'S' . str_pad($episodeInfo['season_number'], 2, '0', STR_PAD_LEFT);
		$newThreadTitle .= 'E' . str_pad($episodeInfo['episode_number'], 2, '0', STR_PAD_LEFT);
		$newThreadTitle .= " " . html_entity_decode($episodeInfo['name']);

		// DOWNLOAD EPISODE IMAGE
		if ($episodeInfo['still_path'] > '')
		{
			$tempDir = FILE::getTempDir();
			$path = 'data://tv/EpisodePosters' . $episodeInfo['still_path'];
			$tempPath = $tempDir . $episodeInfo['still_path'];
			$this->getTvImage($episodeInfo['still_path'], 'w300', $path, $tempPath);
		}

		$messageInfo = "[B]" . $parent->TVForum->tv_title . "[/B]" . "\r\n";
		$messageInfo .= '[IMG]' . $this->getEpisodeImageUrl(($episodeInfo['still_path'] ?: '/no-poster.jpg')) . '[/IMG]' . "\r\n";

		$guest = '';
		$permStars = '';
		if (!empty($episodeInfo['credits']['guest_stars']))
		{
			foreach ($episodeInfo['credits']['cast'] as $cast)
			{
				if ($permStars) $permStars .= ', ';
				$permStars .= $cast['name'];
			}

			$checkStars = explode(',', $permStars);
			foreach ($episodeInfo['credits']['guest_stars'] as $guestStar)
			{
				if (!in_array($guestStar['name'], $checkStars))
				{
					if ($guest) $guest .= ', ';
					$guest .= $guestStar['name'];
				}
			}
		}

		$messageInfo .= "[B]" . $episodeInfo['name'] . "[/B]" . "\r\n";
		$messageInfo .= "[B]" . \XF::phrase('snog_tv_season') . ":[/B] " . $episodeInfo['season_number'] . "\r\n";
		$messageInfo .= "[B]" . \XF::phrase('snog_tv_episode') . ":[/B] " . $episodeInfo['episode_number'] . "\r\n";
		$messageInfo .= "[B]" . \XF::phrase('snog_tv_air_date') . ":[/B] " . $episodeInfo['air_date'] . "\r\n\r\n";
		if (!empty($guest))
		{
			$messageInfo .= "[B]" . \XF::phrase('snog_tv_guest_stars') . ":[/B] " . $guest . "\r\n\r\n";
		}
		$messageInfo .= $episodeInfo['overview'] . "\r\n";
		$message = $messageInfo . "\r\n\r\n";
		$message .= $comment;

		/** @var \XF\Service\Thread\Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $forum);
		$creator->setContent($newThreadTitle, $message);

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId && $forum->isPrefixUsable($prefixId))
		{
			$creator->setPrefix($prefixId);
		}
		if ($forum->canEditTags())
		{
			$creator->setTags($this->filter('tags', 'str'));
		}

		$setOptions = $this->filter('_xfSet', 'array-bool');

		if ($setOptions)
		{
			$thread = $creator->getThread();

			if (isset($setOptions['discussion_open']) && $thread->canLockUnlock())
			{
				$creator->setDiscussionOpen($this->filter('discussion_open', 'bool'));
			}
			if (isset($setOptions['sticky']) && $thread->canStickUnstick())
			{
				$creator->setSticky($this->filter('sticky', 'bool'));
			}
		}

		$customFields = $this->filter('custom_fields', 'array');
		$creator->setCustomFields($customFields);
		$creator->checkForSpam();

		/** @var \XF\Validator\Username $errors */
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$this->assertNotFlooding('post');

		$thread = $creator->save();

		/** @var \Snog\TV\Entity\TV $tv */
		$tv = $this->em()->create('Snog\TV:TV');
		$tv->thread_id = $thread->thread_id;
		$tv->tv_id = $parent->TVForum->tv_id;
		$tv->tv_title = $parent->TVForum->tv_title;
		$tv->tv_plot = $episodeInfo['overview'];
		$tv->tv_season = $episodeInfo['season_number'];
		$tv->tv_episode = $episodeInfo['episode_number'];
		if (!empty($guest))
		{
			$tv->tv_cast = $guest;
		}
		$tv->tv_release = $episodeInfo['air_date'];
		if ($comment)
		{
			$tv->comment = $comment;
		}
		$tv->save(false, false);

		/** @var \Snog\TV\Entity\TVPost $episodePost */
		$episodePost = $this->em()->create('Snog\TV:TVPost');
		$episodePost->post_id = $thread->first_post_id;
		$episodePost->tv_id = $parent->TVForum->tv_id;
		$episodePost->tv_title = $episodeInfo['name'];
		$episodePost->tv_plot = $episodeInfo['overview'];
		$episodePost->tv_image = $episodeInfo['still_path'];
		$episodePost->tv_season = $episodeInfo['season_number'];
		$episodePost->tv_episode = $episodeInfo['episode_number'];
		if (!empty($guest))
		{
			$episodePost->tv_guest = $guest;
		}
		$episodePost->tv_aired = $episodeInfo['air_date'];
		if ($comment)
		{
			$episodePost->message = $comment;
		}
		$episodePost->save(false, false);

		// MOVE EPISODE IMAGE TO POST ID + IMAGE NAME
		if ($episodeInfo['still_path'] > '')
		{
			$imageName = $thread->first_post_id . '-' . str_ireplace('/', '', $episodeInfo['still_path']);

			$tempPath = File::copyAbstractedPathToTempFile('data://tv/EpisodePosters' . $episodeInfo['still_path']);
			$path = 'data://tv/EpisodePosters/' . $imageName;
			File::copyFileToAbstractedPath($tempPath, $path);
			unlink($tempPath);

			$path = sprintf('data://tv/EpisodePosters%s', $episodeInfo['still_path']);
			File::deleteFromAbstractedPath($path);

			$replaceMessage = '[IMG]' . $this->getEpisodeImageUrl(($episodeInfo['still_path'] ?: '/no-poster.jpg')) . '[/IMG]' . "\r\n";
			$withReplacement = "[img]" . $episodePost->getEpisodeImageUrl(($episodeInfo['still_path'] ? '' : '/no-poster.jpg')) . "[/img]" . "\r\n";

			if ($episodeInfo['still_path'])
			{
				$post = $thread->FirstPost;
				$post->message = str_ireplace($replaceMessage, $withReplacement, $post->message);
				$post->save();
			}
		}

		// MOVED HERE FOR COMPATIBLITY ISSUES WITH XenPorta REDIRECTING IN THE FUNCTION

		$this->finalizeThreadCreate($creator);

		if ($switches['inline-mode'])
		{
			$viewParams = ['thread' => $thread, 'forum' => $forum];
			return $this->view('XF:Forum\ThreadItem', 'thread_list_item', $viewParams);
		}
		else if (!$thread->canView())
		{
			return $this->redirect($this->buildLink('forums', $forum, ['pending_approval' => 1]));
		}

		return $this->redirect($this->buildLink('threads', $thread));
	}

	protected function getForumViewExtraWith()
	{
		$extraWith = parent::getForumViewExtraWith();
		$extraWith[] = 'TVForum';

		return $extraWith;
	}

	protected function getAvailableForumSorts(\XF\Entity\Forum $forum)
	{
		$sorts = parent::getAvailableForumSorts($forum);
		$sorts += [
			'TV.tv_director' => 'TV.tv_director',
			'TV.tv_release' => 'TV.tv_release',
			'TV.tv_rating' => 'TV.tv_rating',
			'TV.tv_genres' => 'TV.tv_genres',
		];

		return $sorts;
	}

	protected function getForumFilterInput(\XF\Entity\Forum $forum)
	{
		$filters = parent::getForumFilterInput($forum);

		$input = $this->filter([
			'tvgenre' => 'str',
			'tvcast' => 'str',
			'creator' => 'str',
			'tv_title' => 'str'
		]);

		if ($input['tvgenre'])
		{
			$filters['tvgenre'] = $input['tvgenre'];
		}
		if ($input['tvcast'])
		{
			$filters['tvcast'] = $input['tvcast'];
		}
		if ($input['creator'])
		{
			$filters['creator'] = $input['creator'];
		}
		if ($input['tv_title'])
		{
			$filters['tv_title'] = $input['tv_title'];
		}

		return $filters;
	}

	protected function applyForumFilters(\XF\Entity\Forum $forum, \XF\Finder\Thread $threadFinder, array $filters)
	{
		if (!empty($filters['tvgenre']))
		{
			$threadFinder->where('TV.tv_genres', 'LIKE', $threadFinder->escapeLike($filters['tvgenre'], '%?%'));
		}

		if (!empty($filters['tvcast']))
		{
			$threadFinder->where('TV.tv_cast', 'LIKE', $threadFinder->escapeLike($filters['tvcast'], '%?%'));
		}

		if (!empty($filters['creator']))
		{
			$threadFinder->where('TV.tv_director', 'LIKE', $threadFinder->escapeLike($filters['creator'], '%?%'));
		}

		if (!empty($filters['tv_title']))
		{
			$threadFinder->where('TV.tv_title', 'LIKE', $threadFinder->escapeLike($filters['tv_title'], '%?%'));
		}

		parent::applyForumFilters($forum, $threadFinder, $filters);
	}

	protected function getTvImage($srcPath, $size, $localPath, $tempPath)
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
	}

	public function getTVImageUrl($posterpath, $canonical = true)
	{
		return $this->app->applyExternalDataUrl("tv/LargePosters{$posterpath}", $canonical);
	}

	public function getEpisodeImageUrl($posterpath, $canonical = true)
	{
		return $this->app->applyExternalDataUrl("tv/EpisodePosters{$posterpath}", $canonical);
	}
}