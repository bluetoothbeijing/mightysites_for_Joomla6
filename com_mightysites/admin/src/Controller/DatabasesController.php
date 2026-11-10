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
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;

/**
 * Data sources list controller.
 */
class DatabasesController extends AdminController
{
	protected $text_prefix = 'COM_MIGHTYSITES_DATABASES';

	protected $view_list = 'databases';

	public function getModel($name = 'Database', $prefix = 'Administrator', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, $config);
	}

	public function remove()
	{
		// Access check.
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.delete', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$app = Factory::getApplication();
		$ids = $app->input->get('cid', array(), 'array');
		$ids = array_map('intval', $ids);
		$n   = count($ids);
		$delete_tables = $app->input->getString('delete_tables');

		if ($n)
		{
			$n = 0;

			foreach ($ids as $id)
			{
				$site = MightysitesHelper::getSite($id);

				// Only databases!
				if ($site && $site->type == 2)
				{
					// Delete Tables & Views
					if ($delete_tables === 'true')
					{
						try
						{
							$db2 = MightysitesHelper::getDBO($site);

							$tables = MightysitesHelper::getTables($db2);

							if (count($tables))
							{
								foreach ($tables as $table)
								{
									if (strpos($table, $site->dbprefix) === 0)
									{
										$db2->setQuery('DROP TABLE `' . $table . '`');

										try
										{
											$db2->execute();
										}
										catch (\Throwable $e)
										{
											$app->enqueueMessage($e->getMessage(), 'error');
										}
									}
								}
							}

							$views = MightysitesHelper::getViews($db2);

							if (count($views))
							{
								foreach ($views as $view)
								{
									if (strpos($view, $site->dbprefix) === 0)
									{
										$db2->setQuery('DROP VIEW `' . $view . '`');

										try
										{
											$db2->execute();
										}
										catch (\Throwable $e)
										{
											$app->enqueueMessage($e->getMessage(), 'error');
										}
									}
								}
							}
						}
						catch (\Throwable $e)
						{
							$app->enqueueMessage($e->getMessage(), 'error');
						}
					}

					// Delete in database
					$db    = Factory::getDbo();
					$query = 'DELETE FROM ' . $db->quoteName('#__mightysites') . ' WHERE ' . $db->quoteName('id') . ' = ' . (int) $id;
					$db->setQuery($query);
					$db->execute();
					$n++;
				}
			}
		}

		$mes = $n ? Text::_('COM_MIGHTYSITES_DATABASES_DELETED') : null;
		$this->setRedirect(Route::_('index.php?option=com_mightysites&view=databases', false), $mes);
	}
}
