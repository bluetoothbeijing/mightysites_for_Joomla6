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
 * Data source table.
 */
class DatabaseTable extends Table
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
		$array['type'] = 2;

		return parent::bind($array, $ignore);
	}

	public function check()
	{
		$db = Factory::getDbo();

		// Check tables prefix.
		if (!preg_match('/^[a-z0-9_]*$/', $this->dbprefix))
		{
			$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_INVALID_DBPREFIX', $this->dbprefix));
			return false;
		}

		// Check for unique table prefix.
		$query = $db->getQuery(true)
			->select('id')
			->from($db->quoteName('#__mightysites'))
			->where($db->quoteName('db') . ' = ' . $db->quote($this->db))
			->where($db->quoteName('dbprefix') . ' = ' . $db->quote($this->dbprefix));
		$db->setQuery($query, 0, 1);
		$id = $db->loadResult();

		if ($id && $id != $this->id)
		{
			$this->setError(Text::sprintf('COM_MIGHTYSITES_ERROR_DATABASE_EXISTS', $this->db, $this->dbprefix));
			return false;
		}

		return true;
	}
}
