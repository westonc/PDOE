<?php

require_once('WTestSet.class.php');
require_once('../PDOE.class.php');
require_once('common.php');

class Test_PDOE_PgSQL extends Test_PDOE_common {
	
	static $dsn = "pgsql:dbname=test";

	function setup_create_DB() {
		return $this->assert(
			$this->dbh = $dbh = new PDO(self::$dsn),
			"couldn't connect using dsn: ".self::$dsn.' '.$this->_dberr($dbh)
		) && $this->assert(
			!($dbh->exec("CREATE TABLE person ( id SERIAL PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, phone TEXT)") === false),
			"couldn't create person table ".$this->_dberr($dbh)
		);
	} 

	function setup_populate_DB() {

		$this->rec1 = array('John','Smith','jsmith@devnull.not','3215559876');
		$this->rec2 = array('Chad','Vader','chadv@empire.not','3215550123');
		$this->rec3 = array('Moo','Cow','moo@pasture.not','3215559876');

		return $this->assert(
			$sth = $this->dbh->prepare("INSERT INTO person (firstname, lastname, email, phone) VALUES (?,?,?,?)"),
			"couldn't prepare insert statement ".$this->_dberr($this->dbh)
		) && $this->assert(
			!($sth->execute($this->rec1) === false) &&
			!($sth->execute($this->rec2) === false) &&
			!($sth->execute($this->rec3) === false),
			"couldn't insert records ".$this->_dberr($this->dbh)
		);
	}

	function setup_create_PDOE() {
		return $this->assert(
			$this->pdoe = new PDOE(self::$dsn),
			"couldn't create/connect PDOE ".$this->_dberr($this->pdoe)
		);
	}

	function takedown_destroy_DB() {
		return $this->assert(
			$this->dbh->exec("DROP TABLE person")!==false,
			"couldn't drop table person: ".implode(' ',$this->dbh->errorInfo())
		);
	}
}


