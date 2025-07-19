<?php

namespace Snog\TV\Option;

use XF\Option\AbstractOption;

class UserGroup extends AbstractOption
{
	public static function renderCheckboxes(\XF\Entity\Option $option, array $htmlParams)
	{
		/** @var \XF\Repository\UserGroup $userGroupRepo */
		$userGroupRepo = \XF::repository('XF:UserGroup');
		$userGroups = $userGroupRepo->findUserGroupsForList()->fetch();
		$choices = [];

		/** @var \XF\Entity\UserGroup $group */
		foreach ($userGroups as $group)
		{
			$choices[$group->user_group_id] = $group->title;
		}

		return self::getCheckboxRow($option, $htmlParams, $choices);
	}
}