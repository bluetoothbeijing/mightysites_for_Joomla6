<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Plugins select list (grouped by folder).
 */
class MightypluginsField extends GroupedlistField
{
	protected $type = 'MightyPlugins';

	protected function getGroups()
	{
		$groups = array();

		// Get list of plugins
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select(array('folder', 'element', 'name'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('folder') . ' != ""')
			->where($db->quoteName('element') . ' != "mightysites"')
			->where($db->quoteName('type') . ' = "plugin"')
			->order('folder, ordering, name');
		$db->setQuery($query);

		$plugins = $db->loadObjectList();

		$lang = Factory::getLanguage();

		foreach ($plugins as $item)
		{
			$source    = JPATH_PLUGINS . '/' . $item->folder . '/' . $item->element;
			$extension = 'plg_' . $item->folder . '_' . $item->element;

			$lang->load($extension . '.sys', JPATH_ADMINISTRATOR, null, false, true)
				|| $lang->load($extension . '.sys', $source, null, false, true);

			$group = ucfirst($item->folder);

			// Initialize the group if necessary.
			if (!isset($groups[$group]))
			{
				$groups[$group] = array();
			}

			$groups[$group][] = HTMLHelper::_('select.option', $item->folder . ':' . $item->element, Text::_($item->name), 'value', 'text', false);
		}

		// Merge any additional groups in the XML definition.
		return array_merge(parent::getGroups(), $groups);
	}
}
