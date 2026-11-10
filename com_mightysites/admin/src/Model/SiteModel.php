<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Model;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\User\UserHelper;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

/**
 * Site edit model.
 */
class SiteModel extends AdminModel
{
	protected $text_prefix = 'COM_MIGHTYSITES_SITES_';

	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			// Don't delete root!
			if ($record->id == 1)
			{
				return false;
			}

			// Don't delete databases!
			if ($record->type == 2)
			{
				return false;
			}

			return parent::canDelete($record);
		}

		return false;
	}

	public function getForm($data = array(), $loadData = true)
	{
		$form = $this->loadForm('com_mightysites.site', 'site', array('control' => 'jform', 'load_data' => $loadData));

		// Require fields
		if ($this->getState('site.id'))
		{
			$form->setFieldAttribute('source_config', 'required', 'false');

			// If site uses same database - we can prettify it a bit
			$config = Factory::getConfig();
			$site   = MightysitesHelper::getSite($this->getState('site.id'), true);

			if ($site && $site->db == $config->get('db') && $site->dbprefix == $config->get('dbprefix'))
			{
				// Prettify Template Style
				$form->setFieldAttribute('template', 'type', 'templatestyle', 'params');
			}
		}

		return $form;
	}

	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_mightysites.edit.site.data', array());

		if (empty($data))
		{
			$data = $this->getItem();

			// Config file is missing (typical for sites migrated from Joomla 3):
			// prefill the connection data - db/dbprefix from the site row,
			// credentials from the live configuration - so that a simple save
			// creates a usable per-domain configuration file.
			if (is_object($data) && !empty($data->config_missing))
			{
				$app = Factory::getApplication();

				foreach (array('user', 'password', 'host', 'dbtype', 'db', 'dbprefix') as $key)
				{
					if (!isset($data->$key) || $data->$key === '')
					{
						$data->$key = $app->get($key);
					}
				}
			}

			// Prime some default values.
			if ($this->getState('site.id') == 0)
			{
				$app = Factory::getApplication();

				$data->type     = 1;
				$data->db       = $app->get('db');
				$data->dbprefix = $app->get('dbprefix');
				$data->user     = $app->get('user');
				$data->password = $app->get('password');
			}
		}

		$this->sanitizeFormData($data);

		return $data;
	}

	/**
	 * Ensure form data types match the field types: multi-select fields get
	 * arrays, everything else gets scalars. Prevents e.g. an array value
	 * being fed into a text field layout (htmlspecialchars() fatal) after a
	 * failed save round-tripped array values through the session user state.
	 */
	protected function sanitizeFormData($data)
	{
		$multi = array(
			'hidemenus', 'onlymenus', 'hidemenuitems', 'onlymenuitems',
			'hidemodules', 'onlymodules', 'hideplugins',
			'hidecontentcats', 'onlycontentcats',
			'single_domains', 'content', 'tables',
		);

		$fix = function (&$params) use ($multi)
		{
			foreach ($params as $key => $value)
			{
				if (is_array($value) && !in_array($key, $multi))
				{
					// Coerce to scalar: single value, or comma-joined list.
					$flat = array_values(array_filter($value, 'is_scalar'));

					$params[$key] = empty($flat) ? '' : (count($flat) == 1 ? (string) $flat[0] : implode(',', array_map('strval', $flat)));
				}
				elseif (!is_array($value) && in_array($key, $multi) && $value !== null && $value !== '')
				{
					// Multi-value fields expect arrays.
					$params[$key] = (array) $value;
				}
			}
		};

		if (is_array($data) && isset($data['params']) && is_array($data['params']))
		{
			$fix($data['params']);
		}
		elseif (is_object($data) && isset($data->params))
		{
			if ($data->params instanceof Registry)
			{
				$arr = $data->params->toArray();
				$fix($arr);
				$data->params = new Registry($arr);
			}
			elseif (is_array($data->params))
			{
				$fix($data->params);
			}
		}
	}

	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			// Always work with a Registry for params (DB gives a raw JSON string).
			$item->params = new Registry($item->params);

			if ($item->id)
			{
				// Add config
				MightysitesHelper::attachConfig($item);

				// Pass legacy vars into params
				$legacy_map = array(
					'mighty_language'       => 'language',
					'mighty_template'       => 'template',
					'mighty_force_template' => 'force_template',
					'mighty_home'           => 'home',
					'mighty_langoverride'   => 'langoverride',
					'mighty_enable'         => 'enable_replacements',
					'mighty_hidemenus'      => 'hidemenus',
					'mighty_onlymenus'      => 'onlymenus',
					'mighty_hidemenuitems'  => 'hidemenuitems',
					'mighty_onlymenuitems'  => 'onlymenuitems',
					'secret'                => 'secret',
					'cookie_domain'         => 'cookie_domain',
				);

				foreach ($legacy_map as $prop => $param)
				{
					if (isset($item->$prop))
					{
						$item->params[$param] = $item->$prop;
					}
				}

				if (isset($item->params['slogin']))
				{
					$item->params['single_login'] = $item->params['slogin'];
				}

				if (isset($item->params['slogout']))
				{
					$item->params['single_logout'] = $item->params['slogout'];
				}
			}
			else
			{
				// Force type
				$item->type = 1;
			}
		}

		return $item;
	}

	public function save($data)
	{
		$app = Factory::getApplication();

		// Prepare domain name
		$data['domain'] = trim(strtolower($data['domain']), ' ./\\');

		if (substr($data['domain'], 0, 7) === 'http://')
		{
			$data['domain'] = substr($data['domain'], 7);
		}

		if (substr($data['domain'], 0, 8) === 'https://')
		{
			$data['domain'] = substr($data['domain'], 8);
		}

		if (empty($data['domain']))
		{
			$this->setError(Text::_('COM_MIGHTYSITES_ERROR_DOMAIN_EMPTY'));
			return false;
		}

		// Prepare single login/logout params
		$single_domain = $app->input->get('single_domain', array(), 'array');
		$single_key    = $app->input->get('single_key', array(), 'array');

		if (count($single_domain))
		{
			$i = 0;
			$data['params']['single_domains'] = array();

			foreach ($single_domain as $value)
			{
				if (!empty($value))
				{
					$value = trim(strtolower($value), ' /');

					// Not to itself!
					if ($value === $data['domain'])
					{
						continue;
					}

					$data['params']['single_domain' . (string) $i] = $value;
					$data['params']['single_key' . (string) $i]    = trim($single_key[$i]);
					$data['params']['single_domains'][$value]      = trim($single_key[$i]);
				}

				$i++;
			}
		}

		// Prepare Aliases
		if (strlen($data['aliases']))
		{
			$data['aliases'] = strtolower($data['aliases']);
			$data['aliases'] = strtr($data['aliases'], "\r\n", "\n");
			$data['aliases'] = array_filter(explode("\n", $data['aliases']));

			foreach ($data['aliases'] as &$alias)
			{
				$alias = trim($alias, '/ ');

				if (substr($alias, 0, 7) === 'http://')
				{
					$alias = substr($alias, 7);
				}

				if (substr($alias, 0, 8) === 'https://')
				{
					$alias = substr($alias, 8);
				}

				if (substr($alias, 0, 4) === 'www.')
				{
					$alias = substr($alias, 4);
				}

				// Still someone will try to enter this here!
				if ($alias === $data['domain'])
				{
					$alias = '';
				}

				// Alias can't be current domain
				if (MightysitesHelper::getSite($alias))
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_ALIAS_EXISTS_SITE', $alias), 'error');
					$alias = '';
				}

				// Check aliases of other sites - no duplicates!
				foreach (MightysitesHelper::getSites() as $site2)
				{
					if ($site2->aliases)
					{
						$aliases2 = explode("\n", $site2->aliases);

						if (in_array($alias, $aliases2) && $site2->id != $data['id'])
						{
							$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_ALIAS_EXISTS', $alias, $site2->domain), 'error');
							$alias = '';
						}
					}
				}
			}

			$data['aliases'] = implode("\n", array_filter($data['aliases']));
		}

		// Get all fields
		$jform = $app->input->get('jform', array(), 'array');

		// Add tables
		if (isset($jform['tables']) && is_array($jform['tables']))
		{
			foreach ($jform['tables'] as $i => $value)
			{
				if ($value)
				{
					$data['params']['table_' . $i] = $value;
				}
			}
		}

		// New site?
		$isNew = empty($data['id']);
		$source_site = null;

		if ($isNew)
		{
			// Load source site.
			$source_site = MightysitesHelper::getSite($data['source_config']);

			if (!$source_site)
			{
				$this->setError(Text::_('COM_MIGHTYSITES_INVALID_SOURCE'));
				return false;
			}

			// New site will get source params
			$data['params'] = $source_site->params->toArray();

			// But not this, otherwise nobody will login.
			unset($data['params']['cookie_domain']);
		}

		// Add content
		$data['params']['content'] = array();

		if (isset($jform['content']) && is_array($jform['content']))
		{
			foreach ($jform['content'] as $pk => $pv)
			{
				if ($pv !== '')
				{
					$data['params']['content'][$pk] = $pv;
				}
			}
		}

		// Load current row
		$id     = $this->getState($this->getName() . '.id');
		$before = $this->getTable();
		$before->load($id);
		$before->params = new Registry($before->params);

		// Update database
		$result = parent::save($data);

		if ($result !== true)
		{
			return $result;
		}

		// Load new row
		$id  = $this->getState($this->getName() . '.id');
		$row = $this->getTable();
		$row->load($id);
		$row->params = new Registry($row->params);

		$params = isset($data['params']) ? $data['params'] : array();

		if ($isNew)
		{
			// Create config
			$config = $this->loadConfig($source_site->domain);

			// New secret key
			$config->secret      = UserHelper::genRandomPassword(16);
			$config->live_site   = '';
			$config->cookie_domain = '';
		}
		else
		{
			// Domain changed? Compare non-www entries!
			$before_domain = (substr($before->domain, 0, 4) === 'www.') ? substr($before->domain, 4) : $before->domain;
			$row_domain    = (substr($row->domain, 0, 4) === 'www.') ? substr($row->domain, 4) : $row->domain;

			if ($before_domain !== $row_domain)
			{
				$this->copyConfig($before->domain, $row->domain);
				$this->removeConfig($before->domain);
			}

			// Load current config
			$config = $this->loadConfig($row->domain);
		}

		// New system settings.
		$config->db       = $data['db'];
		$config->user     = $data['user'];
		$config->dbprefix = $data['dbprefix'];
		$config->password = $data['password'];
		$config->dbtype   = $data['dbtype'];

		if (!empty($params['secret']))
		{
			$config->secret = $params['secret'];
		}

		if (isset($params['cookie_domain']))
		{
			$config->cookie_domain = $params['cookie_domain'];
		}

		// Create replacements
		MightysitesHelper::attachConfig($row, $config);

		// Next part is only active for existing site.
		if (!$isNew)
		{
			$replacements = $this->createTablesReplacements($row);

			// Check dummies
			if (isset($replacements[0]) && is_array($replacements[0]) && count($replacements[0]) > 200)
			{
				$app->enqueueMessage(Text::_('СOM_MIGHTYSITES_ERROR_TOO_MUCH_SHARINGS'), 'notice');
			}

			// Inject our settings
			$config->mighty               = $replacements;
			$config->mighty_enable        = $params['enable_replacements'] ?? '';
			$config->mighty_language      = $params['language'] ?? '';
			$config->mighty_template      = $params['template'] ?? '';
			$config->mighty_force_template = $params['force_template'] ?? '';
			$config->mighty_home          = $params['home'] ?? '';
			$config->mighty_langoverride  = $params['langoverride'] ?? '';
			$config->cache_path           = $params['cache_path'] ?? '';
			$config->mighty_slogin        = $params['single_login'] ?? '';
			$config->mighty_slogout       = $params['single_logout'] ?? '';
			$config->mighty_sdomains      = $params['single_domains'] ?? '';
			$config->mighty_css           = !empty($params['custom_css']) ? explode("\n", str_replace("\r\n", "\n", $params['custom_css'])) : array();
			$config->mighty_js            = $params['custom_js'] ?? '';
			$config->mighty_hidemenus     = !empty($params['hidemenus']) ? array_filter($params['hidemenus']) : '';
			$config->mighty_onlymenus     = !empty($params['onlymenus']) ? array_filter($params['onlymenus']) : '';
			$config->mighty_hidemenuitems = !empty($params['hidemenuitems']) ? array_filter($params['hidemenuitems']) : '';
			$config->mighty_onlymenuitems = !empty($params['onlymenuitems']) ? array_filter($params['onlymenuitems']) : '';
			$config->mighty_hidemodules   = !empty($params['hidemodules']) ? array_filter($params['hidemodules']) : '';
			$config->mighty_onlymodules   = !empty($params['onlymodules']) ? array_filter($params['onlymodules']) : '';
			$config->mighty_hideplugins   = !empty($params['hideplugins']) ? array_filter($params['hideplugins']) : '';
			$config->mighty_hidecontentcats = !empty($params['hidecontentcats']) ? array_filter($params['hidecontentcats']) : '';
			$config->mighty_onlycontentcats = !empty($params['onlycontentcats']) ? array_filter($params['onlycontentcats']) : '';
			$config->mighty_favicon       = $params['favicon'] ?? '';
			$config->mighty_mijoshopid    = $params['mijoshopid'] ?? '';
			$config->mighty_file_path     = $params['file_path'] ?? '';
			$config->mighty_image_path    = $params['image_path'] ?? '';
			$config->mighty_new_usertype  = $params['new_usertype'] ?? '';
			$config->mighty_yootheme_style = $params['yootheme_style'] ?? '';
			$config->mighty_falang        = $params['falang'] ?? '';
		}

		// Save config
		$this->saveConfig($data['domain'], $config, $isNew);

		// Copy tables
		if ($isNew && !empty($data['source_db']))
		{
			$app->getSession()->set(
				'mighty_copy',
				'index.php?option=com_mightysites&task=database.copy&from=' . $data['source_db'] . '&to=' . $row->id . '&tmpl=component'
			);
		}

		// Update aliases
		if ($before->aliases !== $row->aliases || $before->domain !== $row->domain)
		{
			$before_aliases = explode("\n", (string) $before->aliases);
			$row_aliases    = explode("\n", (string) $row->aliases);

			$old_aliases = array_diff($before_aliases, $row_aliases);
			$new_aliases = array_diff($row_aliases, $before_aliases);

			// Domain was changed - change all!
			if ($before->domain !== $row->domain)
			{
				$new_aliases = $row_aliases;
			}

			foreach ($old_aliases as $old_alias)
			{
				$this->removeAlias($old_alias);
			}

			foreach ($new_aliases as $new_alias)
			{
				$this->createAlias($row->domain, $new_alias);
			}
		}

		// Auto-check if configuration.php is patched + auto-update default.php config
		MightysitesHelper::patchConfiguration();

		return true;
	}

	protected function loadConfig($domain)
	{
		$app  = Factory::getApplication();
		$fname = MightysitesHelper::getConfigFilename($domain);

		if (!file_exists($fname))
		{
			// Auto-heal: if this config is for the domain we are currently
			// browsing, we can safely rebuild it from the live configuration
			// (e.g. after the domain of this site record was just changed).
			$reason = '';

			if (!MightysitesHelper::createConfigFromLive($domain, $reason) || !file_exists($fname))
			{
				// Another domain (or not writable): use the live configuration
				// as a TEMPLATE. Nothing is written here - saveConfig() writes
				// the file later using the values submitted in the form, so no
				// other domain's credentials are invented.
				try
				{
					$template = Factory::getConfig();
				}
				catch (\Throwable $e)
				{
					$template = null;
				}

				if (!$template)
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_NEW_CONFIG_DOESNT_EXIST', $fname), 'error');
					$app->redirect('index.php?option=com_mightysites');
				}

				return clone $template;
			}
		}

		if (!is_readable($fname))
		{
			$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_NEW_CONFIG_NOT_READABLE', $fname), 'error');
			$app->redirect('index.php?option=com_mightysites');
		}

		$config = @file_get_contents($fname);

		$classname = 'JConfig' . uniqid();

		if (!class_exists($classname))
		{
			$config = strtr($config, array(
				'<?php'        => '',
				'class JConfig' => 'class ' . $classname,
			));
			eval($config);
		}

		if (class_exists($classname))
		{
			$config = new $classname;
		}

		return $config;
	}

	protected function saveConfig($domain, $config, $isNew = false)
	{
		$app  = Factory::getApplication();
		$file = MightysitesHelper::getConfigFilename($domain);

		if (file_exists($file))
		{
			@chmod($file, 0644);
		}

		$registry = new Registry($config);

		$configuration = $registry->toString('PHP', array('class' => 'JConfigggg', 'closingtag' => false));
		$configuration = strtr($configuration, array('JConfigggg' => 'JConfig'));

		if (!File::write($file, $configuration))
		{
			$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_CONFIG_WRITE_FAILED', $file), 'error');
			$app->redirect('index.php?option=com_mightysites');
		}

		@chmod($file, 0444);
	}

	protected function copyConfig($domain_from, $domain_to)
	{
		$file_from = MightysitesHelper::getConfigFilename($domain_from);
		$file_to   = MightysitesHelper::getConfigFilename($domain_to);

		if ($file_from !== $file_to && file_exists($file_from))
		{
			if (!File::copy($file_from, $file_to))
			{
				Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_CONFIG_WRITE_FAILED', $file_to), 'error');
			}
		}
	}

	protected function removeConfig($domain)
	{
		$file = MightysitesHelper::getConfigFilename($domain);

		if (file_exists($file))
		{
			@chmod($file, 0644);

			return File::delete($file);
		}

		return true;
	}

	protected function removeAlias($alias)
	{
		if (empty($alias))
		{
			return;
		}

		$filename = MightysitesHelper::getConfigFilename($alias);

		if (file_exists($filename))
		{
			@chmod($filename, 0644);
			File::delete($filename);
		}
	}

	protected function createAlias($domain, $alias)
	{
		if (empty($alias))
		{
			return;
		}

		$filename = MightysitesHelper::getConfigFilename($alias);

		if (file_exists($filename))
		{
			@chmod($filename, 0644);
			File::delete($filename);
		}

		$str = '<?php ' . "\n"
			. 'define(\'MIGHTY_DOMAIN\', \'' . MightysitesHelper::prepareDomain($domain) . '\');' . "\n"
			. 'define(\'MIGHTY_CONFIG\', __DIR__ . \'/configuration_' . MightysitesHelper::prepareDomain($domain) . '.php\');' . "\n"
			. 'require_once MIGHTY_CONFIG;';

		if (!File::write($filename, $str))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_ALIAS_WRITE_FAILED', $filename, $alias), 'error');
		}
	}

	/**
	 * Build the regex arrays used by the database driver to rewrite queries.
	 */
	protected function createTablesReplacements(&$site, $tables = array())
	{
		$params  = $site->params;
		$content = new Registry($params->get('content', array()));

		$data   = array();
		$tables = array();

		// Core extensions
		if ($content->get('extensions'))
		{
			$tables['extensions']              = $content->get('extensions');
			$tables['updates']                 = $content->get('extensions');
			$tables['update_categories']       = $content->get('extensions');
			$tables['update_sites']            = $content->get('extensions');
			$tables['update_sites_extensions'] = $content->get('extensions');

			// Special script since 3.7.
			$data['/SELECT m\.id, m\.title, m\.alias, m\.link, m\.parent_id, m\.img, e\.element, m\.menutype(\s+)FROM #__menu AS m/u'] =
				'SELECT m.id, m.title, m.alias, m.link, m.parent_id, m.img, e.element, m.menutype$1FROM ' . $this->_createTableReplacements($site, 'menu', $content->get('extensions')) . ' AS m';
		}

		if ($content->get('permissions'))
		{
			$tables['assets'] = $content->get('permissions');
		}

		if ($content->get('users'))
		{
			$tables['usergroups']         = $content->get('users');
			$tables['users']              = $content->get('users');
			$tables['user_notes']         = $content->get('users');
			$tables['user_profiles']      = $content->get('users');
			$tables['user_usergroup_map'] = $content->get('users');
			$tables['user_keys']          = $content->get('users');
			$tables['viewlevels']         = $content->get('users');
		}

		if ($content->get('sessions'))
		{
			$tables['session'] = $content->get('sessions');
		}

		if ($content->get('templates'))
		{
			$tables['template_styles'] = $content->get('templates');
		}

		if ($content->get('categories'))
		{
			$tables['categories'] = $content->get('categories');
		}

		if ($content->get('content'))
		{
			$tables['content']            = $content->get('content');
			$tables['content_frontpage']  = $content->get('content');
			$tables['content_rating']     = $content->get('content');
			$tables['content_types']      = $content->get('content');
			$tables['ucm_base']           = $content->get('content');
			$tables['ucm_content']        = $content->get('content');
			$tables['ucm_history']        = $content->get('content');
		}

		if ($content->get('languages'))
		{
			$tables['languages'] = $content->get('languages');
			$tables['overrider'] = $content->get('languages');
		}

		if ($content->get('menus'))
		{
			$tables['menu']         = $content->get('menus');
			$tables['menu_types']   = $content->get('menus');
			$tables['associations'] = $content->get('menus');
		}

		if ($content->get('modules'))
		{
			$tables['modules']      = $content->get('modules');
			$tables['modules_menu'] = $content->get('modules');
		}

		if ($content->get('newsfeeds'))
		{
			$tables['newsfeeds'] = $content->get('newsfeeds');
		}

		if ($content->get('weblinks'))
		{
			$tables['weblinks'] = $content->get('weblinks');
		}

		if ($content->get('banners'))
		{
			$tables['banners']        = $content->get('banners');
			$tables['banner_clients'] = $content->get('banners');
			$tables['banner_tracks']  = $content->get('banners');
		}

		if ($content->get('contacts'))
		{
			$tables['contact_details'] = $content->get('contacts');
		}

		if ($content->get('messages'))
		{
			$tables['messages']     = $content->get('messages');
			$tables['messages_cfg'] = $content->get('messages');
		}

		if ($content->get('tags'))
		{
			$tables['tags'] = $content->get('tags');
		}

		if ($content->get('tags_refs'))
		{
			$tables['contentitem_tag_map'] = $content->get('tags_refs');
		}

		if ($content->get('redirect'))
		{
			$tables['redirect_links'] = $content->get('redirect');
		}

		// 3rd-party extensions (table prefix regex replacements)
		$prefix_rules = array(
			'smartsearch'         => 'finder_',
			'js'                  => 'community_',
			'cb'                  => 'comprofiler',
			'mtree'               => 'mt_',
			'sobi2'               => 'sobi2_',
			'sobipro'             => 'sobipro_',
			'gridiron'            => 'gridiron_',
			'uddeim'              => 'uddeim',
			'zoo'                 => 'zoo_',
			'dtregister'          => 'dtregister',
			'easyblog'            => 'easyblog_',
			'joomsport'           => 'bl_',
			'rsform'              => 'rsform_',
			'rsfiles'             => 'rsfiles_',
			'acymailing'          => 'acymailing_',
			'adsmanager'          => 'adsmanager_',
			'joomshopping'        => 'jshopping_',
			'kunena'              => 'kunena_',
			'k2'                  => 'k2_',
			'pf'                  => 'pf_',
			'jcp'                 => 'jcp_',
			'enmasse'             => 'enmasse_',
			'phocadownload'       => 'phocadownload',
			'phocagallery'        => 'phocagallery',
			'jreviews'            => 'jreviews_',
			'virtuemart'          => 'virtuemart',
			'jevents'             => 'jevents_',
			'hwdms'               => 'hwdms_',
			'admintools'          => 'admintools_',
			'ak'                  => 'ak_',
			'akeebasubs'          => 'akeebasubs_',
			'feedgator'           => 'feedgator',
			'invitex'             => 'invitex_',
			'jbolo'               => 'jbolo',
			'jxtc_appbook'        => 'jxtc_appbook_',
			'jxtc_albumplayer'    => 'jxtc_albumplayer',
			'geo'                 => 'geo_',
			'jxtc_powertabs'      => 'jxtc_powertabs',
			'payplans'            => 'payplans_',
			'ad'                  => 'ad_',
			'locator'             => 'location_',
			'pin'                 => 'pin_',
			'hikashop'            => 'hikashop_',
			'docman'              => 'docman',
			'swmenu'              => 'swmenu_',
			'virtueuploads'       => 'virtueuploads',
			'djcf'                => 'djcf_',
			'eventlist'           => 'eventlist_',
			'fpss'                => 'fpss_',
			'imageshow'           => 'imageshow_',
			'uniform'             => 'jsn_uniform_',
			'listbingo'           => 'listbingo_',
			'redshop'             => 'redshop_',
			'komento'             => 'komento_',
			'securitycheck'       => 'securitycheck',
			'expautos'            => 'expautos_',
			'mijosearch'          => 'mijosearch_',
			'magebridge'          => 'magebridge_',
			'osmembership'        => 'osmembership_',
			'mijosef'             => 'mijosef_',
			'osemsc'              => 'osemsc_',
			'easydiscuss'         => 'discuss_',
			'quiz'                => 'quiz_',
			'muscol'              => 'muscol_',
			'allvideoshare'       => 'allvideoshare_',
			'fua'                 => 'fua_',
			'joaktree'            => 'joaktree_',
			'rsticketspro'        => 'rsticketspro_',
			'acesef'              => 'acesef_',
			'aup'                 => 'alpha_userpoints',
			'vq'                  => 'vq_',
			'spidercalendar'      => 'spidercalendar_',
			'jcomments'           => 'jcomments',
			'mem'                 => 'mem_',
			'paidsystem'          => 'paidsystem_',
			'hikamarket'          => 'hikamarket_',
			'rsmembership'        => 'rsmembership_',
			'jfbconnect'          => 'jfbconnect_',
			'adagency'            => 'ad_agency_',
			'fw_realestate'       => 'fw_realestate_',
			'iproperty'           => 'iproperty',
			'mijoshop'            => 'mijoshop_',
			'zhgooglemap'         => 'zhgooglemap_',
			'cddir'               => 'cddir_',
			'ajaxregister'        => 'ajaxregister_',
			'jmap'                => 'jmap',
			'emerald'             => 'emerald_',
			'rseventspro'         => 'rseventspro_',
			'jblance'             => 'jblance_',
			'j2store'             => 'j2store_',
			'lovefactory'         => 'lovefactory_',
			'social'              => 'social_',
			'hdwplayer'           => 'hdwplayer_',
			'jomcl'               => 'jomcl_',
			'regreminder'         => 'regreminder',
			'comment'             => 'comment',
			'xtdir'               => 'xtdir_',
			'offlajn'             => 'offlajn_',
			'jobsfactory'         => 'jobsfactory_',
			'eshop'               => 'eshop_',
			'flexicontent'        => 'flexicontent_',
			'cmgroupbuying'       => 'cmgroupbuying_',
			'joomgallery'         => 'joomgallery',
			'joomgalaxy'          => 'joomgalaxy_',
			'jdownloads'          => 'jdownloads_',
			'icagenda'            => 'icagenda',
			'booking'             => 'booking_',
			'cobalt'              => 'js_res_',
			'vikappointments'     => 'vikappointments_',
			'joomcareer'          => 'joomcareer',
			'kart'                => 'kart_',
			'tjfields'            => 'tjfields_',
			'guru'                => 'guru_',
			'vminvoice'           => 'vminvoice_',
			'onepage'             => 'onepage_',
			'breezingforms'       => 'facileforms_',
			'vikbooking'          => 'vikbooking_',
			'kandanda'            => 'kandanda_',
			'upgflickrsuite'      => 'upgflickrsuite',
			'fieldsattach'        => 'fieldsattach',
			'sh404sef'            => 'sh404sef_',
			'matukio'             => 'matukio',
			'lmsking'             => 'lk_',
			'judownload'          => 'judownload_',
			'jgive'               => 'jg_',
			'form2content'        => 'f2c_',
			'jticketing'          => 'jticketing_',
			'falang'              => 'falang_',
			'asurveys'            => 'asurveys_',
			'youtubegallery'      => 'youtubegallery_',
			'dpcalendar'          => 'dpcalendar_',
			'contentbuilder'      => 'contentbuilder_',
			'communityanswers'    => 'answers_',
			'cmlivedeal'          => 'cmlivedeal_',
			'creativecontactform' => 'creative_',
			'fst'                 => 'fst_',
			'fsj_faqs'            => 'fsj_faqs_',
			'fsf_faq'             => 'fsf_faq_',
			'jcalpro'             => 'jcalpro_',
			'lifestream'          => 'lifestream_',
			'eb'                  => 'eb_',
			'convertforms'        => 'convertforms',
			'jsn'                 => 'jsnsocial_',
		);

		foreach ($prefix_rules as $key => $table_prefix)
		{
			if ($content->get($key))
			{
				$data['/`?#__' . $table_prefix . '([0-9a-z_]*)`?/u'] = '`' . $this->_createTableReplacements($site, $table_prefix, $content->get($key), '') . '$1`';
			}
		}

		// Extra "no config" variants (exclude config tables).
		$no_config_rules = array(
			'js_no_config'       => 'community',
			'easyblog_no_config' => 'easyblog',
			'virtuemart_no_config' => 'virtuemart',
			'hwdms_no_config'    => 'hwdms',
			'komento_no_config'  => 'komento',
			'social_no_config'   => 'social',
			'kart_no_config'     => 'kart',
		);

		foreach ($no_config_rules as $key => $table_prefix)
		{
			if ($content->get($key))
			{
				$data['/(`?)(\#__' . $table_prefix . ')((?!_config)(?!_configs)(?!_store)[^0-9a-z_]*)/u'] =
					'$1' . str_replace('`.`', '$1.$1', $this->_createTableReplacements($site, $table_prefix, $content->get($key), '')) . '$4';
			}
		}

		// Extra tables for specific extensions.
		if ($content->get('jce'))
		{
			$tables['wf_profiles'] = $content->get('jce');
		}

		if ($content->get('jxtc_ezimg'))
		{
			$tables['jxtc_ezimg_images'] = $content->get('jxtc_ezimg');
		}

		if ($content->get('invitex'))
		{
			$tables['techjoomlaAPI_users'] = $content->get('invitex');
		}

		if ($content->get('locator'))
		{
			$tables['locations'] = $content->get('locator');
		}

		if ($content->get('chronoforms'))
		{
			$tables['chronoforms']        = $content->get('chronoforms');
			$tables['chronoform_actions'] = $content->get('chronoforms');
		}

		if ($content->get('imageshow'))
		{
			$tables['jsn_imageshow_messages'] = $content->get('imageshow');
			$tables['jsn_imageshow_config']   = $content->get('imageshow');
		}

		if ($content->get('widgetkit'))
		{
			$tables['widgetkit_widget'] = $content->get('widgetkit');
		}

		if ($content->get('bfstop'))
		{
			$tables['bfstop_failedlogin'] = $content->get('bfstop');
			$tables['bfstop_bannedip']    = $content->get('bfstop');
		}

		if ($content->get('jsn_poweradmin'))
		{
			$tables['jsn_poweradmin_history']     = $content->get('jsn_poweradmin');
			$tables['jsn_poweradmin_config']      = $content->get('jsn_poweradmin');
			$tables['jsn_poweradmin_menu_assets'] = $content->get('jsn_poweradmin');
		}

		if ($content->get('advancedmodules'))
		{
			$tables['advancedmodules'] = $content->get('advancedmodules');
		}

		if ($content->get('jck'))
		{
			$tables['jckplugins']         = $content->get('jck');
			$tables['jcktoolbars']        = $content->get('jck');
			$tables['jcktoolbarplugins']  = $content->get('jck');
			$tables['update_jckplugins']  = $content->get('jck');
		}

		if ($content->get('rereplacer'))
		{
			$tables['rereplacer'] = $content->get('rereplacer');
		}

		if ($content->get('preachit'))
		{
			foreach (array('pibooks', 'picomments', 'pifilepath', 'pimime', 'pipodcast', 'piseries', 'piministry', 'piteachers', 'pistudies', 'pimediaplayers', 'pibckadmin', 'pitemplates', 'pipodmes', 'piadminpodmes', 'pibiblevers') as $pitable)
			{
				$tables[$pitable] = $content->get('preachit');
			}
		}

		if ($content->get('osemsc'))
		{
			$tables['ose_activation'] = $content->get('osemsc');
		}

		if ($content->get('hsusers'))
		{
			$tables['users_authentications'] = $content->get('hsusers');
			$tables['users_extended']        = $content->get('hsusers');
		}

		if ($content->get('snippets'))
		{
			$tables['snippets'] = $content->get('snippets');
		}

		if ($content->get('xmap'))
		{
			$tables['xmap_items']   = $content->get('xmap');
			$tables['xmap_sitemap'] = $content->get('xmap');
		}

		if ($content->get('auctionfactory'))
		{
			$tables['bids'] = $content->get('auctionfactory');
		}

		if ($content->get('vmvendor'))
		{
			$tables['vmvendor_vendorratings'] = $content->get('vmvendor');
		}

		if ($content->get('jxtc_readinglist'))
		{
			$tables['jxtc_readinglist'] = $content->get('jxtc_readinglist');
		}

		if ($content->get('kart') || $content->get('kart_no_config'))
		{
			$tables['order_item_fee']    = $content->get('kart') ?: $content->get('kart_no_config');
			$tables['q2c_order_history'] = $content->get('kart') ?: $content->get('kart_no_config');
		}

		if ($content->get('tjfields'))
		{
			$tables['tj_city']    = $content->get('tjfields');
			$tables['tj_country'] = $content->get('tjfields');
			$tables['tj_region']  = $content->get('tjfields');
		}

		if ($content->get('jticketing'))
		{
			$tables['Stripe_xref']     = $content->get('jticketing');
			$tables['tjlms_user_xref'] = $content->get('jticketing');
		}

		if ($content->get('jmapmyldap'))
		{
			$tables['sh_ldap_config'] = $content->get('jmapmyldap');
			$tables['sh_config']      = $content->get('jmapmyldap');
		}

		if ($content->get('creativecontactform'))
		{
			foreach (array('creative_forms', 'creative_fields', 'creative_field_types', 'creative_form_options', 'contact_templates', 'creative_submissions') as $ctable)
			{
				$tables[$ctable] = $content->get('creativecontactform');
			}
		}

		if ($content->get('mytestimonials'))
		{
			$tables['mytestimonials_testimonial'] = $content->get('mytestimonials');
		}

		if ($content->get('mymaplocations'))
		{
			$tables['mymaplocations_location'] = $content->get('mymaplocations');
		}

		if ($content->get('rstbox'))
		{
			$tables['rstbox']      = $content->get('rstbox');
			$tables['rstbox_logs'] = $content->get('rstbox');
		}

		if ($content->get('breezingforms'))
		{
			$tables['breezingforms'] = $content->get('breezingforms');
		}

		// Apply 3rd-party plugins.
		Factory::getApplication()->triggerEvent('onMightysitesCreateReplacements', array($params, $content, $site, &$data, &$tables, $this));

		// Custom tables
		foreach ($params->toArray() as $table => $from)
		{
			if (substr($table, 0, 6) === 'table_' && $from)
			{
				$tables[substr($table, 6)] = $from;
			}
		}

		// Tables
		if (count($tables))
		{
			foreach ($tables as $table => $from)
			{
				if ($from)
				{
					$data['/`?#__' . $table . '`?([\., \n\)]+)/u'] = '`' . $this->_createTableReplacements($site, $table, $from, '') . '`$1';
				}
			}
		}

		// Always load mighty from root
		$data['/`?#__mightysites[` \n]+/u'] = $this->_createTableReplacements($site, 'mightysites', 1);

		// Remove empty data
		foreach ($data as $key => $value)
		{
			if (empty($value))
			{
				unset($data[$key]);
			}
		}

		return count($data) ? array(array_keys($data), array_values($data)) : array();
	}

	public function _createTableReplacements($site, $table, $from, $enclosure = '`')
	{
		$from = MightysitesHelper::getSite($from, true);

		if ($from)
		{
			$ending = $enclosure ? ' ' : '';

			if ($site->db == $from->db)
			{
				return $enclosure . $from->dbprefix . $table . $enclosure . $ending;
			}

			return $enclosure . $from->db . '`.`' . $from->dbprefix . $table . $enclosure . $ending;
		}

		return $table;
	}
}
