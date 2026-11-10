<?php
/**
 * @package    MightySites (System plugin)
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class plgSystemMightysitesInstallerScript
{
	public function install($parent)
	{
		// Force our plugin to be ordered first, this is critical!
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('ordering') . ' = -99980')
			->where($db->quoteName('element') . ' = "mightysites"')
			->where($db->quoteName('type') . ' = "plugin"');
		$db->setQuery($query);
		$db->execute();

		Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_MIGHTYSITES_INSTALLED_OK'));
	}

	public function update($parent)
	{
		$this->install($parent);
	}
}
