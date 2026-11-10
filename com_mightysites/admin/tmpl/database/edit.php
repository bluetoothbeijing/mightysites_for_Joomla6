<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('behavior.formvalidator');

$token = Session::getFormToken();
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task === 'database.cancel' || document.formvalidator.isValid(document.getElementById('database-form'))) {
			Joomla.submitform(task, document.getElementById('database-form'));
		}
		else {
			alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED')); ?>');
		}
	}

	async function checkConnection(type)
	{
		document.getElementById('checkWaiter').style.display = 'inline-block';
		document.getElementById('checkButton').disabled = true;

		const data = new URLSearchParams({
			option: 'com_mightysites',
			task: 'database.check',
			db: document.getElementById('jform_db').value,
			dbprefix: document.getElementById('jform_dbprefix').value,
			id: <?php echo (int) $this->item->id; ?>,
			type: type,
			'<?php echo $token; ?>': '1'
		});

		const resp = await fetch('<?php echo Route::_('index.php'); ?>', {method: 'POST', body: data});
		document.getElementById('checkInfo').innerHTML = await resp.text();

		document.getElementById('checkWaiter').style.display = 'none';
		document.getElementById('checkButton').disabled = false;
	}

	async function createDatabase()
	{
		const text = '<?php echo Text::sprintf('COM_MIGHTYSITES_REALLY_CREATE_DB', '________'); ?>';

		if (confirm(text.replace(/________/, document.getElementById('jform_db').value))) {
			document.getElementById('createWaiter').style.display = 'inline-block';
			document.getElementById('createButton').disabled = true;

			const data = new URLSearchParams({
				option: 'com_mightysites',
				task: 'database.create',
				db: document.getElementById('jform_db').value,
				'<?php echo $token; ?>': '1'
			});

			const resp = await fetch('<?php echo Route::_('index.php'); ?>', {method: 'POST', body: data});
			document.getElementById('createInfo').innerHTML = await resp.text();

			document.getElementById('createWaiter').style.display = 'none';
			document.getElementById('createButton').disabled = false;
		}
	}
</script>

<form action="<?php echo Route::_('index.php?option=com_mightysites&view=database&layout=edit&id=' . (int) $this->item->id); ?>" class="form-validate" method="post" name="database-form" id="database-form">

	<h3><?php echo Text::_('COM_MIGHTYSITES_TAB_SETTINGS'); ?></h3>

	<?php echo $this->form->renderField('domain'); ?>

	<div class="control-group mb-2">
		<div class="control-label"><?php echo $this->form->getLabel('db'); ?></div>
		<div class="controls"><?php echo $this->form->getInput('db'); ?>

			<?php if (!$this->item->id) : ?>
			<button type="button" class="btn btn-secondary" id="createButton" onclick="createDatabase()">
				<?php echo Text::_('COM_MIGHTYSITES_CREATE_DATABASE'); ?>
			</button>
			<span id="createWaiter" class="icon-refresh icon-spin" style="display:none"></span>
			<span id="createInfo"></span>
			<?php endif; ?>
		</div>
	</div>

	<?php echo $this->form->renderField('dbprefix'); ?>

	<?php if (!$this->item->id) : ?>
	<?php echo $this->form->renderField('source_db'); ?>
	<?php endif; ?>

	<div class="control-group">
		<div class="controls">
			<button type="button" class="btn btn-secondary" id="checkButton" onclick="checkConnection(1)">
				<?php echo Text::_('COM_MIGHTYSITES_CHECK_CONNECTION'); ?>
			</button>
			<span id="checkWaiter" class="icon-refresh icon-spin" style="display:none"></span>
			<span id="checkInfo"></span>
		</div>
	</div>

	<input type="hidden" name="type" value="<?php echo $this->item->type; ?>" />
	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
