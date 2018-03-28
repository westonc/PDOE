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

class Test_PDOE_MySQL extends WTestSet {


	function test_check_pdo_mysql_install() {
		$drivers = PDO::getAvailableDrivers();
		return $this->assert(array_search('mysql',$drivers),
			"No MySQL Driver"
		);
	}

	function test_connect() {
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
			"couldn't connect to MySQL database ".dberr($this->dbh)
		);
	}

	function test_create_table() {
		$this->rec1 = array(null, 'John','Smith','jsmith@devnull.not','3215559876');
		$this->rec2 = array(null, 'Chad','Vader','chadv@empire.not','3215550123');
		$this->rec3 = array(null, 'Moo','Cow','moo@pasture.not','3215559876');

		return $this->assert(
			!($this->dbh->exec("CREATE TABLE person (id INT(11) not null auto_increment, firstname varchar(63), lastname varchar(63), email varchar(255), phone varchar(31), PRIMARY KEY (id) )") === false),
			"couldn't create person table ".dberr($this->dbh)
		) && $this->assert(
			$sth = $this->dbh->prepare("INSERT INTO person VALUES (?,?,?,?,?)"),
			"couldn't prepare insert statement ".dberr($this->dbh)
		) && $this->assert(
			!($sth->execute($this->rec1) === false) &&
			!($sth->execute($this->rec2) === false) &&
			!($sth->execute($this->rec3) === false),
			"couldn't insert records ".dberr($this->dbh)
		);
	}

	function test_createPDOE() {
		$db	= $this->_db;
		$dsn = "mysql:host={$db['host']};dbname={$db['database']}";
		return $this->assert(
			$this->pdoe = new PDOE($dsn,$db['user'],$db['password']),
			"couldn't create/connect PDOE ".dberr($this->pdoe)
		);
	}

	function test_fetch() {
		return $this->assert(
			is_array($f1 = $this->pdoe->fetch('person',1)),
			'fetch 1 (mode id) failed (yielded '.var_export($f1,true).') ( DB ERROR: '.dberr($this->pdoe).' )'

		) && $this->assert(
			cmpTestRec($f1,$this->rec1),
			'fetch 1 result not equal to rec1'

		) && $this->assert(
			is_array($f2 = $this->pdoe->fetch('person',array('firstname'=>'Chad'))),
			'fetch 2 (mode parameter) failed '.dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($f2,$this->rec2),
			'fetch 2 result not equal to rec2'

		) && $this->assert(
			is_array($f3 = $this->pdoe->fetch('person')),
			'fetch 3 (no parameter/implicit last row) failed'.dberr($this->pdoe)

		) && $this->assert(
			cmpTestRec($f3,$this->rec3),
			'fetch 3 ('.var_export($f3,true).') result not equal to rec3'
		);
	}

	function test_insertrec() {
		$this->rec4 = array(null,'Vlad','Impaler','vlad@vampire.not','3215550000');
		return $this->assert(
			$this->r4id = $this->pdoe->insertrec('person',$this->rec4),
			"couldn't insert record 4 ".dberr($this->pdoe)
		) && $this->assert(
			$this->rec4fetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't retrieve record 4 by id |{$this->r4id}| ".dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4,$this->rec4fetched),
			"inserted record 4 differs from retrieved record 4"
		);
	}

	function test_updateSingleRecord() {
		$this->rec4fetched['phone'] = '8015550000';
		$this->rec4fetched['email'] = 'vlad@sparklers.not';
		return $this->assert(
			$this->pdoe->updaterec('person',$this->rec4fetched),
			"couldn't update record 4 ".dberr($this->pdoe)
		) && $this->assert(
			$this->rec4refetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't refetch record 4 ".dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		);
	}

	function test_saveRecord() {
		$this->rec5 = array(null,'Zod','Of Krypton','bow2zod@phantomzone.not','3215552692');
		$rv = $this->assert(
			$this->rec4fetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't fetch record 4".dberr($this->pdoe)
		);
		$this->rec4fetched['phone'] = '9165550000';
		return $rv && $this->assert(
			$this->pdoe->saverec('person',$this->rec4fetched),
			"couldn't save record 4".dberr($this->pdoe)
		) && $this->assert(
			$this->rec4refetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't refetch saved record 4".dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		) && $this->assert(
			$this->r5id = $this->pdoe->saverec('person',$this->rec5),
			"couldn't save record 5".dberr($this->pdoe)
		) && $this->assert(
			$this->rec5fetched = $this->pdoe->fetch('person',$this->r5id),
			"couldn't refetch saved record 5".dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
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

/* Database record state at this point:
1,John,Smith,jsmith@devnull.not,3215559876
2,Chad,Vader,chadv@empire.not,3215550123
3,Moo,Cow,moo@pasture.not,3215559876
4,Vlad,Impaler,vlad@sparklers.not,9165550000 */

	function test_reduce() {

		$idsReduced = $this->pdoe->reduce(array(
			'table'		=> 'person',
			'callback'	=> 'reduce_cb'));
		return $this->assert($idsReduced == 1*2*3*4,"reduction $idsReduced instead of 24");
	}

	function test_map() {

		$namesMapped = $this->pdoe->map(array(
			'table'		=> 'person',
			'callback'	=> 'map_cb'));

		return $this->assert($namesMapped == array(5,5,3,7),
			"map [".implode(',',$namesMapped)."] instead of [5,5,3,7]");
	}

	function test_walk() {

		ob_start();

		$this->pdoe->walk(array(
			'table'		=> 'person',
			'callback'	=> 'walk_cb'));	
		$buf = ob_get_clean();
		$ref = ' John Chad Moo Vlad';
		return $this->assert($buf == $ref,"collector got $buf instead of $ref");

	}

	function test_walk_obj_cb() {

		$ts = new _Test_Walk();

		$this->pdoe->walk(array(
			'sql'		=> 'SELECT * FROM person',
			'callback'	=> array($ts,'collectPhones')
		));

		$ref = array('3215559876','3215550123','3215559876','9165550000');

		return $this->assert($ts->phones == $ref,
			'collected phones ['.implode(',',$ts->phones).'] instead of ['.implode(',',$ref).']'
		);
	} 

	function test_updateMultipleRecords() {
		$this->pdoe->updaterec('person',
			array('firstname' => 'Bruce'), 	//Mind if we call you Bruce?
			':id > 2'
		);
		$this->rec3fetched = $this->pdoe->fetch('person',3);
		$this->rec4fetched = $this->pdoe->fetch('person',4);

		return 
			$this->assert($this->rec3fetched['firstname'] == 'Bruce',
				'record 3 firstname not changed to Bruce')
		 && $this->assert($this->rec4fetched['firstname'] == 'Bruce',
				'record 4 firstname not changed to Bruce');
	} 

	function test_deleteMultipleRecords() {
		$where = array('firstname' => 'Bruce');
		$this->pdoe->deleterec('person',$where);
		return 
			$this->assert(!$this->pdoe->fetch('person',$where),
				'Not all Bruces deleted');
	} 

	function test_destroyDB() {
		$this->dbh->exec("DROP TABLE person");
		return $this->assert(
			"couldn't drop table person ".dberr($this->dbh)
		);
	} 
}


