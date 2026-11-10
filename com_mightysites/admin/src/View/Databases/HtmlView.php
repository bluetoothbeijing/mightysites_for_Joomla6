<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\View\Databases;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Data sources list view.
 */
class HtmlView extends BaseHtmlView
{
	public $items;
	public $state;
	public $pagination;
	public $filterForm;
	public $activeFilters;

	public function display($tpl = null)
	{
		$this->state         = $this->get('State');
		$this->items         = $this->get('Items');
		$this->pagination    = $this->get('Pagination');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');

		MightysitesHelper::topMenu();

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_MIGHTYSITES_TITLE_DATABASES'), 'database');

		$user = Factory::getApplication()->getIdentity();

		if ($user->authorise('core.create', 'com_mightysites'))
		{
			ToolbarHelper::addNew('database.add');
		}

		if ($user->authorise('core.edit', 'com_mightysites'))
		{
			ToolbarHelper::editList('database.edit');
		}

		if ($user->authorise('core.delete', 'com_mightysites'))
		{
			ToolbarHelper::deleteList(Text::_('COM_MIGHTYSITES_REALLY_DELETE_DATABASES'), 'databases.remove');
		}
	}
}
