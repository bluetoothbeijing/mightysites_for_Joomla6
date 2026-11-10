<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Registry\Registry;

/**
 * Data source edit model.
 */
class DatabaseModel extends AdminModel
{
	protected $text_prefix = 'COM_MIGHTYSITES_DATABASES_';

	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			// Don't delete sites!
			if ($record->type == 1)
			{
				return false;
			}

			return parent::canDelete($record);
		}

		return false;
	}

	public function getForm($data = array(), $loadData = true)
	{
		$form = $this->loadForm('com_mightysites.database', 'database', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_mightysites.edit.database.data', array());

		if (empty($data))
		{
			$data = $this->getItem();

			// Prime some default values.
			if ($this->getState('database.id') == 0)
			{
				$app = Factory::getApplication();

				$data->type = 2;
				$data->db   = $app->get('db');
			}
		}

		return $data;
	}

	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			// Always work with a Registry for params.
			$item->params = new Registry($item->params);

			if (!$item->id)
			{
				// Force type
				$item->type = 2;
			}
		}

		return $item;
	}

	public function save($data)
	{
		// Update database
		$result = parent::save($data);

		if ($result !== true)
		{
			return $result;
		}

		$isNew = $this->getState($this->getName() . '.new');
		$id    = $this->getState($this->getName() . '.id');

		// Load new row
		$row = $this->getTable();
		$row->load($id);

		// Copy tables
		if ($isNew && !empty($data['source_db']))
		{
			Factory::getApplication()->getSession()->set(
				'mighty_copy',
				'index.php?option=com_mightysites&task=database.copy&from=' . $data['source_db'] . '&to=' . $row->id . '&tmpl=component'
			);
		}

		return true;
	}
}
