<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * MightySites master display controller.
 */
class DisplayController extends BaseController
{
	/**
	 * The default view.
	 */
	protected $default_view = 'sites';

	public function display($cachable = false, $urlparams = false)
	{
		$app    = Factory::getApplication();
		$view   = $app->input->get('view', $this->default_view);
		$layout = $app->input->get('layout', 'default');
		$id     = $app->input->getInt('id');

		// Check for edit form.
		if ($view === 'site' && $layout === 'edit' && method_exists($this, 'checkEditId') && !$this->checkEditId('com_mightysites.edit.site', $id))
		{
			$this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
			$this->setMessage($this->getError(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_mightysites&view=sites', false));

			return false;
		}

		if ($view === 'database' && $layout === 'edit' && method_exists($this, 'checkEditId') && !$this->checkEditId('com_mightysites.edit.database', $id))
		{
			$this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
			$this->setMessage($this->getError(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_mightysites&view=databases', false));

			return false;
		}

		// Copy DB scheduled?
		$copyDB = $app->getSession()->get('mighty_copy');

		if ($copyDB)
		{
			$app->getSession()->set('mighty_copy', '');

			$app->getDocument()->addScriptDeclaration(
				'window.addEventListener("DOMContentLoaded", function(){ window.open(' . json_encode($copyDB) . ', "mightysites_copy", "width=800,height=440"); });'
			);
		}

		return parent::display();
	}
}
