<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;

/**
 * Sites list controller.
 */
class SitesController extends AdminController
{
	protected $text_prefix = 'COM_MIGHTYSITES_SITES';

	protected $view_list = 'sites';

	public function getModel($name = 'Site', $prefix = 'Administrator', $config = array('ignore_request' => true))
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

		$app  = Factory::getApplication();
		$ids  = $app->input->get('cid', array(), 'array');
		$ids  = array_map('intval', $ids);
		$n    = count($ids);
		$delete_tables = $app->input->getString('delete_tables');

		if ($n)
		{
			$n = 0;

			foreach ($ids as $id)
			{
				$site = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getSite($id, true);

				if (!$site)
				{
					continue;
				}

				$config_domain = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::prepareDomain($site->domain);

				if ($config_domain)
				{
					// Don't delete ourself :)
					if ($site->id == 1 && \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::prepareDomain() === $config_domain)
					{
						$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_NOT_DELETE_CURRENT', $site->domain), 'notice');
					}
					else
					{
						if ($site->type == 1)
						{
							// Delete Tables & Views
							if ($delete_tables === 'true')
							{
								try
								{
									$db2 = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getDBO($site);

									$tables = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getTables($db2);

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

									$views = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getViews($db2);

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

							// Delete config
							$fname = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getConfigFilename($config_domain);

							if (file_exists($fname))
							{
								@chmod($fname, 0644);

								if (!\Joomla\Filesystem\File::delete($fname))
								{
									$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_CANT_DELETE_CONFIG', $fname), 'error');
								}
							}

							// Delete symlink
							if (strpos($site->domain, '/') !== false)
							{
								$parts = explode('/', $site->domain);
								array_shift($parts);

								$path = implode('/', $parts);
								$file = JPATH_SITE . '/' . $path;

								if (file_exists($file) && is_link($file) && !@unlink($file))
								{
									$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_SYMLINK_DELETE', $file), 'error');
								}
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
		}

		$mes = $n ? Text::_('COM_MIGHTYSITES_DOMAINS_DELETED') : null;
		$this->setRedirect(Route::_('index.php?option=com_mightysites&view=sites', false), $mes);
	}

	/**
	 * Rebuild missing per-domain configuration files from the site rows.
	 */
	public function rebuildConfigs()
	{
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.edit', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->checkToken();

		$result = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::rebuildMissingConfigs();
		$app    = Factory::getApplication();

		if ($result['ok'])
		{
			$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_REBUILD_OK', $result['ok']), 'success');
		}

		if ($result['failed'])
		{
			$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_REBUILD_FAILED', implode(', ', $result['failed'])), 'error');
		}

		if (!$result['ok'] && !$result['failed'])
		{
			$app->enqueueMessage(Text::_('COM_MIGHTYSITES_REBUILD_NOTHING'), 'notice');
		}

		$this->setRedirect(Route::_('index.php?option=com_mightysites&view=sites', false));
	}

	/**
	 * Online/Offline toggle: changes $offline in the per-domain config file.
	 */
	public function publish()
	{
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.edit.state', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$app     = Factory::getApplication();
		$ids     = $app->input->get('cid', array(), 'array');
		$ids     = array_map('intval', $ids);
		$task    = $app->input->get('task');
		$publish = ($task === 'publish');

		if (empty($ids))
		{
			throw new \Exception(Text::_('COM_MIGHTYSITES_NO_ITEMS'), 500);
		}

		foreach ($ids as $id)
		{
			$config_site = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getSite($id);

			if ($config_site && $config_site->type == 1 && isset($config_site->domain))
			{
				$file = \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::getConfigFilename($config_site->domain);

				if (!file_exists($file))
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_FILE_DOESNT_EXIST', $file), 'error');
					continue;
				}

				$config = @file_get_contents($file);

				if ($config === false)
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_FILE_NOT_READABLE', $file), 'error');
					continue;
				}

				if ($publish)
				{
					$config = preg_replace('#public \$offline = \'1\';#u', 'public $offline = \'0\';', $config);
				}
				else
				{
					$config = preg_replace('#public \$offline = \'0\';#u', 'public $offline = \'1\';', $config);
				}

				@chmod($file, 0644);

				if (!\Joomla\Filesystem\File::write($file, $config))
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_CANT_WRITE_FILE', $file), 'error');
					continue;
				}

				@chmod($file, 0444);

				$mes = $publish ? 'COM_MIGHTYSITES_SITE_PUBLISHED' : 'COM_MIGHTYSITES_SITE_UNPUBLISHED';
				$app->enqueueMessage(Text::sprintf($mes, $config_site->domain));
			}
		}

		$this->setRedirect(Route::_('index.php?option=com_mightysites&view=sites', false));
	}
}
