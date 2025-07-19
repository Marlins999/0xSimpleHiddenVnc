<?php

namespace Snog\TV\Option;

use XF\Option\AbstractOption;

class Forum extends AbstractOption
{
	public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams)
	{
		$data = self::getSelectData($option, $htmlParams);
		$data['controlOptions']['multiple'] = true;
		$data['controlOptions']['size'] = 8;

		return self::getTemplater()->formSelectRow(
			$data['controlOptions'], $data['choices'], $data['rowOptions']
		);
	}

	protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams)
	{
		/** @var \Snog\TV\Repository\Node $nodeRepo */
		$nodeRepo = \XF::repository('Snog\TV:Node');
		$choices = $nodeRepo->getNodeOptionsData(true, 'Forum', 'option');

		$choices = array_map(function ($v) {
			$v['label'] = \XF::escapeString($v['label']);
			return $v;
		}, $choices);

		return [
			'choices' => $choices,
			'controlOptions' => self::getControlOptions($option, $htmlParams),
			'rowOptions' => self::getRowOptions($option, $htmlParams)
		];
	}

	public static function verifyOption(array &$value, \XF\Entity\Option $option)
	{
		if (($key = array_search('0', $value)) !== false)
		{
			unset($value[$key]);
		}

		if (!empty($value))
		{
			$finder = \XF::app()->finder('Snog\TV:Node');

			/** @var \Snog\TV\Entity\Node[] $tvForums */
			$tvForums = $finder->fetch();

			foreach ($tvForums as $tvForum)
			{
				if (!in_array($tvForum->node_id, $value))
				{
					$tvForum->delete();
				}
			}
		}

		return true;
	}
}