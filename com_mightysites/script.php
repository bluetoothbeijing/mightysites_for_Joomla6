<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 *
 * Installer script for Joomla 6 port.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

class com_MightysitesInstallerScript extends InstallerScript
{
	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	protected $minimumPhp = '8.1';

	/**
	 * Minimum Joomla version.
	 *
	 * @var string
	 */
	protected $minimumJoomla = '5.0';

	public function postflight($type, $parent)
	{
		$app = Factory::getApplication();
		$db  = Factory::getDbo();

		// Make sure the per-domain configuration folder exists.
		$dir = JPATH_SITE . '/components/com_mightysites/configuration';

		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
		}

		// Load our language
		Factory::getLanguage()->load('com_mightysites', JPATH_ADMINISTRATOR . '/components/com_mightysites');

		// Create current site record
		$domain = $this->getHost();

		$query = 'INSERT IGNORE INTO ' . $db->quoteName('#__mightysites')
			. ' (' . $db->quoteName('id') . ', ' . $db->quoteName('type') . ', ' . $db->quoteName('domain') . ')'
			. ' VALUES(1, 1, ' . $db->quote($domain) . ')';
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (\Exception $e)
		{
			// Ignore, can exist already.
		}

		// Upgrade old tables (Joomla 4+ strict mode: no invalid datetime defaults).
		$this->upgradeSchema($db);

		// Create new per-domain config if not exists.
		require_once JPATH_ADMINISTRATOR . '/components/com_mightysites/src/Helper/MightysitesHelper.php';

		$fname = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getConfigFilename();

		if (!file_exists($fname))
		{
			$config = @file_get_contents(JPATH_CONFIGURATION . '/configuration.php');

			if ($config && strpos($config, 'class JConfig') !== false && File::write($fname, $config))
			{
				@chmod($fname, 0444);
			}
			else
			{
				// Tell the user clearly instead of failing silently.
				$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_CONFIG_WRITE_FAILED', $fname), 'error');
				$app->enqueueMessage('Check write permissions on: ' . dirname($fname), 'error');

				return;
			}
		}

		// Patch configuration.php!
		\AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::patchConfiguration();

		// Move old config files from site root into our configuration folder.
		foreach (\AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getSites() as $site)
		{
			$prepared = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::prepareDomain($site->domain);
			$old      = JPATH_SITE . '/configuration_' . $prepared . '.php';
			$new      = JPATH_SITE . '/components/com_mightysites/configuration/configuration_' . $prepared . '.php';

			if (file_exists($old) && !file_exists($new) && is_writable(dirname($new)))
			{
				File::move($old, $new);
			}
		}

		return true;
	}

	public function uninstall($parent)
	{
		$db = Factory::getDbo();

		require_once JPATH_ADMINISTRATOR . '/components/com_mightysites/src/Helper/MightysitesHelper.php';

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__mightysites'))
			->where($db->quoteName('id') . ' = 1');
		$db->setQuery($query, 0, 1);
		$root = $db->loadObject();

		if ($root)
		{
			$fname = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getConfigFilename($root->domain);

			if (file_exists($fname))
			{
				$new_config = @file_get_contents($fname);
				$old_config = @file_get_contents(JPATH_CONFIGURATION . '/configuration.php');

				if ($new_config && $old_config && strpos($old_config, 'class JConfig') === false)
				{
					if (File::write(JPATH_CONFIGURATION . '/configuration.php', $new_config))
					{
						return true;
					}
				}
			}

			Factory::getApplication()->enqueueMessage('Please re-save Global configuration!', 'error');
		}

		return true;
	}

	/**
	 * Fix legacy schema (datetime defaults etc).
	 */
	protected function upgradeSchema($db)
	{
		// Make checked_out_time nullable (Joomla strict mode forbids 0000-00-00).
		try
		{
			$db->setQuery('ALTER TABLE ' . $db->quoteName('#__mightysites')
				. ' MODIFY ' . $db->quoteName('checked_out_time') . ' datetime NULL DEFAULT NULL');
			$db->execute();
		}
		catch (\Exception $e)
		{
			// Ignore.
		}

		try
		{
			$db->setQuery('UPDATE ' . $db->quoteName('#__mightysites')
				. ' SET ' . $db->quoteName('checked_out_time') . ' = NULL WHERE ' . $db->quoteName('checked_out_time') . ' = ' . $db->quote('0000-00-00 00:00:00'));
			$db->execute();
		}
		catch (\Exception $e)
		{
			// Ignore.
		}

		// Add aliases column if upgrading from very old versions.
		try
		{
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__mightysites'));
			$db->setQuery($query, 0, 1);
			$columns = $db->loadObject();

			if ($columns && !property_exists($columns, 'aliases'))
			{
				$db->setQuery('ALTER TABLE ' . $db->quoteName('#__mightysites')
					. ' ADD ' . $db->quoteName('aliases') . ' TEXT NULL');
				$db->execute();
			}
		}
		catch (\Exception $e)
		{
			// Ignore.
		}
	}

	protected function getHost()
	{
		$domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
		$domain = (substr($domain, 0, 4) === 'www.') ? substr($domain, 4) : $domain;

		if (strpos($domain, ':') !== false)
		{
			list($domain, $tmp) = explode(':', $domain);
		}

		return $domain;
	}
}
