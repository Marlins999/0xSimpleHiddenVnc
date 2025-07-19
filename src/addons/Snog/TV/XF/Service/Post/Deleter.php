<?php

namespace Snog\TV\XF\Service\Post;

use XF\Util\File;

class Deleter extends XFCP_Deleter
{
	/**
	 * @var \Snog\TV\XF\Entity\Post
	 */
	protected $post;

	public function delete($type, $reason = '')
	{
		/** @var \Snog\TV\XF\Entity\Thread $thread */
		$thread = $this->post->Thread;
		$db = \XF::db();

		if ($type == 'hard')
		{
			if ($this->post->isFirstPost())
			{
				$tvShow = $thread->TV;

				if ($tvShow)
				{
					$tvId = $tvShow->tv_id;
					$image = $tvShow->tv_image;
					$tvShow->delete();

					/** @var \Snog\TV\Entity\TV[] $shows */
					$shows = $this->finder('Snog\TV:TV')->where('tv_id', $tvId)->fetch();

					if ($shows)
					{
						foreach ($shows as $show)
						{
							/** @var \XF\Entity\Thread $originalThread */
							$originalThread = $this->em()->find('XF:Thread', $show->thread_id);
							if ($originalThread->discussion_type == 'redirect')
							{
								$show->delete();
							}
						}
					}

					if ($image)
					{
						$image = str_ireplace('/', '', $image);

						$path = sprintf('data://tv/LargePosters/%d-%s', $thread->thread_id, $image);
						File::deleteFromAbstractedPath($path);

						$path = sprintf('data://tv/SmallPosters/%d-%s', $thread->thread_id, $image);
						File::deleteFromAbstractedPath($path);
					}

					// EPISODE POSTS IN THREAD

					/** @var \Snog\TV\XF\Entity\Post[] $episodes */
					$episodes = $this->finder('XF:Post')
						->with('TVPost')
						->where('thread_id', $thread->thread_id)
						->where('TVPost.tv_id', '>', 0)
						->fetch();

					if ($episodes)
					{
						foreach ($episodes as $episode)
						{
							$episodeimage = str_ireplace('/', '', $episode->TVPost->tv_image);

							$path = sprintf('data://tv/EpisodePosters/%d-%s', $episode->post_id, $episodeimage);
							File::deleteFromAbstractedPath($path);

							$db->delete('xf_snog_tv_post', 'post_id = ' . $episode->post_id);
						}
					}
				}
			}
			else
			{
				$tvPost = $this->post->TVPost;

				if ($tvPost)
				{
					$image = $tvPost->tv_image;
					$tvPost->delete();

					if ($image)
					{
						$image = str_ireplace('/', '', $image);

						$path = sprintf('data://tv/EpisodePosters/%d-%s', $tvPost->post_id, $image);
						File::deleteFromAbstractedPath($path);
					}
				}
			}
		}

		return parent::delete($type, $reason);
	}
}