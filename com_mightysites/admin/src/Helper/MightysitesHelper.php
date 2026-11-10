<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

/**
 * MightySites helper (Joomla 6 port).
 */
abstract class MightysitesHelper
{
	/**
	 * Cached db objects per site id.
	 */
	private static $dbo;

	public static function getHost()
	{
		static $domain;

		if (!$domain)
		{
			$domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
			$domain = (substr($domain, 0, 4) === 'www.') ? substr($domain, 4) : $domain;

			if (strpos($domain, ':') !== false)
			{
				list($domain, $tmp) = explode(':', $domain);
			}
		}

		return $domain;
	}

	/**
	 * Returns list of overloadable data sources.
	 */
	public static function getSynchs($type = null)
	{
		$custom = array(
			'js', 'cb', 'mtree', 'sobi2', 'sobipro', 'gridiron', 'uddeim', 'zoo', 'dtregister',
			'easyblog', 'easyblog_no_config', 'joomsport', 'rsform', 'rsfiles', 'acymailing',
			'jce', 'adsmanager', 'joomshopping', 'kunena', 'k2', 'pf', 'jcp', 'enmasse',
			'phocadownload', 'phocagallery', 'jreviews', 'virtuemart', 'virtuemart_no_config',
			'jevents', 'hwdms', 'hwdms_no_config', 'admintools', 'ak', 'akeebasubs', 'feedgator',
			'jxtc_ezimg', 'invitex', 'jbolo', 'jxtc_appbook', 'jxtc_albumplayer', 'geo',
			'jxtc_powertabs', 'payplans', 'jxtc_readinglist', 'ad', 'locator', 'pin',
			'hikashop', 'docman', 'swmenu', 'virtueuploads', 'djcf', 'chronoforms',
			'eventlist', 'fpss', 'imageshow', 'uniform', 'widgetkit', 'listbingo', 'redshop',
			'jvle', 'bfstop', 'komento', 'komento_no_config', 'jsn_poweradmin', 'securitycheck',
			'expautos', 'mijosearch', 'advancedmodules', 'jck', 'rereplacer', 'magebridge',
			'preachit', 'osmembership', 'mijosef', 'osemsc', 'easydiscuss', 'hsusers', 'quiz',
			'muscol', 'allvideoshare', 'fua', 'joaktree', 'rsticketspro', 'acesef', 'aup', 'vq',
			'spidercalendar', 'jcomments', 'mem', 'paidsystem', 'hikamarket', 'rsmembership',
			'jfbconnect', 'adagency', 'fw_realestate', 'iproperty', 'mijoshop', 'snippets',
			'zhgooglemap', 'cddir', 'ajaxregister', 'jmap', 'emerald', 'rseventspro', 'jblance',
			'xmap', 'j2store', 'js_no_config', 'lovefactory', 'social', 'social_no_config',
			'auctionfactory', 'hdwplayer', 'jomcl', 'regreminder', 'flexicontact', 'comment',
			'xtdir', 'offlajn', 'jobsfactory', 'vmvendor', 'eshop', 'flexicontent',
			'cmgroupbuying', 'joomgallery', 'joomgalaxy', 'jdownloads', 'jsn', 'icagenda',
			'booking', 'cobalt', 'vikappointments', 'joomcareer', 'kart', 'kart_no_config',
			'tjfields', 'guru', 'vminvoice', 'onepage', 'breezingforms', 'vikbooking',
			'kandanda', 'upgflickrsuite', 'fieldsattach', 'jmapmyldap', 'sh404sef', 'matukio',
			'lmsking', 'judownload', 'jgive', 'form2content', 'jticketing', 'falang',
			'asurveys', 'youtubegallery', 'dpcalendar', 'rstbox', 'contentbuilder',
			'communityanswers', 'cmlivedeal', 'creativecontactform', 'fst', 'fsj_faqs',
			'fsf_faq', 'jcalpro', 'lifestream', 'eb', 'convertforms', 'mytestimonials',
			'mymaplocations',
		);

		// Apply 3rd-party plugins.
		Factory::getApplication()->triggerEvent('onMightysitesGetSynchs', array(&$custom));

		// Sort by name.
		$custom2 = array();

		foreach ($custom as $key)
		{
			$custom2[$key] = Text::_('COM_MIGHTYSITES_SYNCH_LABEL_' . $key);
		}

		natcasesort($custom2);
		$custom = array_keys($custom2);

		$core = array(
			'extensions', 'permissions', 'users', 'templates', 'categories', 'content',
			'languages', 'menus', 'modules', 'newsfeeds', 'weblinks', 'banners', 'contacts',
			'messages', 'smartsearch', 'tags', 'tags_refs', 'redirect',
		);
		asort($core);

		switch ($type)
		{
			case 'custom':
				return $custom;
			case 'core':
				return $core;
			default:
				return array_merge($core, $custom);
		}
	}

	/**
	 * In fact returns all Sites & Databases.
	 */
	public static function getSites($key = 'domain')
	{
		static $sites;

		if (!isset($sites[$key]))
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__mightysites'))
				->order($db->quoteName('domain'));
			$db->setQuery($query);

			$sites[$key] = $db->loadObjectList($key);

			foreach ($sites[$key] as &$site)
			{
				// Prepare domain cmd.
				$site->domain_cmd = preg_replace('#([^A-Z0-9])#i', '_', $site->domain);
				// Params.
				$site->params = new Registry($site->params);
			}
		}

		return $sites[$key];
	}

	/**
	 * In fact returns a Site or Database.
	 */
	public static function getSite($id, $attach_config = false)
	{
		$sites = self::getSites();

		// By domain.
		if (isset($sites[$id]))
		{
			return $attach_config ? self::attachConfig($sites[$id]) : $sites[$id];
		}

		// By id or parsed domain.
		foreach ($sites as $site)
		{
			if ($id == $site->id || $id == $site->domain_cmd)
			{
				return $attach_config ? self::attachConfig($site) : $site;
			}
		}

		return false;
	}

	public static function getCurrentSite($attach_config = false)
	{
		return self::getSite(defined('MIGHTY_DOMAIN') ? MIGHTY_DOMAIN : self::getHost(), $attach_config);
	}

	public static function sitesList($name, $value = null, $script = null, $skip = null, $first = null, $type = false, $first_value = '', $second = null, $second_value = null)
	{
		$options = array();

		if ($first)
		{
			$options[] = HTMLHelper::_('select.option', $first_value, $first);
		}

		if ($second)
		{
			$options[] = HTMLHelper::_('select.option', $second_value, $second);
		}

		$sites = self::getSites();

		foreach ($sites as $site)
		{
			if (empty($skip) || ($skip != $site->id))
			{
				if ($type && $site->type != $type)
				{
					continue;
				}

				$options[] = HTMLHelper::_('select.option', $site->id, $site->domain);
			}
		}

		return HTMLHelper::_(
			'select.genericlist',
			$options,
			$name,
			array('class' => 'form-select w-auto', 'onchange' => $script),
			'value',
			'text',
			$value
		);
	}

	public static function getDatabases($key = 'id')
	{
		static $databases;

		if (!isset($databases[$key]))
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__mightysites'))
				->order($db->quoteName('domain'));
			$db->setQuery($query);
			$databases[$key] = $db->loadObjectList($key);
		}

		return $databases[$key];
	}

	public static function databasesList($name, $value = null, $script = null, $skip = array('information_schema'))
	{
		$options = array();
		$options[] = HTMLHelper::_('select.option', '', Text::_('COM_MIGHTYSITES_SELECT_DATABASE'));

		$db = Factory::getDbo();
		$db->setQuery('SHOW DATABASES');
		$rows = $db->loadColumn();

		if (count($rows))
		{
			foreach ($rows as $row)
			{
				if (!in_array($row, $skip))
				{
					$options[] = HTMLHelper::_('select.option', $row, $row);
				}
			}
		}

		return HTMLHelper::_('select.genericlist', $options, $name, array('class' => 'form-select w-auto'), 'value', 'text', $value);
	}

	public static function menuitemsList($name, $value, $script = null)
	{
		$types = array('default' => array(HTMLHelper::_('select.option', '', Text::_('COM_MIGHTYSITES_DEFAULT_MENUITEM'))));

		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select(array('id', 'menutype', 'title', 'level'))
			->from($db->quoteName('#__menu'))
			->where('client_id=0')
			->where('id > 1')
			->where('published=1')
			->order('lft');
		$db->setQuery($query);

		foreach ($db->loadObjectList() as $item)
		{
			if (!isset($types[$item->menutype]))
			{
				$types[$item->menutype] = array();
			}

			$types[$item->menutype][] = HTMLHelper::_('select.option', $item->id, str_repeat(' - ', $item->level - 1) . $item->title);
		}

		return HTMLHelper::_(
			'select.groupedlist',
			$types,
			$name,
			array(
				'list.attr'          => array('class' => 'form-select w-auto'),
				'id'                 => $name,
				'list.select'        => $value,
				'group.items'        => null,
				'option.key.toHtml'  => false,
				'option.text.toHtml' => false,
			)
		);
	}

	public static function prepareDomain($domain = null)
	{
		if (!$domain)
		{
			$domain = self::getHost();
		}

		if (substr($domain, 0, 4) === 'www.')
		{
			$domain = substr($domain, 4);
		}

		// Use Punycode. Note: idna-convert rejects characters like '_' which
		// appear in normalized filenames, so only convert non-ASCII domains.
		if (preg_match('#[^A-Za-z0-9\.\-]#', $domain))
		{
			try
			{
				$domain = \Joomla\CMS\String\PunycodeHelper::toPunycode($domain);
			}
			catch (\Throwable $e)
			{
				// Keep the raw string; it gets normalized below anyway.
			}
		}

		// Normalize filename.
		$domain = preg_replace('#([^A-Za-z0-9])#i', '_', $domain);

		return $domain;
	}

	public static function getConfigFilename($domain = null, $force_root = false)
	{
		// Current config.
		if (!$domain)
		{
			if (defined('MIGHTY_CONFIG'))
			{
				return MIGHTY_CONFIG;
			}

			$domain = self::getHost();
		}

		$fname = JPATH_SITE . '/configuration_' . self::prepareDomain($domain) . '.php';

		if (!file_exists($fname) && !$force_root)
		{
			$fname = JPATH_SITE . '/components/com_mightysites/configuration/configuration_' . self::prepareDomain($domain) . '.php';
		}

		return $fname;
	}

	/**
	 * Load domain configuration file and attach its values to the row.
	 *
	 * @param   object  $row     Site row (modified in place).
	 * @param   mixed   $config  Already loaded config string/object.
	 * @param   bool    $quiet   Do not enqueue any message (list views).
	 */
	public static function attachConfig(&$row, $config = null, $quiet = false)
	{
		$row->config_missing = false;
		$row->config_file    = null;

		if ($row->type == 1)
		{
			// Load as file into string.
			if (!$config)
			{
				$fname = self::getConfigFilename($row->domain);

				$row->config_file = $fname;

				if (file_exists($fname))
				{
					$config = @file_get_contents($fname);
				}
				else
				{
					// Auto-heal: only the CURRENT domain can be rebuilt from the
					// live configuration - other domains' database credentials
					// are unknown and must not be guessed.
					$reason = 'unknown';

					if (self::createConfigFromLive($row->domain, $reason) && file_exists($fname))
					{
						$config = @file_get_contents($fname);
					}
					else
					{
						$row->config_missing = true;

						if (!$quiet)
						{
							self::explainMissingConfig($row, $fname, $reason);
						}

						return $row;
					}
				}
			}

			// Parse into object.
			if (is_string($config))
			{
				$classname = 'JConfig' . $row->id;

				if (!class_exists($classname))
				{
					$config = strtr($config, array(
						'<?php' . "\n"   => "\n",
						'<?php' . "\r\n" => "\r\n",
						'<?php' . "\r"   => "\r",
						'class JConfig'  => 'class ' . $classname,
					));

					try
					{
						eval($config);
					}
					catch (\Throwable $e)
					{
						Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_FILE_DOESNT_EXIST', (string) $row->config_file) . ' (' . $e->getMessage() . ')', 'error');
					}
				}

				if (class_exists($classname))
				{
					$config = new $classname;
				}
			}

			// Parse object.
			if (is_object($config))
			{
				foreach ((array) $config as $key => $value)
				{
					$row->$key = $value;
				}

				$row->published = !@$row->offline;
			}
		}

		return $row;
	}

	/**
	 * Rebuild the per-domain configuration file of the CURRENT domain from the
	 * live global configuration. Only possible for the current domain because
	 * other domains' credentials are unknown.
	 *
	 * @param   string  $domain  Domain to rebuild.
	 * @param   string  $reason  Receives the exact failure reason.
	 */
	public static function createConfigFromLive($domain, &$reason = null)
	{
		$reason = 'unknown';

		$file = self::getConfigFilename($domain);

		if (file_exists($file))
		{
			$reason = 'exists';

			return true;
		}

		$dir = dirname($file);

		if (!is_dir($dir) && !@mkdir($dir, 0755, true))
		{
			$reason = 'dir_missing';

			return false;
		}

		if (!is_writable($dir))
		{
			$reason = 'dir_not_writable';

			return false;
		}

		// We can only reconstruct the configuration of the current domain.
		if (self::prepareDomain($domain) !== self::prepareDomain(self::getHost()))
		{
			$reason = 'domain_mismatch';

			return false;
		}

		try
		{
			$config = Factory::getConfig();
		}
		catch (\Throwable $e)
		{
			$reason = 'no_live_config';

			return false;
		}

		$registry = new Registry($config->toArray());
		$php      = $registry->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));

		if (!$php || !File::write($file, $php))
		{
			$reason = 'write_failed';

			return false;
		}

		@chmod($file, 0444);

		$reason = 'ok';

		return true;
	}

	/**
	 * Report why a site configuration file is missing, with the real reason
	 * (permissions vs. domain mismatch) instead of a generic hint.
	 */
	protected static function explainMissingConfig($row, $fname, $reason)
	{
		$app = Factory::getApplication();

		switch ($reason)
		{
			case 'dir_not_writable':
			case 'dir_missing':
			case 'write_failed':
				$app->enqueueMessage(
					Text::sprintf('COM_MIGHTYSITES_ERR_CONFIG_DIR_NOT_WRITABLE', dirname($fname)),
					'error'
				);
				break;

			case 'domain_mismatch':
				$app->enqueueMessage(
					Text::sprintf('COM_MIGHTYSITES_WARN_CONFIG_DOMAIN_MISMATCH', $row->domain, self::getHost(), $fname),
					'warning'
				);
				break;

			default:
				$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_NO_CONFIG', $fname, $row->domain), 'warning');
				break;
		}
	}

	/**
	 * Build a configuration file body for a site row: the live configuration
	 * is used as a template, while the database name/prefix are taken from the
	 * site row itself (#__mightysites.db / .dbprefix).
	 *
	 * @param   object  $row    Site row.
	 * @param   string  $error  Receives the failure reason.
	 *
	 * @return  string|false
	 */
	public static function buildConfigFromRow($row, &$error = null)
	{
		$error = null;

		if (!is_object($row) || trim((string) $row->db) === '')
		{
			$error = 'no_db';

			return false;
		}

		try
		{
			$values = Factory::getConfig()->toArray();
		}
		catch (\Throwable $e)
		{
			$error = 'no_live_config';

			return false;
		}

		$values['db'] = trim((string) $row->db);

		$prefix = trim((string) $row->dbprefix);

		if ($prefix !== '')
		{
			$values['dbprefix'] = $prefix;
		}

		$registry = new Registry($values);

		return $registry->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));
	}

	/**
	 * Write a per-domain configuration file (creating its folder if needed).
	 *
	 * @param   string  $domain   Domain.
	 * @param   string  $content  File body.
	 * @param   string  $error    Receives the failure reason.
	 *
	 * @return  string|false  Written file name.
	 */
	public static function writeConfigFile($domain, $content, &$error = null)
	{
		$error = null;

		$fname = self::getConfigFilename($domain);
		$dir   = dirname($fname);

		if (!is_dir($dir) && !@mkdir($dir, 0755, true))
		{
			$error = 'dir_missing';

			return false;
		}

		if (!is_writable($dir) && !(file_exists($fname) && is_writable($fname)))
		{
			$error = 'dir_not_writable';

			return false;
		}

		if (file_exists($fname))
		{
			@chmod($fname, 0644);
		}

		if (!$content || !File::write($fname, $content))
		{
			$error = 'write_failed';

			return false;
		}

		@chmod($fname, 0444);

		return $fname;
	}

	/**
	 * Rebuild every missing per-domain configuration file from the site rows.
	 *
	 * @return  array  array('ok' => int, 'skipped' => int, 'failed' => array())
	 */
	public static function rebuildMissingConfigs()
	{
		$result = array('ok' => 0, 'skipped' => 0, 'failed' => array());

		$db = Factory::getDbo();

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__mightysites'))
			->where($db->quoteName('type') . ' = 1');
		$db->setQuery($query);

		foreach ((array) $db->loadObjectList() as $row)
		{
			$fname = self::getConfigFilename($row->domain);

			if (file_exists($fname))
			{
				$result['skipped']++;
				continue;
			}

			// Preferred: rebuild from the row's own database name/prefix.
			$error   = null;
			$content = self::buildConfigFromRow($row, $error);

			if ($content)
			{
				if (self::writeConfigFile($row->domain, $content, $error))
				{
					$result['ok']++;
				}
				else
				{
					$result['failed'][] = $row->domain . ' (' . $error . ')';
				}

				continue;
			}

			// Fallback: the current domain can be rebuilt from the live config.
			$reason = '';

			if (self::createConfigFromLive($row->domain, $reason))
			{
				$result['ok']++;
				continue;
			}

			$result['failed'][] = $row->domain . ' (' . ($error ?: $reason) . ')';
		}

		return $result;
	}

	/**
	 * Ensures that configuration.php is patched (3-line bootstrap) and that
	 * default.php exists. If configuration.php contains a full JConfig class
	 * (e.g. after a Global Configuration save), its values are absorbed into
	 * the current domain config file first.
	 */
	public static function patchConfiguration()
	{
		$app = null;

		try
		{
			$app = Factory::getApplication();
		}
		catch (\Throwable $e)
		{
		}

		// Update/create default config.
		$default_config_file = JPATH_SITE . '/components/com_mightysites/configuration/default.php';

		// Reload master domain from DB.
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('domain')
			->from($db->quoteName('#__mightysites'))
			->where('id=1');
		$db->setQuery($query, 0, 1);
		$master_domain = $db->loadResult();

		if (!$master_domain)
		{
			return false;
		}

		if (!file_exists($default_config_file)
			|| (strpos((string) @file_get_contents($default_config_file), self::getConfigFilename($master_domain)) === false))
		{
			// Use the actual resolved config path (root file preferred, then our folder),
			// so the check above stays consistent and no rewrite loop can happen.
			$mighty_config_path = self::getConfigFilename($master_domain);

			$default_config =
				'<?php' . "\n" .
				'define(\'MIGHTY_DOMAIN\', \'' . self::prepareDomain($master_domain) . '\');' . "\n" .
				'define(\'MIGHTY_CONFIG\', \'' . $mighty_config_path . '\');' . "\n" .
				'require_once MIGHTY_CONFIG;';

			if (!File::write($default_config_file, $default_config))
			{
				if ($app)
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_CANT_WRITE_FILE', $default_config_file), 'error');
				}

				return false;
			}
		}

		// Get our params.
		$params = ComponentHelper::getParams('com_mightysites');

		// Update Joomla config.
		$file = JPATH_CONFIGURATION . '/configuration.php';
		$str  = '<?php require_once __DIR__.\'/components/com_mightysites/mightysites.php\';';

		$contents = @file_get_contents($file);

		if ($contents === false)
		{
			return false;
		}

		// Global configuration was saved (or written manually) and contains a full JConfig class:
		// absorb its values into the current domain configuration file first.
		if (strpos($contents, 'class JConfig') !== false)
		{
			self::absorbConfiguration($contents);
		}

		$contents = @file_get_contents($file);

		if (strpos($contents, $str) !== 0)
		{
			// Load our language, it can be missed if we call method in com_config.
			Factory::getLanguage()->load('com_mightysites', JPATH_ADMINISTRATOR . '/components/com_mightysites');

			@chmod($file, 0644);

			// Add dummy code for Varnish cache.
			if ($params->get('varnish'))
			{
				$masterFile = JPATH_SITE . '/components/com_mightysites/configuration/configuration_' . self::prepareDomain($master_domain) . '.php';

				if (file_exists($masterFile))
				{
					$str .= "\n/*\n" . (string) @file_get_contents($masterFile) . "\n*/";
				}
			}

			if (File::write($file, $str))
			{
				@chmod($file, 0444);
			}
			else
			{
				if ($app)
				{
					$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_ERROR_CONFIG_PATCH_FAILED', $file), 'error');
				}

				return false;
			}
		}

		return true;
	}

	/**
	 * Absorb a freshly written configuration.php (full JConfig class) into the
	 * per-domain configuration file of the current (master) domain, preserving
	 * our mighty_* custom properties that com_config doesn't know about.
	 */
	public static function absorbConfiguration($new_contents = null)
	{
		$file = JPATH_CONFIGURATION . '/configuration.php';

		if ($new_contents === null)
		{
			$new_contents = @file_get_contents($file);
		}

		if (!$new_contents || strpos($new_contents, 'class JConfig') === false)
		{
			return false;
		}

		// Determine current domain config file.
		if (defined('MIGHTY_CONFIG'))
		{
			$domain_file = MIGHTY_CONFIG;
		}
		else
		{
			$domain_file = self::getConfigFilename();
		}

		if (!$domain_file || !file_exists($domain_file) || !is_writable($domain_file) && !@chmod($domain_file, 0644))
		{
			return false;
		}

		$old_contents = @file_get_contents($domain_file);

		// Evaluate both configs into unique classes.
		$new_class = 'MightyAbsorbedConfig' . md5($new_contents . uniqid('', true));
		$old_class = 'MightyAbsorbedConfig' . md5($old_contents . uniqid('', true));

		$new_code = strtr($new_contents, array('<?php' => '', 'class JConfig' => 'class ' . $new_class));
		$old_code = strtr($old_contents, array('<?php' => '', 'class JConfig' => 'class ' . $old_class));

		try
		{
			eval($new_code);
			eval($old_code);
		}
		catch (\Throwable $e)
		{
			return false;
		}

		$oldObj = class_exists($old_class) ? new $old_class : null;
		$newObj = class_exists($new_class) ? new $new_class : null;

		if (!$newObj)
		{
			return false;
		}

		// Start from old (keeps mighty_* and unknown props), overlay new values.
		$merged = array();

		if ($oldObj)
		{
			foreach ((array) $oldObj as $k => $v)
			{
				$merged[$k] = $v;
			}
		}

		foreach ((array) $newObj as $k => $v)
		{
			$merged[$k] = $v;
		}

		// Always preserve MightySites custom properties from the old file.
		foreach ((array) ($oldObj ?: array()) as $k => $v)
		{
			if (strpos($k, 'mighty') === 0 || $k === 'cache_path' || $k === 'varnish_dummy')
			{
				$merged[$k] = $v;
			}
		}

		$registry = new Registry($merged);

		$configuration = $registry->toString('PHP', array('class' => 'JConfigggg', 'closingtag' => false));
		$configuration = strtr($configuration, array('JConfigggg' => 'JConfig'));

		return File::write($domain_file, $configuration);
	}

	/**
	 * Create a database object for a site/database record.
	 */
	public static function getDBO(&$site, $force_select = false)
	{
		if (!isset(self::$dbo[$site->id]))
		{
			$conf = Factory::getConfig();

			$options = array(
				'host'     => isset($site->host)     ? $site->host     : $conf->get('host'),
				'user'     => isset($site->user)     ? $site->user     : $conf->get('user'),
				'password' => isset($site->password) ? $site->password : $conf->get('password'),
				'database' => $site->db,
				'prefix'   => $site->dbprefix,
				'select'   => true,
			);

			$driver   = isset($site->dbtype) && $site->dbtype ? $site->dbtype : $conf->get('dbtype');
			$factory  = new \Joomla\Database\DatabaseFactory();
			$dbo      = $factory->getDriver($driver === 'pdomysql' ? 'mysql' : ($driver ?: 'mysqli'), $options);

			try
			{
				$dbo->connect();
			}
			catch (\Throwable $e)
			{
				throw new \RuntimeException(Text::sprintf('COM_MIGHTYSITES_CANT_CONNECT_DB', $options['database'], $options['user'], $e->getMessage()));
			}

			self::$dbo[$site->id] = $dbo;
		}

		if ($force_select)
		{
			self::$dbo[$site->id]->select($site->db);
		}

		return self::$dbo[$site->id];
	}

	public static function getTables(&$db)
	{
		$db->setQuery('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"');
		return $db->loadColumn();
	}

	public static function getViews(&$db)
	{
		$db->setQuery('SHOW FULL TABLES WHERE Table_Type = "VIEW"');
		return $db->loadColumn();
	}

	/**
	 * Simple backend helper: warn if our system plugin is missing/disabled and
	 * force it to be ordered first.
	 */
	public static function topMenu()
	{
		$app = Factory::getApplication();
		$db  = Factory::getDbo();

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = "mightysites"')
			->where($db->quoteName('type') . ' = "plugin"');
		$db->setQuery($query, 0, 1);
		$plugin_mightysites = $db->loadObject();

		if (!$plugin_mightysites)
		{
			$app->enqueueMessage(Text::_('COM_MIGHTYSITES_PLUGIN_INSTALL_ERROR'), 'notice');
		}
		elseif (!$plugin_mightysites->enabled)
		{
			$app->enqueueMessage(Text::sprintf('COM_MIGHTYSITES_PLUGIN_UNPUBLISHED', 'System - MightySites'), 'notice');
		}

		// Auto correct ordering: move to first place.
		if ($plugin_mightysites && $plugin_mightysites->ordering != -99980)
		{
			$db->setQuery('UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName('ordering') . ' = -99980 WHERE ' . $db->quoteName('element') . '="mightysites" AND ' . $db->quoteName('type') . '="plugin"');
			$db->execute();

			try
			{
				if (method_exists(Factory::class, 'getCache'))
				{
					Factory::getCache()->clean('com_plugins');
				}
			}
			catch (\Throwable $e)
			{
				// Cache cleaning is best-effort only.
			}
		}
	}
}
