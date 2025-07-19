<?php

namespace Snog\TV\Callbacks;

class TVShow
{
	public static function getShowName($contents, $params)
	{
		$finder = \XF::app()->finder('Snog\TV:TVForum');

		/** @var \Snog\TV\Entity\TVForum $show */
		$show = $finder->with('Parent')->where('tv_id', $params[0])->fetchOne();

		if ($show->Parent)
		{
			return $show->Parent->tv_title;
		}

		return $show->tv_title;
	}
}
