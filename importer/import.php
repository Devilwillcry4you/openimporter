<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (defined('IN_PHAR'))
{
	define('WORKINGDIR', dirname(Phar::running(false)));
}
else
{
	define('WORKINGDIR', __DIR__);
}
define('BASEDIR', __DIR__);

require_once(BASEDIR . '/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader(null, BASEDIR . '/OpenImporter');
$classLoader->register();
$classLoader2 = new SplClassLoader(null, BASEDIR . '/Importers');
$classLoader2->register();

@set_time_limit(600);
@set_exception_handler(array('ImportException', 'exception_handler'));
@set_error_handler(array('ImportException', 'error_handler_callback'), E_ALL);

// Clean up after unfriendly php.ini settings.
if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0') < 0)
	@set_magic_quotes_runtime(0);

error_reporting(E_ALL);
ignore_user_abort(true);
umask(0);

ob_start();

// disable gzip compression if possible
if (is_callable('apache_setenv'))
	apache_setenv('no-gzip', '1');

if (@ini_get('session.save_handler') == 'user')
	@ini_set('session.save_handler', 'files');
@session_start();

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	$_POST = stripslashes_recursive($_POST);

$config = new Configurator();
$config->lang_dir = BASEDIR . '/Languages';

try
{
	$lng = new Lang();
	$lng->loadLang($config->lang_dir);
}
catch (Exception $e)
{
	ImportException::exception_handler($e);
}

$template = new Template($lng);

global $import;
$importer = new Importer($config, $lng, $template);
$response = new HttpResponse(new ResponseHeader());

$template->setResponse($response);

$import = new ImportManager($config, $importer, $template, new Cookie(), $response);

try
{
	$import->process();
}
catch (Exception $e)
{
	// Debug, remember to remove before PR
	echo '<br>' . $e->getMessage() . '<br>';
	echo $e->getFile() . '<br>';
	echo $e->getLine() . '<br>';
	// If an error is not catched, it means it's fatal and the script should die.
}