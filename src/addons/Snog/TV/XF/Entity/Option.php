<?php

namespace Snog\TV\XF\Entity;

class Option extends XFCP_Option
{
	protected function _postSave()
	{
		parent::_postSave();

		if ($this->option_id == 'TvThreads_sort' && $this->isChanged('option_value'))
		{
			// Get TV forums
			$tvNodes = $this->finder('Snog\TV:TVForum')->where('tv_parent', 0)->fetch();

			foreach ($tvNodes as $parentNode)
			{
				/** @var \XF\Entity\Node[] $tvNodes */
				$tvNodes = $this->finder('XF:Node')
					->where('parent_node_id', $parentNode->node_id)
					->order('TVForum.tv_season', $this->option_value)
					->fetch();

				$order = 100;

				foreach ($tvNodes as $node)
				{
					$node->display_order = $order;
					$node->save();
					$order = $order + 100;
				}
			}
		}
	}
}