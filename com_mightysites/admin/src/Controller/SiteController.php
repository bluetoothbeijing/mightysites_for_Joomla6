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
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;

/**
 * Single site edit controller.
 */
class SiteController extends FormController
{
	public function getModel($name = 'Site', $prefix = 'Administrator', $config = array('ignore_request' => false))
	{
		return parent::getModel($name, $prefix, $config);
	}

	public function add()
	{
		if (!is_writable(JPATH_SITE))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_ROOT_NOT_WRITABLE', JPATH_SITE), 'error');
			$this->setRedirect('index.php?option=com_mightysites');

			return;
		}

		return parent::add();
	}

	/**
	 * Login to the administration of another site (via token file).
	 */
	public function login()
	{
		// Access check.
		$user = $this->app->getIdentity();

		if (!$user->authorise('core.edit', 'com_mightysites'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$app    = Factory::getApplication();
		$return = base64_decode($app->input->getBase64('return', '')) ?: 'index.php';
		$domain = base64_decode($app->input->getBase64('domain', ''));

		$to = MightysitesHelper::getSite($domain, true);

		if (!$to || !isset($to->id))
		{
			throw new \Exception(Text::_('COM_MIGHTYSITES_INVALID_SOURCE'), 500);
		}

		$folder = isset($to->tmp_path) && $to->tmp_path ? $to->tmp_path : $app->get('tmp_path');

		$token = md5(uniqid(mt_rand(), true));
		$fname = $folder . '/' . md5($token . ($to->secret ?? '')) . '.mighty';

		$data = serialize(array(
			'user_id'  => $user->id,
			'username' => $user->username,
			'return'   => $return,
		));

		if (!File::write($fname, $data))
		{
			echo '<h1>', Text::_('COM_MIGHTYSITES_ERROR'), '</h1>';
			echo '<p>', Text::sprintf('COM_MIGHTYSITES_CANT_WRITE_FILE', $fname), '</p>';
			jexit();
		}

		// AdminTools token.
		$admintools_token = $to->params->get('admintools_token');

		$scheme = Uri::getInstance()->getScheme() === 'https' ? 'https' : 'http';
		$link   = $scheme . '://' . $domain . '/administrator/index.php?' . ($admintools_token ? $admintools_token . '&' : '') . 'mighty_token=' . $token;

		$this->setRedirect($link);
	}
}
