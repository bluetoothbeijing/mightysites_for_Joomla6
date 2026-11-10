<?php
/**
 * @package    MightySites (System plugin)
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface
{
	public function register(Container $container)
	{
		$container->set(
			\AlterBrains\Plugin\System\MightySites\Extension\MightySites::class,
			function (Container $container) {
				$plugin = new \AlterBrains\Plugin\System\MightySites\Extension\MightySites(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('system', 'mightysites')
				);

				try
				{
					$plugin->setApplication(Factory::getApplication());
				}
				catch (\Throwable $e)
				{
				}

				return $plugin;
			}
		);
	}
};
