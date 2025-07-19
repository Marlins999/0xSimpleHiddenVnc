<?php

namespace Snog\TV\XF\Service\Thread;

use XF\Util\File;

class Deleter extends XFCP_Deleter
{
	public function delete($type, $reason = '')
	{
		$db = \XF::db();

		if ($this->thread->discussion_type !== 'redirect' && $type == 'hard')
		{
			/** @var \Snog\TV\Entity\TV $tvShow */
			$tvShow = $this->em()->find('Snog\TV:TV', $this->thread->thread_id);

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

					$path = sprintf('data://tv/LargePosters/%d-%s', $this->thread->thread_id, $image);
					File::deleteFromAbstractedPath($path);

					$path = sprintf('data://tv/SmallPosters/%d-%s', $this->thread->thread_id, $image);
					File::deleteFromAbstractedPath($path);
				}

				// EPISODE POSTS IN THREAD

				/** @var \Snog\TV\XF\Entity\Post[] $episodes */
				$episodes = $this->finder('XF:Post')
					->with('TVPost')
					->where('thread_id', $this->thread->thread_id)
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

		return parent::delete($type, $reason);
	}
}