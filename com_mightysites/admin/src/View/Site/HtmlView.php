<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\View\Site;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Site edit view.
 */
class HtmlView extends BaseHtmlView
{
	public $item;
	public $form;
	public $state;
	public $params;

	public function display($tpl = null)
	{
		$this->state  = $this->get('State');
		$this->item   = $this->get('Item');
		$this->form   = $this->get('Form');

		$this->params = new Registry($this->item->params);

		// Load tables
		if ($this->getLayout() === 'tables')
		{
			$this->tables = $this->getTables();
		}
		else
		{
			$this->synchs_core  = $this->getSynchs('core');
			$this->synchs_custom = $this->getSynchs('custom');
		}

		if ($this->getLayout() !== 'tables')
		{
			$this->addToolbar();
		}

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user   = Factory::getApplication()->getIdentity();
		$isNew  = ($this->item->id == 0);

		ToolbarHelper::title(Text::_($isNew ? 'COM_MIGHTYSITES_TITLE_SITE_ADD' : 'COM_MIGHTYSITES_TITLE_SITE_EDIT'), 'generic');

		if ($user->authorise('core.edit', 'com_mightysites'))
		{
			ToolbarHelper::apply('site.apply');
			ToolbarHelper::save('site.save');
			ToolbarHelper::save2new('site.save2new');
		}

		if (empty($this->item->id))
		{
			ToolbarHelper::cancel('site.cancel');
		}
		else
		{
			ToolbarHelper::cancel('site.cancel', 'JTOOLBAR_CLOSE');
		}
	}

	protected function getTables()
	{
		$tables = array();

		if ($this->item->id)
		{
			try
			{
				$db = MightysitesHelper::getDBO($this->item);

				if ($db && $db->connected())
				{
					// We display both Tables & Views
					foreach ($db->getTableList() as $table)
					{
						if (strpos($table, $this->item->dbprefix) === 0)
						{
							$name = substr($table, strlen($this->item->dbprefix));

							$tables[$name] = MightysitesHelper::sitesList(
								'jform[tables][' . $name . ']',
								$this->params->get('table_' . $name),
								null,
								$this->item->domain,
								Text::_('COM_MIGHTYSITES_OWN_DATA')
							);
						}
					}
				}
			}
			catch (\Throwable $e)
			{
				Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
			}
		}

		return $tables;
	}

	// Synchs
	protected function getSynchs($type)
	{
		$synchs = array();

		foreach (MightysitesHelper::getSynchs($type) as $synch)
		{
			// New code
			$content = $this->params->get('content');

			if (is_object($content) || is_array($content))
			{
				$content = (array) $content;
				$value   = $content[$synch] ?? '';
			}
			// old legacy
			else
			{
				$value = $this->params->get($synch);
			}

			$synchs[$synch] = MightysitesHelper::sitesList(
				'jform[content][' . $synch . ']',
				$value,
				null,
				$this->item->id,
				Text::_('COM_MIGHTYSITES_OWN_DATA')
			);
		}

		return $synchs;
	}
}
