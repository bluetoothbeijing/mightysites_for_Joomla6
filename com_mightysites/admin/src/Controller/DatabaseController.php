<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Controller;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Data source edit controller.
 */
class DatabaseController extends FormController
{
	public function getModel($name = 'Database', $prefix = 'Administrator', $config = array('ignore_request' => false))
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * AJAX: create a new MySQL database.
	 */
	public function create()
	{
		// Access check.
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.create', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$app = Factory::getApplication();
		$db  = Factory::getDbo();

		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		// Get vars
		$dbname = $app->input->getString('db');

		try
		{
			$db->setQuery('CREATE DATABASE `' . str_replace('`', '', $dbname) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
			$db->execute();

			echo '<span style="color:green">', Text::sprintf('COM_MIGHTYSITES_DATABASE_CREATED', $dbname), '</span>';
		}
		catch (\Throwable $e)
		{
			echo '<span style="color:red">', Text::sprintf('COM_MIGHTYSITES_ERROR_CUSTOM', $e->getMessage()), '</span>';
		}

		$app->close();
	}

	/**
	 * AJAX: check a database connection.
	 */
	public function check()
	{
		$app = Factory::getApplication();

		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		// Get vars
		$db       = $app->input->getString('db');
		$dbprefix = $app->input->getString('dbprefix');
		$user     = $app->input->getString('user');
		$password = $app->input->getString('password');
		$id       = $app->input->getInt('id');
		$type     = $app->input->getInt('type');

		// Use current?
		if (empty($user))
		{
			$user     = $app->get('user');
			$password = $app->get('password');
		}

		// Check connection
		$link = @\mysqli_connect($app->get('host'), $user, $password);

		if ($link === false)
		{
			echo '<span style="color:red">', Text::_('COM_MIGHTYSITES_ERROR_INVALID_USER'), '</span>';
		}
		else
		{
			if (!\mysqli_select_db($link, $db))
			{
				echo '<span style="color:red">', Text::sprintf('COM_MIGHTYSITES_ERROR_CUSTOM', \mysqli_error($link)), '</span>';
			}
			else
			{
				if ($type == 1)
				{
					if ($id)
					{
						echo '<span style="color:green">', Text::_('COM_MIGHTYSITES_CONNECTED'), '</span>';
					}
					else
					{
						if (@\mysqli_query($link, 'SELECT COUNT(*) FROM ' . $dbprefix . 'users'))
						{
							echo '<span style="color:#f0ad4e">', Text::sprintf('COM_MIGHTYSITES_WARNING_TABLES_EXIST', $dbprefix, $db), '</span>';
						}
						else
						{
							echo '<span style="color:green">', Text::_('COM_MIGHTYSITES_CONNECTED'), '</span>';
						}
					}
				}
				elseif ($type == 2)
				{
					if (!@\mysqli_query($link, 'SELECT COUNT(*) FROM ' . $dbprefix . 'users'))
					{
						echo '<span style="color:red">', Text::sprintf('COM_MIGHTYSITES_ERROR_NO_TABLES_EXIST', $dbprefix, $db), '</span>';
					}
					else
					{
						echo '<span style="color:green">', Text::_('COM_MIGHTYSITES_CONNECTED'), '</span>';
					}
				}

				mysqli_close($link);
			}
		}

		$app->close();
	}

	/**
	 * Copy tables from one site/database to another (step by step).
	 */
	public function copy()
	{
		$app = Factory::getApplication();

		// Access check.
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.create', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$from  = $app->input->getCmd('from');
		$to    = $app->input->getCmd('to');
		$table = $app->input->getInt('table', 0);
		$tmpl  = $app->input->getCmd('tmpl');

		// Get source site
		$from = MightysitesHelper::getSite($from, true);

		if (!isset($from->id))
		{
			throw new \Exception(Text::_('COM_MIGHTYSITES_INVALID_SOURCE'), 500);
		}

		// Get destination site
		$to = MightysitesHelper::getSite($to, true);

		// Check
		if (($from->db == $to->db) && ($from->dbprefix == $to->dbprefix))
		{
			$this->setRedirect(Route::_('index.php?option=com_mightysites', false), Text::_('COM_MIGHTYSITES_SOURCE_DEST_EQUAL'));
			return;
		}

		// Get Dbo
		$db = MightysitesHelper::getDBO($from);

		// Get tables & views list from site.
		$tables = MightysitesHelper::getTables($db);
		$views  = MightysitesHelper::getViews($db);

		// Main array, tables are copied first because they are used in views.
		$tables_views = array_merge($tables, $views);

		// Filter tables without source prefix
		if (count($tables_views))
		{
			foreach ($tables_views as $key => $value)
			{
				if (strpos($value, $from->dbprefix) !== 0)
				{
					unset($tables_views[$key]);
				}

				// Filter dest tables if we use same db
				if ($from->db == $to->db && strpos($value, $to->dbprefix) === 0)
				{
					unset($tables_views[$key]);
				}
			}

			// Rebuild keys
			$tables_views = explode(',,,', implode(',,,', $tables_views));
		}

		// Remember views code.
		$views_code = array();

		foreach ($tables_views as $tables_view)
		{
			if (in_array($tables_view, $views))
			{
				$db->setQuery('SHOW CREATE VIEW `' . $tables_view . '`');
				$vres = $db->loadAssoc();

				if (isset($vres['Create View']))
				{
					$views_code[$tables_view] = $vres['Create View'];
				}
			}
		}

		// Check tables
		if (!count($tables_views))
		{
			throw new \Exception(Text::sprintf('COM_MIGHTYSITES_NO_TABLES', $from->domain, $from->db), 500);
		}

		// source
		$source_table = $tables_views[$table];

		// destination
		$dest_table = $to->dbprefix . substr($source_table, strlen($from->dbprefix));

		// Table or View?
		$isTable = in_array($source_table, $tables);
		$isView  = in_array($source_table, $views);

		// Connect to dest DB
		$db2 = MightysitesHelper::getDBO($to);

		echo '<h1>', Text::sprintf('COM_MIGHTYSITES_CREATING_TABLE', ($table + 1), count($tables_views), $dest_table), '</h1>';
		echo '<h3>', Text::sprintf('COM_MIGHTYSITES_SOURCE_DATABASE', $from->db, $from->dbprefix), '</h3>';
		echo '<h3>', Text::sprintf('COM_MIGHTYSITES_DEST_DATABASE', $to->db, $to->dbprefix), '</h3>';
		echo '<h3>', Text::_('COM_MIGHTYSITES_PLEASE_WAIT'), '</h3>';

		$error = false;

		// Try to delete first
		if ($isTable)
		{
			$query = 'DROP TABLE IF EXISTS `' . $dest_table . '`';
		}
		elseif ($isView)
		{
			$query = 'DROP VIEW IF EXISTS `' . $dest_table . '`';
		}
		else
		{
			$query = null;
		}

		if ($query)
		{
			$db2->setQuery($query);

			try
			{
				$db2->execute();
			}
			catch (\Throwable $e)
			{
				die('<span style="color:red">' . $e->getMessage() . '</span>');
			}
		}

		// Create table or view
		if ($isTable)
		{
			$query = 'CREATE TABLE `' . $dest_table . '` LIKE `' . $from->db . '`.`' . $source_table . '`';
		}
		elseif ($isView)
		{
			if (isset($views_code[$source_table]))
			{
				$query = strtr($views_code[$source_table], array(
					'`' . $source_table . '`' => '`' . $dest_table . '`',
					'`' . $from->dbprefix     => '`' . $to->dbprefix,
				));
			}
			else
			{
				$query = 'SELECT 1';
			}
		}

		if (!empty($query))
		{
			$db2->setQuery($query);

			try
			{
				$db2->execute();
			}
			catch (\Throwable $e)
			{
				echo '<p style="color:red">' . $e->getMessage() . '</p>';
				$error = true;
			}
		}

		// Next copy data table
		if ($isTable && !$error)
		{
			$query = 'INSERT INTO `' . $dest_table . '` SELECT * FROM `' . $from->db . '`.`' . $source_table . '`';
			$db2->setQuery($query);

			try
			{
				$db2->execute();
			}
			catch (\Throwable $e)
			{
				echo '<span style="color:red">' . $e->getMessage() . '</span>';
				$error = true;
			}
		}

		$table++;

		// Finish
		if ($table >= count($tables_views))
		{
			if ($tmpl === 'component')
			{
				echo '<script type="text/javascript">window.parent.location.reload();</script>';
				echo '<p>', Text::sprintf('COM_MIGHTYSITES_TABLES_CREATED', $to->db, $to->dbprefix), '</p>';
				jexit();
			}
			else
			{
				$this->setRedirect(Route::_('index.php?option=com_mightysites&view=databases', false), Text::sprintf('COM_MIGHTYSITES_TABLES_CREATED', $to->db, $to->dbprefix));
				$this->redirect();
			}
		}
		// or Continue to next table
		else
		{
			$link = Route::_('index.php?option=com_mightysites&task=database.copy&from=' . $from->id . '&to=' . $to->id . '&table=' . $table . '&tmpl=' . $tmpl, false);

			// no errors - let's auto proceed
			if (!$error)
			{
				echo '<script type="text/javascript">document.location.href=' . json_encode($link) . ';</script>';
			}
			// errors! show them!
			else
			{
				echo '<a href="', $link, '">', Text::_('COM_MIGHTYSITES_CONTINUE'), '</a>';
			}

			jexit();
		}
	}
}
