<?php

namespace Snog\TV\XF\Repository;

class Post extends XFCP_Post
{
	public function findPostsForThreadView(\XF\Entity\Thread $thread, array $limits = [])
	{
		$finder = parent::findPostsForThreadView($thread, $limits);
		$finder->with('TVPost');

		return $finder;
	}
}