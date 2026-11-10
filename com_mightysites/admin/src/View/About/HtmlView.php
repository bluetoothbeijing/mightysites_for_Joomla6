<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\View\About;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * About view.
 */
class HtmlView extends BaseHtmlView
{
	public function display($tpl = null)
	{
		ToolbarHelper::title(Text::_('COM_MIGHTYSITES_TITLE_ABOUT'), 'info');

		MightysitesHelper::topMenu();

		parent::display($tpl);
	}
}
