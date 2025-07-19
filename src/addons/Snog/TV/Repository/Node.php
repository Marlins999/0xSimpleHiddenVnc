<?php

namespace Snog\TV\Repository;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;

class Node extends Repository
{
	public function getFullNodeList(\XF\Entity\Node $withinNode = null, $with = null)
	{
		/** @var \XF\Finder\Node $finder */
		$finder = $this->finder('XF:Node')->order('lft');

		if ($withinNode) $finder->descendantOf($withinNode);
		if ($with) $finder->with($with);
		return $finder->fetch();
	}

	public function loadNodeTypeDataForNodes($nodes)
	{
		$types = [];
		foreach ($nodes as $node)
		{
			$types[$node->node_type_id][$node->node_id] = $node->node_id;
		}

		$nodeTypes = $this->app()->container('nodeTypes');

		foreach ($types as $typeId => $nodeIds)
		{
			if (isset($nodeTypes[$typeId]))
			{
				$entityIdent = $nodeTypes[$typeId]['entity_identifier'];
				$entityClass = $this->em->getEntityClassName($entityIdent);
				$extraWith = $entityClass::getListedWith();
				$this->em->findByIds($entityIdent, $nodeIds, $extraWith);
			}
		}

		return $nodes;
	}

	public function filterViewable(AbstractCollection $nodes)
	{
		if (!$nodes->count()) return $nodes;
		\XF::visitor()->cacheNodePermissions();
		return $nodes->filterViewable();
	}

	public function getNodeOptionsData($includeEmpty = true, $enableTypes = null, $type = null, $checkPerms = false)
	{
		$choices = [];
		if ($includeEmpty) $choices = [0 => ['_type' => 'option', 'value' => 0, 'label' => '']];
		$nodeList = $this->getFullNodeList();

		if ($checkPerms)
		{
			$this->loadNodeTypeDataForNodes($nodeList);
			$nodeList = $nodeList->filterViewable();
		}

		foreach ($this->createNodeTree($nodeList)->getFlattened() as $entry)
		{
			/** @var \XF\Entity\Node $node */
			$node = $entry['record'];

			if (!isset($node->TVForum->node_id))
			{
				if ($entry['depth'])
				{
					$prefix = str_repeat('--', $entry['depth']) . ' ';
				}
				else
				{
					$prefix = '';
				}

				$choices[$node->node_id] = ['value' => $node->node_id, 'label' => $prefix . $node->title];

				if ($enableTypes !== null)
				{
					if (!is_array($enableTypes)) $enableTypes = [$enableTypes];
					$choices[$node->node_id]['disabled'] = in_array($node->node_type_id, $enableTypes) ? false : 'disabled';
				}

				if ($type !== null) $choices[$node->node_id]['_type'] = $type;
			}
		}

		return $choices;
	}


	public function createNodeTree($nodes, $rootId = 0)
	{
		return new \XF\Tree($nodes, 'parent_node_id', $rootId);
	}
}