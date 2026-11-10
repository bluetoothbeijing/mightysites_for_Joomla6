<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Model;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Registry\Registry;

/**
 * Sites list model.
 */
class SitesModel extends ListModel
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'domain', 'a.domain',
				'db', 'a.db',
				'dbprefix', 'a.dbprefix',
			);
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = null, $direction = null)
	{
		// Load the filter state.
		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);

		// Load the parameters.
		$params = ComponentHelper::getParams('com_mightysites');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('a.id', 'asc');
	}

	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');

		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState('list.select', 'a.*')
		);
		$query->from($db->quoteName('#__mightysites', 'a'));

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON uc.id = a.checked_out');

		// Filter by type.
		$query->where('a.type = 1');

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->quote('%' . $db->escape($search, true) . '%');
				$query->where('(a.domain LIKE ' . $search . ' OR a.db LIKE ' . $search . ' OR a.dbprefix LIKE ' . $search . ')');
			}
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');
		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}

	public function getItems()
	{
		$items = parent::getItems();

		if ($items)
		{
			$missing = 0;

			foreach ($items as &$item)
			{
				// Init params
				$item->params = new Registry($item->params);

				// Add config. Quiet mode: the list must never spam one error
				// message per site - missing files are flagged per row and
				// summarised once below.
				MightysitesHelper::attachConfig($item, null, true);

				if (!empty($item->config_missing))
				{
					$missing++;
				}
			}

			if ($missing)
			{
				Factory::getApplication()->enqueueMessage(
					Text::sprintf('COM_MIGHTYSITES_MISSING_CONFIGS', $missing),
					'warning'
				);
			}
		}

		return $items;
	}
}
