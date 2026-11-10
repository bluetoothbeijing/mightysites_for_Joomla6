<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$user      = Factory::getApplication()->getIdentity();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

// Popover helper script
Factory::getDocument()->addScriptDeclaration("
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.hasMightyPopover').forEach(function(el) {
        new bootstrap.Popover(el, {trigger: 'hover focus', html: true});
    });
});
");
?>
<form action="<?php echo Route::_('index.php?option=com_mightysites&view=sites'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
				<table class="table table-striped" id="sitesList">
					<thead>
						<tr>
							<th class="w-1 text-center">
								<?php echo HTMLHelper::_('grid.checkall'); ?>
							</th>
							<th class="w-1 text-center">
								<?php echo Text::_('COM_MIGHTYSITES_HEADING_ONLINE'); ?>
							</th>
							<th>
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_SITE', 'a.domain', $listDirn, $listOrder); ?>
							</th>
							<th class="w-25 text-center">
								<?php echo Text::_('COM_MIGHTYSITES_HEADING_ACTIONS'); ?>
							</th>
							<th class="w-1 text-center d-none d-md-table-cell">
								<?php echo Text::_('COM_MIGHTYSITES_HEADING_INFORMATION'); ?>
							</th>
							<th class="w-5 text-center d-none d-md-table-cell">
								<?php echo Text::_('COM_MIGHTYSITES_HEADING_LANGUAGE'); ?>
							</th>
							<th class="w-5 text-center d-none d-md-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_NAME', 'a.db', $listDirn, $listOrder); ?>
							</th>
							<th class="w-5 text-center d-none d-md-table-cell">
								<?php echo Text::_('COM_MIGHTYSITES_HEADING_DATABASE_USER'); ?>
							</th>
							<th class="w-5 text-center d-none d-md-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_PREFIX', 'a.dbprefix', $listDirn, $listOrder); ?>
							</th>
							<th class="w-1 text-center">
								<?php echo HTMLHelper::_('searchtools.sort', 'ID', 'a.id', $listDirn, $listOrder); ?>
							</th>
						</tr>
					</thead>

					<tfoot>
						<tr>
							<td colspan="10">
								<?php echo $this->pagination->getListFooter(); ?>
							</td>
						</tr>
					</tfoot>

					<tbody>
					<?php foreach ($this->items as $i => $item) :
						$canEdit    = $user->authorise('core.edit', 'com_mightysites');
						$canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $user->get('id') || is_null($item->checked_out_time) || $item->checked_out == 0;
						$canChange  = $user->authorise('core.edit.state', 'com_mightysites') && $canCheckin;
					?>
						<tr class="row<?php echo $i % 2; ?>">
							<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>

							<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', !@$item->offline, $i, 'sites.', $canChange, 'cb'); ?></td>

							<td>
								<?php if ($item->checked_out) : ?>
									<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'sites.', $canCheckin); ?>
								<?php endif; ?>

								<?php if ($canEdit) : ?>
									<a href="<?php echo Route::_('index.php?option=com_mightysites&task=site.edit&id=' . (int) $item->id); ?>">
										<?php echo $item->title; ?></a>
								<?php else : ?>
									<?php echo $item->title; ?>
								<?php endif; ?>
								<div class="small"><?php echo (isset($item->sitename) && is_string($item->sitename)) ? $this->escape($item->sitename) : ''; ?></div>
								<?php if (!empty($item->config_missing)) : ?>
									<span class="badge bg-warning text-dark" title="<?php echo htmlspecialchars((string) $item->config_file, ENT_COMPAT, 'UTF-8'); ?>">
										<span class="icon-warning" aria-hidden="true"></span> <?php echo Text::_('COM_MIGHTYSITES_CONFIG_MISSING'); ?>
									</span>
								<?php endif; ?>
							</td>

							<td>
								<?php if ($user->authorise('core.edit', 'com_mightysites')) : ?>
								<a class="nowrap" href="#" onclick="document.siteFormConfig<?php echo $item->id; ?>.submit(); return false">
									<span class="icon-cog"></span> <?php echo Text::_('COM_MIGHTYSITES_ACTION_CONFIG'); ?>
								</a>
								&nbsp;
								<a class="nowrap" href="#" onclick="document.siteFormAdmin<?php echo $item->id; ?>.submit(); return false">
									<span class="icon-share-alt"></span> <?php echo Text::_('COM_MIGHTYSITES_ACTION_ADMIN'); ?>
								</a>
								&nbsp;
								<?php endif; ?>

								<a class="nowrap" href="https://<?php echo $item->domain; ?>" target="_blank" rel="noopener">
									<span class="icon-eye"></span> <?php echo Text::_('COM_MIGHTYSITES_ACTION_PREVIEW'); ?>
								</a>
							</td>

							<td class="text-center d-none d-md-table-cell">
								<?php if (!empty($item->contentTip)) : ?>
									<span class="badge <?php echo !empty($item->mighty_enable) ? 'bg-success' : 'bg-danger'; ?> hasMightyPopover" data-bs-placement="right" data-bs-content="<?php echo $item->contentTip; ?>" title="<?php echo Text::_('COM_MIGHTYSITES_INFO_CONTENT_SHARING'); ?>">
										<span class="icon-shuffle"></span>
									</span>
								<?php endif; ?>
							</td>

							<td class="text-center d-none d-md-table-cell">
								<?php if ($item->singleTip) : ?>
								<span class="badge <?php echo (!empty($item->mighty_slogin) || !empty($item->mighty_slogout)) ? 'bg-success' : 'bg-danger'; ?> hasMightyPopover" data-bs-placement="right" data-bs-content="<?php echo $item->singleTip; ?>" title="<?php echo Text::_('COM_MIGHTYSITES_INFO_SINGLE'); ?>">
									<span class="icon-users"></span>
								</span>
								<?php endif; ?>
							</td>

							<td class="text-center d-none d-md-table-cell">
								<?php if ($item->aliases) : ?>
								<span class="badge bg-secondary hasMightyPopover" data-bs-placement="right" data-bs-content="<?php echo nl2br($item->aliases); ?>" title="<?php echo Text::_('COM_MIGHTYSITES_INFO_ALIASES'); ?>">
									<span class="icon-copy"></span>
								</span>
								<?php endif; ?>
							</td>

							<td class="small text-center d-none d-md-table-cell"><?php echo (!empty($item->mighty_language)) ? $item->mighty_language : Text::_('JDEFAULT'); ?></td>
							<td class="small text-center d-none d-md-table-cell"><?php echo @$item->db; ?></td>
							<td class="small text-center d-none d-md-table-cell"><?php echo @$item->user; ?></td>
							<td class="small text-center d-none d-md-table-cell"><?php echo @$item->dbprefix; ?></td>
							<td class="text-center"><?php echo $item->id; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<input type="hidden" name="delete_tables" value="" />
				<input type="hidden" name="task" value="" />
				<input type="hidden" name="boxchecked" value="0" />
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>

<script type="text/javascript">
	Joomla.submitbutton = function(task) {
		if (task === 'sites.remove') {
			if (window.confirm('<?php echo Text::_('COM_MIGHTYSITES_REALLY_DELETE_SITES'); ?>')) {
				if (window.confirm('<?php echo Text::_('COM_MIGHTYSITES_REALLY_DELETE_DB'); ?>')) {
					/* Confirm twice! */
					document.adminForm.delete_tables.value = 'true';
				}
			} else {
				return;
			}
		}
		Joomla.submitform(task, document.getElementById('adminForm'));
	}
</script>

<?php foreach ($this->items as $i => $item) : ?>
	<?php echo $this->showForm($item->link, 'siteFormConfig' . $item->id); ?>
	<?php echo $this->showForm($item->link2, 'siteFormAdmin' . $item->id); ?>
<?php endforeach; ?>
