<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface
{
	/**
	 * Registers the services.
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(
			new MVCFactory('\\AlterBrains\\Component\\MightySites')
		);

		$container->registerServiceProvider(
			new ComponentDispatcherFactory('\\AlterBrains\\Component\\MightySites')
		);

		$container->set(
			ComponentInterface::class,
			static function (Container $container) {
				$component = new MVCComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
