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
HTMLHelper::_('bootstrap.tab', 'siteTabs', array('active' => 'tab-common'));

$token = Session::getFormToken();

/**
 * Render a form field defensively.
 *
 * Some legacy field patches (e.g. the Fabrik system plugin) replace core
 * form field classes/layouts and can pass non-scalar values to the core
 * text layout, causing a fatal TypeError inside htmlspecialchars(). In that
 * case we clean up the partial output and fall back to a plain input so the
 * whole edit page keeps working.
 */
$mightyRenderField = function ($field)
{
	$level = ob_get_level();

	try
	{
		return $field->input;
	}
	catch (\Throwable $e)
	{
		while (ob_get_level() > $level)
		{
			ob_end_clean();
		}

		$val = $field->value;

		if (is_array($val))
		{
			$val = implode(',', array_filter($val, 'is_scalar'));
		}

		$name = (string) $field->name;
		$id   = (string) $field->id;

		return '<input type="text" class="form-control"'
			. ' name="' . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '"'
			. ' id="' . htmlspecialchars($id, ENT_COMPAT, 'UTF-8') . '"'
			. ' value="' . htmlspecialchars((string) $val, ENT_COMPAT, 'UTF-8') . '" />';
	}
};
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task === 'site.cancel' || document.formvalidator.isValid(document.getElementById('site-form'))) {
			Joomla.submitform(task, document.getElementById('site-form'));
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
			user: document.getElementById('jform_user') ? document.getElementById('jform_user').value : '',
			password: document.getElementById('jform_password') ? document.getElementById('jform_password').value : '',
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

	async function loadTables()
	{
		document.getElementById('tablesWaiter').style.display = 'inline-block';

		const data = new URLSearchParams({
			option: 'com_mightysites',
			view: 'site',
			layout: 'tables',
			id: <?php echo (int) $this->item->id; ?>
		});

		const resp = await fetch('<?php echo Route::_('index.php'); ?>', {method: 'POST', body: data});
		document.getElementById('tablesArea').innerHTML = await resp.text();

		document.getElementById('tablesWaiter').style.display = 'none';
	}

	function selectAllTo(scope, val) {
		if (val >= 0) {
			document.querySelectorAll('#' + scope + ' select').forEach(function(e) {
				e.value = val;
			});
		}
	}

	function addSingleDomain() {
		const table = document.getElementById('singleDomainsTable').tBodies[0];
		const newTr = table.rows[table.rows.length - 1].cloneNode(true);
		table.appendChild(newTr);

		const lastRow = table.rows[table.rows.length - 1];
		lastRow.children[0].querySelector('input').value = '';
		lastRow.children[1].querySelector('input').value = '';
		return false;
	}
</script>

<form action="<?php echo Route::_('index.php?option=com_mightysites&view=site&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="site-form" id="site-form" class="form-validate">

	<?php echo HTMLHelper::_('uitab.startTabSet', 'siteTabs', array('active' => 'tab-common')); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-common', Text::_('COM_MIGHTYSITES_TAB_BASIC')); ?>
		<div class="tab-content p-3">
			<?php echo $this->form->renderField('domain'); ?>
			<?php echo $this->form->renderField('aliases'); ?>

			<?php if (!$this->item->id) : ?>
			<?php echo $this->form->renderField('source_config'); ?>
			<?php echo $this->form->renderField('source_db'); ?>
			<?php endif; ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-database', Text::_('COM_MIGHTYSITES_TAB_DATABASE')); ?>
		<div class="tab-content p-3">
			<?php echo $this->form->renderField('db'); ?>

			<?php if (!$this->item->id) : ?>
			<div class="control-group mb-2">
				<div class="controls">
					<button type="button" class="btn btn-secondary" id="createButton" onclick="createDatabase()">
						<?php echo Text::_('COM_MIGHTYSITES_CREATE_DATABASE'); ?>
					</button>
					<span id="createWaiter" class="icon-refresh icon-spin" style="display:none"></span>
					<span id="createInfo"></span>
				</div>
			</div>
			<?php endif; ?>

			<?php echo $this->form->renderField('dbprefix'); ?>
			<?php echo $this->form->renderField('user'); ?>
			<?php echo $this->form->renderField('password'); ?>
			<?php echo $this->form->renderField('dbtype'); ?>

			<div class="control-group">
				<div class="controls">
					<button type="button" class="btn btn-secondary" id="checkButton" onclick="checkConnection(1)">
						<?php echo Text::_('COM_MIGHTYSITES_CHECK_CONNECTION'); ?>
					</button>
					<span id="checkWaiter" class="icon-refresh icon-spin" style="display:none"></span>
					<span id="checkInfo"></span>
				</div>
			</div>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-sharing', Text::_('COM_MIGHTYSITES_TAB_CONTENT_SHARING')); ?>
		<div class="tab-content p-3">
			<?php if (!$this->item->id) : ?>
				<?php echo Text::_('COM_MIGHTYSITES_APPLY_SETTINGS'); ?>
			<?php else : ?>
				<?php foreach ($this->form->getFieldset('replacements') as $field) : ?>
				<div class="control-group mb-2">
					<div class="control-label"><?php echo $field->label; ?></div>
					<div class="controls"><?php echo $mightyRenderField($field); ?></div>
				</div>
				<?php endforeach; ?>

				<div class="row">
					<div class="col-md-4" id="coreComponents">
						<h4><?php echo Text::_('COM_MIGHTYSITES_FIELDSET_CORE_COMPONENTS'); ?></h4>
						<div class="mb-2">
							<?php echo \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::sitesList('coreComponentsSelect', -1, "selectAllTo('coreComponents', this.value)", $this->item->id, Text::_('COM_MIGHTYSITES_SELECT_ALL_TO'), false, '-1', Text::_('COM_MIGHTYSITES_OWN_DATA'), ''); ?>
						</div>
						<hr/>

						<?php foreach ($this->synchs_core as $synch => $input) : ?>
						<div class="control-group mb-2">
							<div class="control-label"><label class="hasTip" title="<?php echo Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $synch); ?>::<?php echo Text::_('COM_MIGHTYSITES_SYNCH_DESC_' . $synch); ?>"><?php echo Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $synch); ?></label></div>
							<div class="controls"><?php echo $input; ?></div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="col-md-4" id="customComponents">
						<h4><?php echo Text::_('COM_MIGHTYSITES_FIELDSET_CUSTOM_COMPONENTS'); ?></h4>
						<div class="mb-2">
							<?php echo \AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::sitesList('customComponentsSelect', -1, "selectAllTo('customComponents', this.value)", $this->item->id, Text::_('COM_MIGHTYSITES_SELECT_ALL_TO'), false, '-1', Text::_('COM_MIGHTYSITES_OWN_DATA'), ''); ?>
						</div>
						<hr/>

						<?php foreach ($this->synchs_custom as $synch => $input) : ?>
						<div class="control-group mb-2">
							<div class="control-label"><label class="hasTip" title="<?php echo Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $synch); ?>::<?php echo Text::_('COM_MIGHTYSITES_SYNCH_DESC_' . $synch); ?>"><?php echo Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $synch); ?></label></div>
							<div class="controls"><?php echo $input; ?></div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="col-md-4" id="tableComponents">
						<h4><?php echo Text::_('COM_MIGHTYSITES_FIELDSET_DATABASE_TABLES'); ?></h4>

						<div id="tablesArea">
							<button type="button" class="btn btn-secondary" onclick="loadTables()"><?php echo Text::_('COM_MIGHTYSITES_LOAD_TABLES'); ?></button>
							<span id="tablesWaiter" class="icon-refresh icon-spin" style="display:none"></span>

							<?php // Keep current settings
							$tables = 0;
							foreach ($this->params->toArray() as $key => $value)
							{
								if (strpos($key, 'table_') === 0)
								{
									echo '<input type="hidden" name="jform[tables][' . substr($key, 6) . ']" value="' . $value . '" />';
									$tables++;
								}
							} ?>
							<p></p>
							<p><?php echo Text::plural('COM_MIGHTYSITES_N_TABLES_ASSIGNED', $tables); ?></p>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-single', Text::_('COM_MIGHTYSITES_TAB_SINGLE')); ?>
		<div class="tab-content p-3">
			<?php if (!$this->item->id) : ?>
				<?php echo Text::_('COM_MIGHTYSITES_APPLY_SETTINGS'); ?>
			<?php else : ?>
				<?php foreach ($this->form->getFieldset('single') as $field) : ?>
				<div class="control-group mb-2">
					<div class="control-label"><?php echo $field->label; ?></div>
					<div class="controls"><?php echo $mightyRenderField($field); ?></div>
				</div>
				<?php endforeach; ?>

				<h4><?php echo Text::_('COM_MIGHTYSITES_FIELDSET_SINGLE_SITES'); ?></h4>

				<table class="table table-striped w-auto" id="singleDomainsTable">
					<thead>
						<tr>
							<th><?php echo Text::_('COM_MIGHTYSITES_DOMAIN'); ?></th>
							<th><?php echo Text::_('COM_MIGHTYSITES_SECRET_KEY'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$i = 0;
						while ($this->params->get('single_domain' . (string) $i)) { ?>
							<tr>
								<td><input type="text" class="form-control" name="single_domain[]" value="<?php echo $this->params->get('single_domain' . (string) $i); ?>" /></td>
								<td><input type="text" class="form-control" name="single_key[]" value="<?php echo $this->params->get('single_key' . (string) $i); ?>" /></td>
							</tr>
						<?php
							$i++;
						}

						if (!$i) { ?>
							<tr>
								<td><input type="text" class="form-control" name="single_domain[]" value="" /></td>
								<td><input type="text" class="form-control" name="single_key[]" value="" /></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>

				<p><button type="button" class="btn btn-secondary" onclick="return addSingleDomain()"><?php echo Text::_('COM_MIGHTYSITES_ADD_SINGLE_SITE'); ?></button></p>
			<?php endif; ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-overrides', Text::_('COM_MIGHTYSITES_TAB_OVERRIDES')); ?>
		<div class="tab-content p-3">
			<?php if (!$this->item->id) : ?>
				<?php echo Text::_('COM_MIGHTYSITES_APPLY_SETTINGS'); ?>
			<?php else : ?>
				<?php foreach ($this->form->getFieldset('overrides') as $field) : ?>
				<div class="control-group mb-2">
					<div class="control-label"><?php echo $field->label; ?></div>
					<div class="controls"><?php echo $mightyRenderField($field); ?></div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'siteTabs', 'tab-advanced', Text::_('COM_MIGHTYSITES_TAB_ADVANCED')); ?>
		<div class="tab-content p-3">
			<?php if (!$this->item->id) : ?>
				<?php echo Text::_('COM_MIGHTYSITES_APPLY_SETTINGS'); ?>
			<?php else : ?>
				<?php foreach ($this->form->getFieldset('advanced') as $field) : ?>
				<div class="control-group mb-2">
					<div class="control-label"><?php echo $field->label; ?></div>
					<div class="controls"><?php echo $mightyRenderField($field); ?></div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="type" value="<?php echo $this->item->type; ?>" />
	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
