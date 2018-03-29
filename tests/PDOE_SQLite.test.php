<?php

require_once('WTestSet.class.php');
require_once('../PDOE.class.php');
require_once('common.php');

class Test_PDOE_SQLite extends Test_PDOE_common {

	function setup_create_DB() {
		$sqlite_err = '';
		$this->dbfile = './pdoetestdb';

		return $this->assert(
			$this->dbh = $dbh = new PDO("sqlite:$this->dbfile"),
			"couldn't create pdoetestdb ".$this->_dberr($dbh)
		) && $this->assert(
			!($dbh->exec("CREATE TABLE person (id INTEGER PRIMARY KEY, firstname, lastname, email , phone )") === false),
			"couldn't create person table ".$this->_dberr($dbh)
		);
	} 

	function setup_populate_DB() {

		$this->rec1 = array(null, 'John','Smith','jsmith@devnull.not','3215559876');
		$this->rec2 = array(null, 'Chad','Vader','chadv@empire.not','3215550123');
		$this->rec3 = array(null, 'Moo','Cow','moo@pasture.not','3215559876');


		return $this->assert(
			$sth = $this->dbh->prepare("INSERT INTO person VALUES (?,?,?,?,?)"),
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
			$this->pdoe = new PDOE("sqlite:$this->dbfile"),
			"couldn't create/connect PDOE ".$this->_dberr($this->pdoe)
		);
	}

	function takedown_destroy_DB() {
		return $this->assert(
			$this->dbh->exec("DROP TABLE person"),
			"couldn't drop table person".implode(null,$this->dbh->errorInfo())
		) && $this->assert(
			unlink('./pdoetestdb'),
			"couldn't delete test db"
		);
	} 
}


