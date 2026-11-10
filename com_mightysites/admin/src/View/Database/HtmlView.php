<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\View\Database;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Data source edit view.
 */
class HtmlView extends BaseHtmlView
{
	public $item;
	public $form;
	public $state;

	public function display($tpl = null)
	{
		$this->state = $this->get('State');
		$this->item  = $this->get('Item');
		$this->form  = $this->get('Form');

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user  = Factory::getApplication()->getIdentity();
		$isNew = ($this->item->id == 0);

		ToolbarHelper::title(Text::_($isNew ? 'COM_MIGHTYSITES_TITLE_DATABASE_ADD' : 'COM_MIGHTYSITES_TITLE_DATABASE_EDIT'), 'database');

		if ($user->authorise('core.edit', 'com_mightysites'))
		{
			ToolbarHelper::apply('database.apply');
			ToolbarHelper::save('database.save');
		}

		if (empty($this->item->id))
		{
			ToolbarHelper::cancel('database.cancel');
		}
		else
		{
			ToolbarHelper::cancel('database.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}
