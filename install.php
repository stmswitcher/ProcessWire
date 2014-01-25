<?php

/**
 * ProcessWire Installer
 *
 * Because this installer runs before PW2 is installed, it is largely self contained.
 * It's a quick-n-simple single purpose script that's designed to run once, and it should be deleted after installation.
 * This file self-executes using code found at the bottom of the file, under the Installer class. 
 *
 * Note that it creates this file once installation is completed: /site/assets/installed.php
 * If that file exists, the installer will not run. So if you need to re-run this installer for any
 * reason, then you'll want to delete that file. This was implemented just in case someone doesn't delete the installer.
 * 
 * ProcessWire 2.x 
 * Copyright (C) 2014 by Ryan Cramer 
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://processwire.com
 *
 */

define("PROCESSWIRE_INSTALL", 2); 

/**
 * class Installer
 *
 * Self contained class to install ProcessWire 2.x
 *
 */
class Installer {

	/**
	 * Whether or not we force installed files to be copied. 
	 *
	 * If false, we attempt a faster rename of directories instead.
	 *
	 */
	const FORCE_COPY = true; 

	/**
	 * Replace existing database tables if already present?
	 *
	 */
	const REPLACE_DB = true; 

	/**
	 * Minimum required PHP version to install ProcessWire
	 *
	 */
	const MIN_REQUIRED_PHP_VERSION = '5.3.8';

	/**
	 * Test mode for installer development, non destructive
	 *
	 */
	const TEST_MODE = false;

	/**
	 * File permissions, determined in the dbConfig function
	 *
	 * Below are last resort defaults
	 *
	 */
	protected $chmodDir = "0777";
	protected $chmodFile = "0666";

	/**
	 * Number of errors that occurred during the request
	 *
	 */
	protected $numErrors = 0; 

	/**
	 * Available color themes
	 *
	 */
	protected $colors = array(
		'classic',
		'warm',
		'modern',
		'futura'
		);

	/**
	 * Execution controller
	 *
	 */
	public function execute() {

		$title = "ProcessWire 2.4 Installation";

		require("./wire/modules/AdminTheme/AdminThemeDefault/install-head.inc"); 

		if(isset($_POST['step'])) switch($_POST['step']) {

			case 1: $this->compatibilityCheck(); 
				break;

			case 2: $this->dbConfig(); 
				break;

			case 4: $this->dbSaveConfig(); 
				break;

			case 5: require("./index.php"); 
				$this->adminAccountSave($wire); 
				break;

			default: 
				$this->welcome();

		} else $this->welcome();

		require("./wire/modules/AdminTheme/AdminThemeDefault/install-foot.inc"); 
	}


	/**
	 * Welcome/Intro screen
	 *
	 */
	protected function welcome() {
		$this->h("Welcome. This tool will guide you through the installation process."); 
		$this->p("Thanks for choosing ProcessWire! If you downloaded this copy of ProcessWire from somewhere other than <a href='http://processwire.com/'>processwire.com</a> or <a href='https://github.com/ryancramerdesign/ProcessWire' target='_blank'>our GitHub page</a>, please download a fresh copy before installing. If you need help or have questions during installation, please stop by our <a href='http://processwire.com/talk/' target='_blank'>support board</a> and we'll be glad to help.");
		$this->btn("Get Started", 1, 'sign-in'); 
	}


	/**
	 * Check if the given function $name exists and report OK or fail with $label
	 *
	 */
	protected function checkFunction($name, $label) {
		if(function_exists($name)) $this->ok("$label"); 
			else $this->err("Fail: $label"); 
	}

	/**
	 * Step 1: Check for ProcessWire compatibility
	 *
	 */
	protected function compatibilityCheck() { 

		$this->h("Compatibility Check"); 
		
		if(is_file("./site/install/install.sql")) {
			$this->ok("Found installation profile in /site/install/"); 

		} else if(is_dir("./site/")) {
			$this->ok("Found /site/ -- already installed? ");

		} else if(@rename("./site-default", "./site")) {
			$this->ok("Renamed /site-default => /site"); 

		} else {
			$this->err("Before continuing, please rename '/site-default' to '/site' -- this is the default installation profile."); 
			$this->ok("If you prefer, you may download an alternate installation profile at processwire.com/download, which you should unzip to /site");
			$this->btn("Continue", 1); 
			return;
		}

		if(version_compare(PHP_VERSION, self::MIN_REQUIRED_PHP_VERSION) >= 0) {
			$this->ok("PHP version " . PHP_VERSION);
		} else {
			$this->err("ProcessWire requires PHP version " . self::MIN_REQUIRED_PHP_VERSION . " or newer. You are running PHP " . PHP_VERSION);
		}
		
		if(extension_loaded('pdo_mysql')) {
			$this->ok("PDO (mysql) database"); 
		} else {
			$this->err("PDO (pdo_mysql) is required (for MySQL database)"); 
		}

		if(self::TEST_MODE) {
			$this->err("Example error message for test mode"); 
		}

		$this->checkFunction("filter_var", "Filter functions (filter_var)");
		$this->checkFunction("mysqli_connect", "MySQLi (not required by core, but may be required by some 3rd party modules)");
		$this->checkFunction("imagecreatetruecolor", "GD 2.0 or newer"); 
		$this->checkFunction("json_encode", "JSON support");
		$this->checkFunction("preg_match", "PCRE support"); 
		$this->checkFunction("ctype_digit", "CTYPE support");
		$this->checkFunction("iconv", "ICONV support"); 
		$this->checkFunction("session_save_path", "SESSION support"); 
		$this->checkFunction("hash", "HASH support"); 
		$this->checkFunction("spl_autoload_register", "SPL support"); 

		if(function_exists('apache_get_modules')) {
			if(in_array('mod_rewrite', apache_get_modules())) $this->ok("Found Apache module: mod_rewrite"); 
				else $this->err("Apache mod_rewrite does not appear to be installed and is required by ProcessWire."); 
		} else {
			// apache_get_modules doesn't work on a cgi installation.
			// check for environment var set in htaccess file, as submitted by jmarjie. 
			$mod_rewrite = getenv('HTTP_MOD_REWRITE') == 'On' || getenv('REDIRECT_HTTP_MOD_REWRITE') == 'On' ? true : false;
			if($mod_rewrite) {
				$this->ok("Found Apache module (cgi): mod_rewrite");
			} else {
				$this->err("Unable to determine if Apache mod_rewrite (required by ProcessWire) is installed. On some servers, we may not be able to detect it until your .htaccess file is place. Please click the 'check again' button at the bottom of this screen, if you haven't already."); 
			}
		}

		if(is_writable("./site/assets/")) $this->ok("./site/assets/ is writable"); 
			else $this->err("Error: Directory ./site/assets/ must be writable. Please adjust the permissions before continuing."); 

		if(is_writable("./site/config.php")) $this->ok("./site/config.php is writable"); 
			else $this->err("Error: File ./site/config.php must be writable. Please adjust the permissions before continuing."); 

		if(!is_file("./.htaccess") || !is_readable("./.htaccess")) {
			if(@rename("./htaccess.txt", "./.htaccess")) $this->ok("Installed .htaccess"); 
				else $this->err("/.htaccess doesn't exist. Before continuing, you should rename the included htaccess.txt file to be .htaccess (with the period in front of it, and no '.txt' at the end)."); 

		} else if(!strpos(file_get_contents("./.htaccess"), "PROCESSWIRE")) {
			$this->err("/.htaccess file exists, but is not for ProcessWire. Please overwrite or combine it with the provided /htaccess.txt file (i.e. rename /htaccess.txt to /.htaccess, with the period in front)."); 

		} else {
			$this->ok(".htaccess looks good"); 
		}

		if($this->numErrors) {
			$this->p("One or more errors were found above. We recommend you correct these issues before proceeding or <a href='http://processwire.com/talk/'>contact ProcessWire support</a> if you have questions or think the error is incorrect. But if you want to proceed anyway, click Continue below.");
			$this->btn("Check Again", 1, 'refresh', false, true); 
			$this->btn("Continue to Next Step", 2, 'angle-right', true); 
		} else {
			$this->btn("Continue to Next Step", 2, 'angle-right', false); 
		}
	}

	/**
	 * Step 2: Configure the database and file permission settings
	 *
	 */
	protected function dbConfig($values = array()) {

		if(!is_file("./site/install/install.sql")) die("There is no installation profile in /site/. Please place one there before continuing. You can get it at processwire.com/download"); 

		$this->h("MySQL Database"); 
		$this->p("Please create a MySQL 5.x database and user account on your server. The user account should have full read, write and delete permissions on the database.* Once created, please enter the information for this database and account below:"); 
		$this->p("*Recommended permissions are select, insert, update, delete, create, alter, index, drop, create temporary tables, and lock tables.", "detail"); 

		if(!isset($values['dbName'])) $values['dbName'] = '';
		// @todo: are there PDO equivalents for the ini_get()s below?
		if(!isset($values['dbHost'])) $values['dbHost'] = ini_get("mysqli.default_host"); 
		if(!isset($values['dbPort'])) $values['dbPort'] = ini_get("mysqli.default_port"); 
		if(!isset($values['dbUser'])) $values['dbUser'] = ini_get("mysqli.default_user"); 
		if(!isset($values['dbPass'])) $values['dbPass'] = ini_get("mysqli.default_pw"); 

		if(!$values['dbHost']) $values['dbHost'] = 'localhost';
		if(!$values['dbPort']) $values['dbPort'] = 3306; 

		foreach($values as $key => $value) {
			if(strpos($key, 'chmod') === 0) $values[$key] = (int) $value;
				else $values[$key] = htmlspecialchars($value, ENT_QUOTES); 
		}

		$this->input('dbName', 'DB Name', $values['dbName']); 
		$this->input('dbUser', 'DB User', $values['dbUser']);
		$this->input('dbPass', 'DB Pass', $values['dbPass'], false, 'password', false); 
		$this->input('dbHost', 'DB Host', $values['dbHost']); 
		$this->input('dbPort', 'DB Port', $values['dbPort'], true); 

		$cgi = false;
		if(is_writable(__FILE__)) {
			$defaults['chmodDir'] = "755";
			$defaults['chmodFile'] = "644";
			$cgi = true;
		} else {
			$defaults['chmodDir'] = "777";
			$defaults['chmodFile'] = "666";
		}
		$values = array_merge($defaults, $values); 

		$this->h("File Permissions"); 
		$this->p(
			"When ProcessWire creates directories or files, it assigns permissions to them. " . 
			"Enter the most restrictive permissions possible that give ProcessWire (and you) read and write access to the web server (Apache). " . 
			"The safest setting to use varies from server to server. " . 
			"If you are not on a dedicated server or private server, you may want to contact your web host to advise on what are the best permissions to use in your environment. " . 
			"Should you opt to use the defaults provided, you can also adjust these permissions later (if necessary) by editing <u>/site/config.php</u>. "
			);

		$this->p("Permissions must be 3 digits each.", "detail");

		$this->input('chmodDir', 'Directories', $values['chmodDir']); 
		$this->input('chmodFile', 'Files', $values['chmodFile'], true); 

		if($cgi) $this->p("We detected that this file (install.php) is writable. That means Apache may be running as your user account. Given that, we populated the permissions above (755 &amp; 644) as possible good starting point.");

		$this->btn("Continue", 4); 

		$this->p("Note: After you click the button above, be patient &hellip; it may take a minute.", "detail");
	}

	/**
	 * Step 3: Save database configuration, then begin profile import
	 *
	 */
	protected function dbSaveConfig() {

		$fields = array('dbUser', 'dbName', 'dbPass', 'dbHost', 'dbPort'); 	
		$values = array();	

		foreach($fields as $field) {
			$value = get_magic_quotes_gpc() ? stripslashes($_POST[$field]) : $_POST[$field]; 
			$value = substr($value, 0, 128); 
			$values[$field] = $value; 
		}

		if(!$values['dbUser'] || !$values['dbName'] || !$values['dbPort']) {
			$this->err("Missing database configuration fields"); 
			return $this->dbConfig();
		}

		error_reporting(0); 
		
		$dsn = "mysql:dbname=$values[dbName];host=$values[dbHost];port=$values[dbPort]";
		$driver_options = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			);
		try {
			$database = new PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options); 
		} catch(Exception $e) {
			$this->err("Database connection information did not work."); 
			$this->err($e->getMessage());
			$this->dbConfig($values);
			return;
		}

		// file permissions
		$fields = array('chmodDir', 'chmodFile');
		foreach($fields as $field) {
			$value = (int) $_POST[$field]; 
			if(strlen("$value") !== 3) $this->err("Value for '$field' is invalid");
				else $this->$field = "0$value"; 
			$values[$field] = $value;
		}

		if($this->numErrors) {
			$this->dbConfig($values);
			return;
		}

		$this->h("Test Database and Save Configuration");
		$this->ok("Database connection successful to " . htmlspecialchars($values['dbName'])); 

		if($this->dbSaveConfigFile($values)) $this->profileImport($database);
			else $this->dbConfig($values);
	}

	/**
	 * Save configuration to /site/config.php
	 *
	 */
	protected function dbSaveConfigFile(array $values) {

		if(self::TEST_MODE) return true; 

		$salt = md5(mt_rand() . microtime(true)); 

		$cfg = 	"\n/**" . 
			"\n * Installer: Database Configuration" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->dbHost = '$values[dbHost]';" . 
			"\n\$config->dbName = '$values[dbName]';" . 
			"\n\$config->dbUser = '$values[dbUser]';" . 
			"\n\$config->dbPass = '$values[dbPass]';" . 
			"\n\$config->dbPort = '$values[dbPort]';" . 
			"\n" . 
			"\n/**" . 
			"\n * Installer: User Authentication Salt " . 
			"\n * " . 
			"\n * Must be retained if you migrate your site from one server to another" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->userAuthSalt = '$salt'; " . 
			"\n" . 
			"\n/**" . 
			"\n * Installer: File Permission Configuration" . 
			"\n * " . 
			"\n */" . 
			"\n\$config->chmodDir = '0$values[chmodDir]'; // permission for directories created by ProcessWire" . 	
			"\n\$config->chmodFile = '0$values[chmodFile]'; // permission for files created by ProcessWire " . 	
			"\n\n";

		if(($fp = fopen("./site/config.php", "a")) && fwrite($fp, $cfg)) {
			fclose($fp); 
			$this->ok("Saved database configuration to ./site/config.php"); 
			return true; 
		} else {
			$this->err("Error saving database configuration to ./site/config.php. Please make sure it is writable."); 
			return false;
		}
	}

	/**
	 * Step 3b: Import profile
	 *
	 */
	protected function profileImport($database) {

		if(self::TEST_MODE) {
			$this->ok("TEST MODE: Skipping profile import"); 
			$this->adminAccount();
			return;
		}

		$profile = "./site/install/";
		if(!is_file("{$profile}install.sql")) die("No installation profile found in {$profile}"); 

		// checks to see if the database exists using an arbitrary query (could just as easily be something else)
		try {
			$query = $database->prepare("SHOW COLUMNS FROM pages"); 
			$result = $query->execute();
		} catch(Exception $e) {
			$result = false;
		}

		if(self::REPLACE_DB || !$result || $query->rowCount() == 0) {

			$this->profileImportSQL($database, "./wire/core/install.sql"); 
			$this->ok("Imported: ./wire/core/install.sql"); 
			$this->profileImportSQL($database, $profile . "install.sql"); 
			$this->ok("Imported: {$profile}install.sql"); 

			if(is_dir($profile . "files")) $this->profileImportFiles($profile);
				else $this->mkdir("./site/assets/files/"); 
			$this->mkdir("./site/assets/cache/"); 
			$this->mkdir("./site/assets/logs/"); 
			$this->mkdir("./site/assets/sessions/"); 

		} else {
			$this->ok("A profile is already imported, skipping..."); 
		}

		$this->adminAccount();
	}


	/**
	 * Import files to profile
	 *
	 */
	protected function profileImportFiles($fromPath) {

		if(self::TEST_MODE) {
			$this->ok("TEST MODE: Skipping file import - $fromPath"); 
			return;
		}

		$dir = new DirectoryIterator($fromPath);

		foreach($dir as $file) {

			if($file->isDot()) continue; 
			if(!$file->isDir()) continue; 

			$dirname = $file->getFilename();
			$pathname = $file->getPathname();

			if(is_writable($pathname) && self::FORCE_COPY == false) {
				// if it's writable, then we know all the files are likely writable too, so we can just rename it
				$result = rename($pathname, "./site/assets/$dirname/"); 

			} else {
				// if it's not writable, then we will make a copy instead, and that copy should be writable by the server
				$result = $this->copyRecursive($pathname, "./site/assets/$dirname/"); 
			}

			if($result) $this->ok("Imported: $pathname => ./site/assets/$dirname/"); 
				else $this->err("Error Importing: $pathname => ./site/assets/$dirname/"); 
			
		}
	}

	/**
	 * Import profile SQL dump
	 *
	 */
	protected function profileImportSQL($database, $sqlDumpFile) {

		if(self::TEST_MODE) return;

		$fp = fopen($sqlDumpFile, "rb"); 	
		while(!feof($fp)) {
			$line = trim(fgets($fp)); 
			if(empty($line) || substr($line, 0, 2) == '--') continue; 
			if(strpos($line, 'CREATE TABLE') === 0) {
				preg_match('/CREATE TABLE ([^(]+)/', $line, $matches); 
				//$this->ok("Creating table: $matches[1]"); 
				do { $line .= fgets($fp); } while(substr(trim($line), -1) != ';'); 
			}

			try {	
				$database->exec($line); 
			} catch(Exception $e) {
				$this->err($e->getMessage()); 
			}
			
		}
	}

	/**
	 * Present form to create admin account
	 *
	 */
	protected function adminAccount($wire = null) {

		$values = array(
			'admin_name' => 'processwire',
			'username' => 'admin',
			'userpass' => '',
			'userpass_confirm' => '',
			'useremail' => '',
			);

		$clean = array();

		foreach($values as $key => $value) {
			if($wire && $wire->input->post->$key) $value = $wire->input->post->$key;
			$value = htmlentities($value, ENT_QUOTES, "UTF-8"); 
			$clean[$key] = $value;
		}

		$this->h("Admin Panel Information");
		$this->input("admin_name", "Admin Login URL", $clean['admin_name'], false, "name"); 
		$js = "$('link#colors').attr('href', $('link#colors').attr('href').replace(/main-.*$/, 'main-' + $(this).val() + '.css'))";
		echo "<p class='ui-helper-clearfix'><label>Color Theme<br /><select name='colors' id='colors' onchange=\"$js\">";
		foreach($this->colors as $color) echo "<option value='$color'>" . ucfirst($color) . "</option>";
		echo "</select></label> <span class='detail'><i class='fa fa-angle-left'></i> Change for a live preview*</span></p>";
		$this->p("<i class='fa fa-info-circle'></i> You can change the admin URL later by editing the admin page and changing the name on the settings tab.<br /><i class='fa fa-info-circle'></i> You can change the colors later by going to Admin <i class='fa fa-angle-right'></i> Modules <i class='fa fa-angle-right detail'></i> Core <i class='fa fa-angle-right detail'></i> Admin Theme <i class='fa fa-angle-right'></i> Settings.", "detail"); 
		$this->h("Admin Account Information");
		$this->p("The account you create here will have superuser access, so please make sure to create a strong password.");
		$this->input("username", "User", $clean['username'], false, "name"); 
		$this->input("userpass", "Password", $clean['userpass'], false, "password"); 
		$this->input("userpass_confirm", "Password (again)", $clean['userpass_confirm'], true, "password"); 
		$this->input("useremail", "Email Address", $clean['useremail'], true, "email"); 
		$this->p("<i class='fa fa-warning'></i> Please remember the password you enter above as you will not be able to retrieve it again.", "detail");

		$this->btn("Create Account", 5); 
	}

	/**
	 * Save submitted admin account form
	 *
	 */
	protected function adminAccountSave($wire) {

		$input = $wire->input;
		$sanitizer = $wire->sanitizer; 

		if(!$input->post->username || !$input->post->userpass) $this->err("Missing account information"); 
		if($input->post->userpass !== $input->post->userpass_confirm) $this->err("Passwords do not match");
		if(strlen($input->post->userpass) < 6) $this->err("Password must be at least 6 characters long"); 

		$username = $sanitizer->pageName($input->post->username); 
		if($username != $input->post->username) $this->err("Username must be only a-z 0-9");
		if(strlen($username) < 2) $this->err("Username must be at least 2 characters long"); 

		$adminName = $sanitizer->pageName($input->post->admin_name);
		if($adminName != $input->post->admin_name) $this->err("Admin login URL must be only a-z 0-9");
		if(strlen($adminName) < 2) $this->err("Admin login URL must be at least 2 characters long"); 

		$email = strtolower($sanitizer->email($input->post->useremail)); 
		if($email != strtolower($input->post->useremail)) $this->err("Email address did not validate");

		if($this->numErrors) return $this->adminAccount($wire);
	
		$superuserRole = $wire->roles->get("name=superuser");
		$user = $wire->users->get($wire->config->superUserPageID); 

		if(!$user->id) {
			$user = new User(); 
			$user->id = $wire->config->superUserPageID; 
		}

		$user->name = $username;
		$user->pass = $input->post->userpass; 
		$user->email = $email;

		if(!$user->roles->has("superuser")) $user->roles->add($superuserRole); 

		$admin = $wire->pages->get($wire->config->adminRootPageID); 
		$admin->of(false);
		$admin->name = $adminName;

		try {
			if(self::TEST_MODE) {
				$this->ok("TEST MODE: skipped user creation"); 
			} else {
				$wire->users->save($user); 
				$wire->pages->save($admin);
			}

		} catch(Exception $e) {
			$this->err($e->getMessage()); 
			return $this->adminAccount($wire); 
		}

		$adminName = htmlentities($adminName, ENT_QUOTES, "UTF-8");

		$this->h("Admin Account Saved");
		$this->ok("User account saved: <b>{$user->name}</b>"); 

		$colors = $wire->sanitizer->pageName($input->post->colors); 
		if(!in_array($colors, $this->colors)) $colors = reset($this->colors); 
		$theme = $wire->modules->getInstall('AdminThemeDefault'); 
		$configData = $wire->modules->getModuleConfigData('AdminThemeDefault'); 
		$configData['colors'] = $colors;
		$wire->modules->saveModuleConfigData('AdminThemeDefault', $configData); 
		$this->ok("Saved admin color set <b>$colors</b> - you will see this when you login."); 

		$this->h("Complete &amp; Secure Your Installation");
		$this->ok("It is recommended that you make <b>/site/config.php</b> non-writable, for security."); 

		if(!self::TEST_MODE) {
			if(@unlink("./install.php")) $this->ok("Deleted this installer (./install.php) for security."); 
				else $this->ok("Please delete this installer! The file is located in your web root at: ./install.php"); 
		}


		$this->ok("There are additional configuration options available in <b>/site/config.php</b> that you may want to review."); 
		$this->ok("To save space, you may optionally delete <b>/site/install/</b> - it's no longer needed."); 
		$this->ok("Note that future runtime errors are logged to <b>/site/assets/logs/errors.txt</b> (not web accessible)."); 

		$this->h("Use The Site!");
		$this->ok("Your admin URL is <a href='./$adminName/'>/$adminName/</a>"); 
		$this->p("If you'd like, you may change this later by editing the admin page and changing the name.", "detail"); 
		$this->btn("Login to Admin", 1, 'sign-in', false, true, "./$adminName/"); 
		$this->btn("View Site ", 1, 'angle-right', true, false, "./"); 

		//$this->p("<a target='_blank' href='./'>View the Web Site</a> or <a href='./$adminName/'>Login to ProcessWire admin</a>");

		// set a define that indicates installation is completed so that this script no longer runs
		if(!self::TEST_MODE) {
			file_put_contents("./site/assets/installed.php", "<?php // The existence of this file prevents the installer from running. Don't delete it unless you want to re-run the install or you have deleted ./install.php."); 
		}

	}

	/******************************************************************************************************************
	 * OUTPUT FUNCTIONS
	 *
	 */
	
	/**
	 * Report and log an error
	 *
	 */
	protected function err($str) {
		$this->numErrors++;
		echo "\n<li class='ui-state-error'><i class='fa fa-exclamation-triangle'></i> $str</li>";
		return false;
	}

	/**
	 * Report success
	 *
	 */
	protected function ok($str) {
		echo "\n<li class='ui-state-highlight'><i class='fa fa-check-square-o'></i> $str</li>";
		return true; 
	}

	/**
	 * Output a button 
	 *
	 */
	protected function btn($label, $value, $icon = 'angle-right', $secondary = false, $float = false, $href ='') {
		$class = $secondary ? 'ui-priority-secondary' : '';
		if($float) $class .= " floated";
		$type = 'submit';
		if($href) $type = 'button';
		if($href) echo "<a href='$href'>";
		echo "\n<p><button name='step' type='$type' class='ui-button ui-widget ui-state-default $class ui-corner-all' value='$value'>";
		echo "<span class='ui-button-text'><i class='fa fa-$icon'></i> $label</span>";
		echo "</button></p>";
		if($href) echo "</a>";
	}

	/**
	 * Output a headline
	 *
	 */
	protected function h($label) {
		echo "\n<h2>$label</h2>";
	}

	/**
	 * Output a paragraph 
	 *
	 */
	protected function p($text, $class = '') {
		if($class) echo "\n<p class='$class'>$text</p>";
			else echo "\n<p>$text</p>";
	}

	/**
	 * Output an <input type='text'>
	 *
	 */
	protected function input($name, $label, $value, $clear = false, $type = "text", $required = true) {
		$width = 135; 
		$required = $required ? "required='required'" : "";
		$pattern = '';
		$note = '';
		if($type == 'email') {
			$width = ($width*2); 
			$required = '';
		} else if($type == 'name') {
			$type = 'text';
			$pattern = "pattern='[-_a-z0-9]{2,50}' ";
			if($name == 'admin_name') $width = ($width*2);
			$note = "<small class='detail' style='font-weight: normal;'>(a-z 0-9)</small>";
		}
		$inputWidth = $width - 15; 
		$value = htmlentities($value, ENT_QUOTES, "UTF-8"); 
		echo "\n<p style='width: {$width}px; float: left; margin-top: 0;'><label>$label $note<br /><input type='$type' name='$name' value='$value' $required $pattern style='width: {$inputWidth}px;' /></label></p>";
		if($clear) echo "\n<br style='clear: both;' />";
	}


	/******************************************************************************************************************
	 * FILE FUNCTIONS
	 *
	 */

	/**
	 * Create a directory and assign permission
	 *
	 */
	protected function mkdir($path, $showNote = true) {
		if(self::TEST_MODE) return;
		if(mkdir($path)) {
			chmod($path, octdec($this->chmodDir));
			if($showNote) $this->ok("Created directory: $path"); 
			return true; 
		} else {
			if($showNote) $this->err("Error creating directory: $path"); 
			return false; 
		}
	}

	/**
	 * Copy directories recursively
	 *
	 */
	protected function copyRecursive($src, $dst) {

		if(self::TEST_MODE) return;

		if(substr($src, -1) != '/') $src .= '/';
		if(substr($dst, -1) != '/') $dst .= '/';

		$dir = opendir($src);
		$this->mkdir($dst, false);

		while(false !== ($file = readdir($dir))) {
			if($file == '.' || $file == '..') continue; 
			if(is_dir($src . $file)) {
				$this->copyRecursive($src . $file, $dst . $file);
			} else {
				copy($src . $file, $dst . $file);
				chmod($dst . $file, octdec($this->chmodFile));
			}
		}

		closedir($dir);
		return true; 
	} 


}

/****************************************************************************************************/

if(!Installer::TEST_MODE && is_file("./site/assets/installed.php")) die("This installer has already run. Please delete it."); 
error_reporting(E_ALL | E_STRICT); 
$installer = new Installer();
$installer->execute();

