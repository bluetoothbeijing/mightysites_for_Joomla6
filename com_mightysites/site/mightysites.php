<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * MightySites bootstrap for Joomla 5/6.
 *
 * configuration.php of the site is patched to require this file first.
 * This file loads the per-domain configuration (class JConfig) and, if
 * "Data Overload" is enabled, swaps the Joomla database handler with a
 * prefix-rewriting driver BEFORE the DI container resolves the database.
 */

// Setup base (folder of this file is <root>/components/com_mightysites).
define('MIGHTY_BASE', substr(__DIR__, 0, -strlen('/components/com_mightysites')));

// Setup domain. It can be already defined in alias config.
$domain = 'default';

if (!empty($_SERVER['HTTP_HOST']))
{
	$domain = $_SERVER['HTTP_HOST'];
}
elseif (!empty($_SERVER['SERVER_NAME']))
{
	$domain = $_SERVER['SERVER_NAME'];
}
// CLI mode - allow '--domain' parameter
elseif (defined('STDOUT') && defined('STDIN') && isset($_SERVER['argv']))
{
	foreach ($_SERVER['argv'] as $argv)
	{
		if (substr($argv, 0, 9) === '--domain=')
		{
			list(, $domain) = explode('=', $argv);
			break;
		}
	}
}

// Remove port
if (strpos($domain, ':') !== false)
{
	list($domain, $tmp) = explode(':', $domain);
}

$domain = (substr($domain, 0, 4) === 'www.') ? substr($domain, 4) : $domain;
$domain = preg_replace('#([^A-Z0-9])#i', '_', $domain);

$mighty_domain = $domain;

// Setup config.
$config_file = null;

if (file_exists(MIGHTY_BASE . '/configuration_' . $mighty_domain . '.php'))
{
	$config_file = MIGHTY_BASE . '/configuration_' . $mighty_domain . '.php';
}
elseif (file_exists(MIGHTY_BASE . '/components/com_mightysites/configuration/configuration_' . $mighty_domain . '.php'))
{
	$config_file = MIGHTY_BASE . '/components/com_mightysites/configuration/configuration_' . $mighty_domain . '.php';
}
elseif (file_exists(MIGHTY_BASE . '/components/com_mightysites/configuration/default.php'))
{
	$config_file = MIGHTY_BASE . '/components/com_mightysites/configuration/default.php';
}

// Check for virtual folder if we have request URI.
if (!empty($_SERVER['REQUEST_URI']))
{
	if ($folders = array_filter(explode('/', trim($_SERVER['REQUEST_URI'], '/'))))
	{
		if (strpos(end($folders), '.') !== false)
		{
			array_pop($folders);
		}

		if ($folders && end($folders) === 'administrator')
		{
			array_pop($folders);
		}

		while ($folders)
		{
			$segment = preg_replace('#([^A-Z0-9])#i', '_', implode('.', $folders));

			if (file_exists(MIGHTY_BASE . '/configuration_' . $mighty_domain . '_' . $segment . '.php'))
			{
				$config_file    = MIGHTY_BASE . '/configuration_' . $mighty_domain . '_' . $segment . '.php';
				$mighty_domain .= '_' . $segment;
				$subfolder      = true;
				break;
			}
			elseif (file_exists(MIGHTY_BASE . '/components/com_mightysites/configuration/configuration_' . $mighty_domain . '_' . $segment . '.php'))
			{
				$config_file    = MIGHTY_BASE . '/components/com_mightysites/configuration/configuration_' . $mighty_domain . '_' . $segment . '.php';
				$mighty_domain .= '_' . $segment;
				$subfolder      = true;
				break;
			}

			array_pop($folders);
		}
	}

	// Correct possible issues with subfolder site and RewriteBase
	if (!empty($subfolder))
	{
		if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] === '/index.php')
		{
			$_SERVER['SCRIPT_NAME'] = '/' . $segment . '/index.php';
		}

		if (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] === '/index.php')
		{
			$_SERVER['PHP_SELF'] = '/' . $segment . '/index.php';
		}
	}
}

// Load personal config file
if (isset($config_file))
{
	require_once $config_file;
}
// not for Akeeba kickstart restore
elseif (!defined('KICKSTART') && !defined('_AKEEBA'))
{
	die('Invalid domain, please ensure that MightySites is configured properly.');
}

// Setup domain. It can be already defined in alias config.
if (!defined('MIGHTY_DOMAIN'))
{
	define('MIGHTY_DOMAIN', $mighty_domain);
}

// Setup config. It can be already defined in alias config.
if (!defined('MIGHTY_CONFIG'))
{
	define('MIGHTY_CONFIG', $config_file);
}

$config = new JConfig;

// Load our prefix-rewriting database driver.
//
// NOTE: we must NOT call JFactory/Factory::getConfig() here - this file is
// included while the DI container resolves the 'config' service, so we work
// with the raw JConfig object instead.

if (!class_exists('JDatabaseMightysites')
	&& defined('JPATH_SITE')
	&& isset($config->mighty_enable) && $config->mighty_enable
	&& isset($config->mighty) && is_array($config->mighty) && isset($config->mighty[0][0]))
{
	// Remove legacy
	if ($config->dbtype === 'mightysites')
	{
		$config->dbtype = 'mysqli';
	}

	// Resolve base driver class for Joomla 4/5/6 database layer.
	switch ($config->dbtype)
	{
		case 'mysql':
		case 'pdomysql':
			$mighty_base_driver = '\\Joomla\\Database\\Mysql\\MysqlDriver';
			break;
		default:
			$mighty_base_driver = '\\Joomla\\Database\\Mysqli\\MysqliDriver';
	}

	if (!class_exists($mighty_base_driver))
	{
		// Driver not available (e.g. wrong dbtype) - leave default handling.
		$mighty_base_driver = null;
	}

	if ($mighty_base_driver)
	{
		class_alias($mighty_base_driver, 'MightysitesDriverBase');

		class JDatabaseMightysites extends MightysitesDriverBase
		{
			public $mighty = null;

			public function __construct($options)
			{
				parent::__construct($options);

				// NOTE: at bootstrap time we must not call JFactory::getConfig()
				// (the 'config' service is still being resolved), so the mighty
				// patterns are passed via the options array.
				if (isset($options['mighty']))
				{
					$this->mighty = $options['mighty'];
				}
				else
				{
					$this->mighty = JFactory::getConfig()->get('mighty');
				}
			}

			/**
			 * Rewrites table prefixes according to the MightySites sharing
			 * rules (regex patterns defined in the site configuration).
			 */
			public function replacePrefix($sql, $prefix = '#__')
			{
				// We need to allow parent usual method
				if (isset($GLOBALS['no_mightysharing']) || empty($this->mighty))
				{
					return parent::replacePrefix($sql, $prefix);
				}

				// Initialize variables.
				$escaped   = false;
				$startPos  = 0;
				$quoteChar = '';
				$literal   = '';

				$sql = trim($sql);
				$n   = strlen($sql);

				while ($startPos < $n)
				{
					$ip = strpos($sql, $prefix, $startPos);

					if ($ip === false)
					{
						break;
					}

					$j = strpos($sql, "'", $startPos);
					$k = strpos($sql, '"', $startPos);

					if (($k !== false) && (($k < $j) || ($j === false)))
					{
						$quoteChar = '"';
						$j         = $k;
					}
					else
					{
						$quoteChar = "'";
					}

					if ($j === false)
					{
						$j = $n;
					}

					// We need to add extra space in order to match our patterns and next trim it.
					$literal .= str_replace($prefix, $this->tablePrefix, trim(preg_replace($this->mighty[0], $this->mighty[1], substr($sql, $startPos, $j - $startPos) . ' ')));

					$startPos = $j;

					$j = $startPos + 1;

					if ($j >= $n)
					{
						break;
					}

					// quote comes first, find end of quote
					while (true)
					{
						$k = strpos($sql, $quoteChar, $j);
						$escaped = false;

						if ($k === false)
						{
							break;
						}

						$l = $k - 1;

						while ($l >= 0 && $sql[$l] === '\\')
						{
							$l--;
							$escaped = !$escaped;
						}

						if ($escaped)
						{
							$j = $k + 1;
							continue;
						}

						break;
					}

					if ($k === false)
					{
						// error in the query - no end quote; ignore it
						break;
					}

					$literal .= substr($sql, $startPos, $k - $startPos + 1);
					$startPos = $k + 1;
				}

				if ($startPos < $n)
				{
					$literal .= substr($sql, $startPos, $n - $startPos);
				}

				return $literal;
			}

			/**
			 * Re-install our driver as the active database handler.
			 * Called by the system plugin if the handler was replaced.
			 */
			public static function changeHandler()
			{
				$config = JFactory::getConfig();

				$driver = new self(array(
					'host'     => $config->get('host'),
					'user'     => $config->get('user'),
					'password' => $config->get('password'),
					'database' => $config->get('db'),
					'prefix'   => $config->get('dbprefix'),
					'select'   => true,
					'mighty'   => $config->get('mighty'),
				));

				self::installHandler($driver);
			}

			/**
			 * Install a driver instance into Joomla (Factory + DI container).
			 */
			public static function installHandler($driver)
			{
				\Joomla\CMS\Factory::$database = $driver;

				try
				{
					$container = \Joomla\CMS\Factory::getContainer();
					$container->set(\Joomla\Database\DatabaseInterface::class, $driver, true);
				}
				catch (\Throwable $e)
				{
					// Container not available, ignore.
				}
			}
		}

		// Build the driver right now using the raw JConfig values.
		try
		{
			$mighty_driver = new JDatabaseMightysites(array(
				'host'     => $config->host,
				'user'     => $config->user,
				'password' => $config->password,
				'database' => $config->db,
				'prefix'   => $config->dbprefix,
				'select'   => true,
				'mighty'   => $config->mighty,
			));

			JDatabaseMightysites::installHandler($mighty_driver);
		}
		catch (\Throwable $e)
		{
			// Connection errors are handled lazily by Joomla itself.
		}
	}
}
