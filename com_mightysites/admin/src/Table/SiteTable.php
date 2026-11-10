<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

/**
 * Site table.
 */
class SiteTable extends Table
{
	public function __construct($db)
	{
		parent::__construct('#__mightysites', 'id', $db);
	}

	public function bind($array, $ignore = '')
	{
		if (isset($array['params']) && is_array($array['params']))
		{
			$registry = new Registry();
			$registry->loadArray($array['params']);
			$array['params'] = (string) $registry;
		}

		// Force type!
		$array['type'] = 1;

		return parent::bind($array, $ignore);
	}

	public function check()
	{
		$db = Factory::getDbo();

		// Check tables prefix.
		if (!preg_match('/^[a-zA-Z0-9_]*$/', $this->dbprefix))
		{
			$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_INVALID_DBPREFIX', $this->dbprefix));
			return false;
		}

		// Check for domain.
		$query = $db->getQuery(true)
			->select('id')
			->from($db->quoteName('#__mightysites'))
			->where($db->quoteName('domain') . ' = ' . $db->quote($this->domain));
		$db->setQuery($query, 0, 1);
		$id = $db->loadResult();

		if ($id && $id != $this->id)
		{
			$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_DOMAIN_EXISTS', $this->domain));
			return false;
		}

		// Check for unique table prefix
		$query = $db->getQuery(true)
			->select('id')
			->from($db->quoteName('#__mightysites'))
			->where($db->quoteName('dbprefix') . ' LIKE ' . $db->quote('%' . str_replace('_', '\_', $this->dbprefix) . '%'));
		$db->setQuery($query, 0, 1);
		$id = $db->loadResult();

		if ($id && $id != $this->id)
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_NOTICE_PREFIX_EXISTS', $this->dbprefix), 'notice');
		}

		// Virtual subfolder via symlink
		if (strpos($this->domain, '/') !== false)
		{
			$parts = explode('/', $this->domain);
			array_shift($parts);

			$path     = implode('/', $parts);
			$file     = JPATH_SITE . '/' . $path;
			$symlink  = false;

			// New site
			if (!$this->id)
			{
				// Already exists?
				if (file_exists($file))
				{
					// Link?
					if (is_link($file))
					{
						Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_NOTICE_SYMLINK_EXISTS', $file), 'notice');
					}
					// File or folder?
					else
					{
						$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_SYMLINK_EXISTS', $file));
						return false;
					}
				}
				else
				{
					$symlink = true;
				}
			}
			// Current site
			else
			{
				// Absent link? Recreate!
				if (!file_exists($file))
				{
					$symlink = true;
				}
			}

			// Create symlink
			if ($symlink)
			{
				$result = @symlink(JPATH_SITE, $file);

				if (!$result)
				{
					$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_SYMLINK_FAILED', $file, JPATH_SITE));
					return false;
				}
			}
		}

		return true;
	}
}
