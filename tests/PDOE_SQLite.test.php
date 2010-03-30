<?php

require_once('WTestSet.class.php');
require_once('../PDOE.class.php');

class Test_PDOE_SQLite extends WTestSet {

	function _dberr($dbh) {
		return implode(',',$dbh->errorInfo());
	}

	function test_createDB() {
		$sqlite_err = '';
		$this->dbfile = './pdoetestdb';

		$this->rec1 = array(null, 'John','Smith','jsmith@devnull.not','3215559876');
		$this->rec2 = array(null, 'Chad','Vader','chadv@empire.not','3215550123');
		$this->rec3 = array(null, 'Moo','Cow','moo@pasture.not','3215559876');

		return $this->assert(
			$this->dbh = $dbh = new PDO("sqlite:$this->dbfile"),
			"couldn't create pdoetestdb ".$this->_dberr($dbh)

		) && $this->assert(
			!($dbh->exec("CREATE TABLE person (id INTEGER PRIMARY KEY, firstname, lastname, email , phone )") === false),
			"couldn't create person table ".$this->_dberr($dbh)

		) && $this->assert(
			$sth = $dbh->prepare("INSERT INTO person VALUES (?,?,?,?,?)"),
			"couldn't prepare insert statement ".$this->_dberr($dbh)

		) && $this->assert(
			!($sth->execute($this->rec1) === false) &&
			!($sth->execute($this->rec2) === false) &&
			!($sth->execute($this->rec3) === false),
			"couldn't insert records ".$this->_dberr($dbh)
		);
	}

	function test_createPDOE() {
		return $this->assert(
			$this->pdoe = new PDOE("sqlite:$this->dbfile"),
			"couldn't create/connect PDOE ".$this->_dberr($this->pdoe)
		);
	}

	function cmpTestRec($a1,$a2) {
		if(!is_array($a1)) throw new ErrorException('first argument to cmpTestRec is not an array');
		if(!is_array($a2)) throw new ErrorException('second argument to cmpTestRec is not an array');
		$v1 = array_values($a1);
		$v2 = array_values($a2);
		array_shift($v1);
		array_shift($v2);
		$rv = !array_diff($v1,$v2);
		return $rv;
	}

	function test_fetch() {
		return $this->assert(
			is_array($f1 = $this->pdoe->fetch('person',1)),
			'fetch 1 (mode id) failed'.$this->_dberr($this->pdoe)

		) && $this->assert(
			$this->cmpTestRec($f1,$this->rec1),
			'fetch 1 result not equal to rec1'

		) && $this->assert(
			is_array($f2 = $this->pdoe->fetch('person',array('firstname'=>'Chad'))),
			'fetch 2 (mode parameter) failed'.$this->_dberr($this->pdoe)

		) && $this->assert(
			$this->cmpTestRec($f2,$this->rec2),
			'fetch 2 result not equal to rec2'

		) && $this->assert(
			is_array($f3 = $this->pdoe->fetch('person')),
			'fetch 3 (no parameter/implicit last row) failed'.$this->_dberr($this->pdoe)

		) && $this->assert(
			$this->cmpTestRec($f3,$this->rec3),
			'fetch 3 result not equal to rec3'
		);
	}

	function test_insertrec() {
		$this->rec4 = array(null,'Vlad','Impaler','vlad@vampire.not','3215550000');
		return $this->assert(
			$this->r4id = $this->pdoe->insertrec('person',$this->rec4),
			"couldn't insert record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec4fetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't retrieve record 4 by id |{$this->r4id}| ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->cmpTestRec($this->rec4,$this->rec4fetched),
			"inserted record 4 differs from retrieved record 4"
		);
	}

	function test_updateSingleRecord() {
		$this->rec4fetched['phone'] = '8015550000';
		$this->rec4fetched['email'] = 'vlad@sparklers.not';
		return $this->assert(
			$this->pdoe->updaterec('person',$this->rec4fetched),
			"couldn't update record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec4refetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't refetch record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		);
	}

	function test_saveRecord() {
		$this->rec5 = array(null,'Zod','Of Krypton','bow2zod@phantomzone.not','3215552692');
		$rv = $this->assert(
			$this->rec4fetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't fetch record 4".$this->_dberr($this->pdoe)
		);
		$this->rec4fetched['phone'] = '9165550000';
		return $rv && $this->assert(
			$this->pdoe->saverec('person',$this->rec4fetched),
			"couldn't save record 4".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec4refetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't refetch saved record 4".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		) && $this->assert(
			$this->r5id = $this->pdoe->saverec('person',$this->rec5),
			"couldn't save record 5".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec5fetched = $this->pdoe->fetch('person',$this->r5id),
			"couldn't refetch saved record 5".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 5 doesn't matched saved record 5"
		);
	}

	function test_deleteRecord() {
		return $this->assert(
			$this->pdoe->deleterec('person',$this->r5id),
			"couldn't delete record 5"
		) && $this->assert(
			!($this->rec5fetched = $this->pdoe->fetch('person',$this->r5id)),
			"record 5 knew what it was supposed to do, but it didn't. It couldn't. It felt compelled to stay, compelled to disobey."
		);
	}

	/*function test_select() {
	} 

	function test_updateMultipleRecords() {
	} 

	function test_deleteMultipleRecords() {
	} 
	*/


	function test_destroyDB() {
		return $this->assert(
			$this->dbh->exec("DROP TABLE person"),
			"couldn't drop table person".implode(null,$this->dbh->errorInfo())
		) && $this->assert(
			unlink('./pdoetestdb'),
			"couldn't delete test db"
		);
	} 
}

?>
