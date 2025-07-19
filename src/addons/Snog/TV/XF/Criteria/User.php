<?php

namespace Snog\TV\XF\Criteria;

class User extends XFCP_User
{
	protected function _matchTVPosted(array $data, \XF\Entity\User $user)
	{
		$app = \XF::app();
		$finder = $app->finder('XF:Thread');
		$tvs = $finder->where('user_id', $user->user_id)
			->where('TV.tv_id', '>', '')
			->where('discussion_type', '<>', 'redirect')
			->fetch();

		$count = $tvs->count();
		return ($count && $count >= $data['tv']);
	}
}