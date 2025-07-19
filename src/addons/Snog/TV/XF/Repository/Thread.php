<?php

namespace Snog\TV\XF\Repository;

class Thread extends XFCP_Thread
{
	public function findThreadsForForumView(\XF\Entity\Forum $forum, array $limits = [])
	{
		$finder = parent::findThreadsForForumView($forum, $limits);
		if (in_array($forum->node_id, \XF::options()->TvThreads_forum))
		{
			$finder->with('TV');
		}

		return $finder;
	}
}