<?php

function pdoe_mysql_test_db_credentials() {
	/* SET THESE if you don't want to enter database connection credentials ever time*/
	return array( 
		'user' => 'pdoe', 
		'password' => 'moomoo', 
		'host' => 'localhost', 
		'database' => 'pdoe_test'
	);
}

require_once('WTestSet.class.php');
require_once('../PDOE.class.php');
require_once('common.php');

class Test_PDOE_MySQL extends Test_PDOE_common {


	function setup_check_pdo_mysql_install() {
		$drivers = PDO::getAvailableDrivers();
		$result = $this->assert(array_search('mysql',$drivers), "No MySQL Driver");
		if(!$result)
			throw new Exception($this->last_log(),TEST_SET_ABORT);
		return $result;
	}

	function setup_connect() {
		$db = pdoe_mysql_test_db_credentials();
		if(!is_array($db)) throw new Exception("\$db is apparently not an array, but WHAT IS IT? Well, it looks like this: ".var_export($db,true));
		foreach($db as $k=>$v) {
			if(!trim($v)) {
				print "\nConnection info needed - MySQL $k: ";
				$db[$k] = readln();
				$neededEntry = true;
			}
		}
		$this->_db = $db;
		if(isset($neededEntry)) 
			echo "\n(You can set this info in ",__FILE__," so you don't have to re-enter it each time this test set is run.)\n";
		$dsn = "mysql:host={$db['host']};dbname={$db['database']}";
		return $this->assert(
			$this->dbh = new PDO($dsn,$db['user'],$db['password']),
			"couldn't connect to MySQL database ".$this->_dberr($this->dbh)
		);
	}

}


