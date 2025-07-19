<?php

namespace Snog\TV\XF\Admin\Controller;

use XF\InputFilterer;
use XF\Mvc\FormAction;

class Forum extends XFCP_Forum
{
	protected function nodeAddEdit(\XF\Entity\Node $node)
	{
		$reply = parent::nodeAddEdit($node);

		if (in_array($node->node_id, $this->options()->TvThreads_forum) && $reply instanceof \XF\Mvc\Reply\View)
		{
			$tmdbApi = new \Snog\TV\Util\TmdbApi();
			$codes = $tmdbApi->getGenres();

			if ($errors = $tmdbApi->getErrors())
			{
				return $this->error($errors);
			}

			$availableGenres['All'] = 'All';

			foreach ($codes['genres'] as $genre)
			{
				$availableGenres[$genre['name']] = $genre['name'];
			}

			$reply->setParams(['availableGenres' => $availableGenres]);
		}

		return $reply;
	}

	protected function saveTypeData(FormAction $form, \XF\Entity\Node $node, \XF\Entity\AbstractNode $data)
	{
		if ($node->node_id !== NULL && in_array($node->node_id, $this->options()->TvThreads_forum))
		{
			$newNode = false;

			/** @var \Snog\TV\Entity\Node $tvNode */
			$tvNode = $this->finder('Snog\TV:Node')->where('node_id', $node->node_id)->fetchOne();

			if (!isset($tvNode->node_id))
			{
				/** @var \Snog\TV\Entity\Node $tvNode */
				$tvNode = $this->em()->create('Snog\TV:Node');
				$newNode = true;
			}

			$input = $this->filter(['available_genres' => 'array']);
			if ($newNode) $tvNode->node_id = $node->node_id;
			$tvNode->tv_genre = $input['available_genres'];
			$tvNode->save();
		}

		parent::saveTypeData($form, $node, $data);
	}
}