<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\View\Sites;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Sites list view.
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

		// Add some data.
		$return = base64_encode(Uri::getInstance()->toString());

		foreach ($this->items as &$item)
		{
			// Title
			if ($item->id == 1)
			{
				// Highlight master site.
				$item->title = '<b title="' . Text::_('COM_MIGHTYSITES_PRIMARY_DOMAIN') . '">' . $item->domain . '</b>';
			}
			else
			{
				$item->title = $item->domain;
			}

			// Links
			$item->link  = $this->_getLink($item, 'index.php?option=com_config');
			$item->link2 = $this->_getLink($item, 'index.php');

			// Infotip
			$item->contentTip = $this->_getContentInfo($item);
			$item->singleTip  = $this->_getSingleInfo($item);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_MIGHTYSITES_TITLE_SITES'), 'sitemap');

		$user = Factory::getApplication()->getIdentity();

		if ($user->authorise('core.create', 'com_mightysites'))
		{
			ToolbarHelper::addNew('site.add');
		}

		if ($user->authorise('core.edit', 'com_mightysites'))
		{
			ToolbarHelper::editList('site.edit');
			ToolbarHelper::custom('sites.rebuildConfigs', 'refresh', '', 'COM_MIGHTYSITES_REBUILD_CONFIGS', false);
		}

		if ($user->authorise('core.edit.state', 'com_mightysites'))
		{
			ToolbarHelper::publish('sites.publish', 'COM_MIGHTYSITES_ONLINE', true);
			ToolbarHelper::unpublish('sites.unpublish', 'COM_MIGHTYSITES_OFFLINE', true);
			ToolbarHelper::checkin('sites.checkin', true);
		}

		if ($user->authorise('core.delete', 'com_mightysites'))
		{
			ToolbarHelper::deleteList(Text::_('COM_MIGHTYSITES_REALLY_DELETE_SITES'), 'sites.remove');
		}

		if ($user->authorise('core.admin', 'com_mightysites'))
		{
			ToolbarHelper::preferences('com_mightysites');
		}
	}

	protected function _getContentInfo($row)
	{
		$content = $row->params->get('content');

		if (is_object($content) || is_array($content))
		{
			$content = new \Joomla\Registry\Registry($content);
		}
		else
		{
			$content = $row->params;
		}

		$i = 0;
		$s = '<table class="table table-sm">';

		foreach (MightysitesHelper::getSynchs() as $synch)
		{
			if ($content->get($synch))
			{
				$site = MightysitesHelper::getSite($content->get($synch));

				$s .= '<tr><td style="white-space:nowrap">' . Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $synch) . ':</td><td>' . ($site ? $site->domain : 'n/a') . '</td></tr>';
				$i++;
			}
		}

		$s2 = '';

		foreach ($row->params->toArray() as $key => $value)
		{
			if (strpos($key, 'table_') === 0)
			{
				$site = MightysitesHelper::getSite($value);

				$s2 .= '<tr><td style="white-space:nowrap">#__' . substr($key, 6) . ':</td><td>' . ($site ? $site->domain : 'n/a') . '</td></tr>';
				$i++;
			}
		}

		if ($s2)
		{
			$s .= '<tr><td colspan="2"><hr/></td></tr>' . $s2;
		}

		$s .= '</table>';

		return $i ? htmlspecialchars($s, ENT_COMPAT, 'UTF-8') : null;
	}

	protected function _getSingleInfo($row)
	{
		if (!empty($row->mighty_sdomains) && $row->mighty_sdomains)
		{
			$s  = '<table>';
			$s .= '<tr><td style="white-space:nowrap">' . Text::_('COM_MIGHTYSITES_FIELD_SINGLE_LOGIN') . ':</td><td>' . (!empty($row->mighty_slogin) ? Text::_('JENABLED') : Text::_('JDISABLED')) . '</td></tr>';
			$s .= '<tr><td style="white-space:nowrap">' . Text::_('COM_MIGHTYSITES_FIELD_SINGLE_LOGOUT') . ':</td><td>' . (!empty($row->mighty_slogout) ? Text::_('JENABLED') : Text::_('JDISABLED')) . '</td></tr>';
			$s .= '<tr><td colspan="2"><hr/></td></tr>';

			foreach ($row->mighty_sdomains as $key => $value)
			{
				$s .= '<tr><td style="white-space:nowrap">' . $key . '</td></tr>';
			}

			$s .= '</table>';

			return htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
		}

		return null;
	}

	protected function _getLink(&$row, $link)
	{
		if ($row->domain !== MightysitesHelper::getHost())
		{
			$link = 'index.php?option=com_mightysites&task=site.login&domain=' . base64_encode($row->domain) . '&return=' . base64_encode($link);
		}

		return Route::_($link);
	}

	// Mod_security - that's for you, my dear!
	public function showForm($link, $name = null)
	{
		$link = str_replace('&amp;', '&', $link);

		$parts   = parse_url($link);
		$hiddens = array();

		if (isset($parts['query']))
		{
			parse_str($parts['query'], $vars);

			foreach ($vars as $key => $value)
			{
				$hiddens[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
			}
		}

		$html = '<form action="' . $parts['path'] . '" target="_blank" method="post" id="' . $name . '" name="' . $name . '" style="display:none">';
		$html .= implode("\r\n", $hiddens);
		$html .= '</form>';

		return $html;
	}
}
