<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Menu items selector rendered as a collapsible nested tree with checkboxes.
 *
 * Values are menu item IDs - fully compatible with the former flat "sql"
 * list field (hidemenuitems / onlymenuitems).
 */
class MenuitemstreeField extends FormField
{
	/**
	 * The form field type.
	 */
	protected $type = 'Menuitemstree';

	/**
	 * Assets (CSS/JS) are registered only once per page.
	 */
	protected static $assetsLoaded = false;

	/**
	 * Method to get the field input markup (nested tree with checkboxes).
	 *
	 * @return  string
	 */
	protected function getInput()
	{
		$db = $this->getDatabase();

		// Menu items of the site (front-end only).
		$query = $db->getQuery(true)
			->select('a.id, a.menutype, a.title, a.parent_id, a.published, a.home')
			->from($db->quoteName('#__menu', 'a'))
			->where($db->quoteName('a.client_id') . ' = 0')
			->where($db->quoteName('a.id') . ' > 1')
			->order($db->quoteName('a.menutype') . ', ' . $db->quoteName('a.lft'));

		$db->setQuery($query);

		try
		{
			$items = (array) $db->loadObjectList();
		}
		catch (\Throwable $e)
		{
			$items = array();
		}

		// Human readable menutype titles.
		try
		{
			$types = $db->setQuery(
				'SELECT ' . $db->quoteName('menutype') . ', ' . $db->quoteName('title')
				. ' FROM ' . $db->quoteName('#__menu_types')
			)->loadAssocList('menutype', 'title');
		}
		catch (\Throwable $e)
		{
			$types = array();
		}

		// Normalize selected values (array of IDs).
		$selected = $this->value;

		if (is_string($selected))
		{
			$selected = array_filter(explode(',', $selected), 'strlen');
		}

		$selected = array_flip(array_map('strval', (array) $selected));

		// Group by menutype and index children.
		$byType   = array();
		$children = array();

		foreach ($items as $item)
		{
			$byType[$item->menutype][]    = $item;
			$children[$item->parent_id][] = $item;
		}

		ksort($byType);

		// Name must collect an array of values.
		$name = $this->name;

		if (substr($name, -2) !== '[]')
		{
			$name .= '[]';
		}

		self::registerAssets();

		$esc = function ($string) {
			return htmlspecialchars((string) $string, ENT_COMPAT, 'UTF-8');
		};

		$html = '<div class="mighty-mtree" id="' . $this->id . '_tree">';

		if (empty($byType))
		{
			$html .= '<div class="text-muted small">' . Text::_('COM_MIGHTYSITES_NO_MENUS_FOUND') . '</div>';
		}

		foreach ($byType as $menutype => $menuItems)
		{
			$typeTitle = isset($types[$menutype]) ? $types[$menutype] : $menutype;

			// Roots = items whose parent does not belong to this menutype.
			$idsInType = array_flip(array_map('strval', array_column($menuItems, 'id')));

			$roots = array_values(array_filter($menuItems, function ($item) use ($idsInType) {
				return !isset($idsInType[(string) $item->parent_id]);
			}));

			$html .= '<div class="mighty-mtree-group mb-2">';
			$html .= '<button type="button" class="mighty-mtree-group-toggle btn btn-sm btn-link px-0 text-decoration-none fw-bold">'
				. '<span class="icon-folder" aria-hidden="true"></span> ' . $esc($typeTitle)
				. ' <small class="text-muted">(' . count($menuItems) . ')</small></button>';
			$html .= '<ul class="list-unstyled ms-2">';

			foreach ($roots as $item)
			{
				$html .= self::renderItem($item, $children, $selected, $name, $this->id, $esc);
			}

			$html .= '</ul></div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Recursively render one menu item with its subtree.
	 */
	protected static function renderItem($item, $children, $selected, $name, $fieldId, $esc)
	{
		$checked  = isset($selected[(string) $item->id]) ? ' checked' : '';
		$muted    = !$item->published ? ' text-muted' : '';
		$homeIcon = $item->home ? ' <span class="icon-home" aria-hidden="true" title="' . Text::_('JDEFAULT') . '"></span>' : '';
		$cid      = $fieldId . '_' . (int) $item->id;

		$html  = '<li>';
		$html .= '<div class="form-check m-0">';
		$html .= '<input class="form-check-input" type="checkbox" name="' . $esc($name) . '" value="' . (int) $item->id . '" id="' . $esc($cid) . '"' . $checked . '>';
		$html .= '<label class="form-check-label' . $muted . '" for="' . $esc($cid) . '">' . $esc($item->title) . $homeIcon . (!$item->published ? ' <small>(' . Text::_('JUNPUBLISHED') . ')</small>' : '') . '</label>';
		$html .= '</div>';

		if (!empty($children[$item->id]))
		{
			$html .= '<ul class="list-unstyled ms-4 border-start ps-2">';

			foreach ($children[$item->id] as $child)
			{
				$html .= self::renderItem($child, $children, $selected, $name, $fieldId, $esc);
			}

			$html .= '</ul>';
		}

		$html .= '</li>';

		return $html;
	}

	/**
	 * Register the shared CSS/JS (once per page).
	 */
	protected static function registerAssets()
	{
		if (self::$assetsLoaded)
		{
			return;
		}

		self::$assetsLoaded = true;

		$doc = Factory::getDocument();

		$doc->addStyleDeclaration(
			'.mighty-mtree{max-height:420px;overflow:auto;background:var(--body-bg,#fff);border:1px solid var(--bs-border-color,#ced4da);border-radius:.25rem;padding:.5rem}'
			. '.mighty-mtree ul{margin-bottom:0}'
			. '.mighty-mtree .form-check{padding-left:1.5rem;margin-bottom:.15rem}'
			. '.mighty-mtree .form-check-input{margin-top:.3rem;float:left;margin-left:-1.5rem}'
			. '.mighty-mtree .form-check-label{cursor:pointer}'
		);

		$doc->addScriptDeclaration(
			"document.addEventListener('change', function(e) {
	if (e.target && e.target.matches('.mighty-mtree input[type=checkbox]')) {
		var li = e.target.closest('li');
		if (li) {
			li.querySelectorAll(':scope > ul input[type=checkbox]').forEach(function(c) { c.checked = e.target.checked; });
		}
	}
});
document.addEventListener('click', function(e) {
	var h = e.target && e.target.closest('.mighty-mtree-group-toggle');
	if (h) {
		e.preventDefault();
		var ul = h.parentElement.querySelector(':scope > ul');
		if (ul) { ul.classList.toggle('d-none'); }
	}
});"
		);
	}
}
