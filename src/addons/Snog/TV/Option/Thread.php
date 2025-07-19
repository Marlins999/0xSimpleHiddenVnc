<?php

namespace Snog\TV\Option;

use XF\Option\AbstractOption;

class Thread extends AbstractOption
{
	public static function renderCheckboxes(\XF\Entity\Option $option, array $htmlParams)
	{
		$choices[3] = \XF::phrase('snog_tv_genre');
		$choices[6] = \XF::phrase('snog_tv_first_aired');
		$choices[4] = \XF::phrase('snog_tv_creator');
		$choices[5] = \XF::phrase('snog_tv_cast');
		$choices[8] = \XF::phrase('snog_tv_overview');
		$choices[9] = \XF::phrase('snog_tv_trailer');

		return self::getCheckboxRow($option, $htmlParams, $choices);
	}

	public static function renderForumCheckboxes(\XF\Entity\Option $option, array $htmlParams)
	{
		$choices[1] = \XF::phrase('snog_tv_title');
		$choices[3] = \XF::phrase('snog_tv_genre');
		$choices[4] = \XF::phrase('snog_tv_creator');
		$choices[6] = \XF::phrase('snog_tv_first_aired');

		return self::getCheckboxRow($option, $htmlParams, $choices);
	}
}