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
use Joomla\Filesystem\Folder;

/**
 * YooTheme template style select list.
 */
class YoothemestyleField extends GroupedlistField
{
	protected $type = 'Yoothemestyle';

	protected function getGroups()
	{
		$groups = array();

		// Add Module Style Field
		$groups[][] = HTMLHelper::_('select.option', '', Text::_('JOPTION_USE_DEFAULT'));

		foreach ($this->getTemplates() as $template => $data)
		{
			$group = ucfirst(substr($template, 4));

			// Warp 7 (styles folder)
			if (is_dir(JPATH_SITE . '/templates/' . $template . '/styles'))
			{
				$styles_path = JPATH_SITE . '/templates/' . $template . '/styles';

				$groups[$group] = array();

				foreach (Folder::folders($styles_path) as $style)
				{
					$groups[$group][] = HTMLHelper::_('select.option', '_style.' . $template . '.' . $style, $group . ' - ' . ucfirst($style));
				}
			}
		}

		return $groups;
	}

	protected function getTemplates()
	{
		$db = Factory::getDbo();

		$query = $db->getQuery(true)
			->select(array('element', 'name', 'enabled'))
			->from($db->quoteName('#__extensions'))
			->where('client_id = 0')
			->where($db->quoteName('type') . ' = ' . $db->quote('template'))
			->where($db->quoteName('name') . ' LIKE ' . $db->quote('yoo_%'));

		$db->setQuery($query);

		return $db->loadObjectList('element');
	}
}
