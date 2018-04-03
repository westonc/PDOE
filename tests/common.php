<?php 

function readln() {
	$fp=fopen("/dev/stdin", "r");
	$input=fgets($fp, 255);
	fclose($fp);
	return rtrim($input);
}

function dberr($dbh) {
	return implode(',',$dbh->errorInfo());
}

function cmpTestRec($fetched,$reference) {
	//if(!is_array($a1)) throw new ErrorException('first argument to cmpTestRec is not an array');
	//if(!is_array($a2)) throw new ErrorException('second argument to cmpTestRec is not an array');
	$copy = $fetched;
	unset($copy[0]);
	unset($copy['id']);
	$fetched_values = array_values($copy);
	$reference_values = array_values($reference);
	$rv = !array_diff($fetched_values,$reference_values);
	return $rv;
}

function reduce_cb($row,$acc = 1) { 
	$acc = $acc ? $acc : 1;
	return $row['id'] * $acc; 
}

function map_cb($row) { 
	return strlen($row['lastname']); 
}

function walk_cb($row) { 
	echo ' ',$row['firstname']; 
}

class _Test_Walk {
	/* Can't nest classes in PHP? Otherwise this would be in test_operate */
	function withRow($row) { echo "\n",implode(',',$row); }
	function collectIds($row) { if(isset($row['id'])) $this->ids[] = $row['id']; }
	function collectFirstNames($row) { 
		if(isset($row['firstname'])) $this->firstnames[] = $row['firstnames']; 
	}
	function collectLastNames($row) { 
		if(isset($row['lastname'])) $this->lastnames[] = $row['lastnames']; 
	}
	function collectEmails($row) { 
		if(isset($row['email'])) $this->emails[] = $row['email']; 
	}
	function collectPhones($row) { 
		if(isset($row['phone'])) $this->phones[] = $row['phone']; 
	}
}

class Test_PDOE_common extends WTestSet {

	function _dberr($dbh) {
		return implode(',',$dbh->errorInfo());
	}

	function test_fetch() {
		return $this->assert(
			is_array($f1 = $this->pdoe->fetch('person',1)),
			'fetch 1 (mode id) failed (yielded '.var_export($f1,true).') ( DB ERROR: '.$this->_dberr($this->pdoe).' )'

		) && $this->assert(
			cmpTestRec($f1,$this->rec1),
			'fetch 1 result not equal to rec1'

		) && $this->assert(
			is_array($f2 = $this->pdoe->fetch('person',array('firstname'=>'Chad'))),
			'fetch 2 (mode parameter) failed '.$this->_dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($f2,$this->rec2),
			'fetch 2 result not equal to rec2'

		) && $this->assert(
			is_array($f3 = $this->pdoe->fetch('person')),
			'fetch 3 (no parameter/implicit last row) failed'.$this->_dberr($this->pdoe)

		) && $this->assert(
			cmpTestRec($f3,$this->rec3),
			'fetch 3 ('.var_export($f3,true).') result not equal to rec3'
		);
	}

	function test_insertrec() {
		$this->rec4 = [
			'firstname' => 'Vlad',
			'lastname' => 'Impaler',
			'email' => 'vlad@vampire.not',
			'phone' => '3215550000'
		];
		return $this->assert(
			$this->r4id = $this->pdoe->insertrec('person',$this->rec4),
			"couldn't insert record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec4fetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't retrieve record 4 by id |{$this->r4id}| ".$this->_dberr($this->pdoe)
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
			"couldn't update record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec4refetched = $this->pdoe->fetch('person',$this->r4id),
			"couldn't refetch record 4 ".$this->_dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		);
	}

	function test_saveRecord() {
		$this->rec5 = [
			'firstname' => 'Zod',
			'lastname' => 'Of Krypton',
			'email' => 'bow2zod@phantomzone.not',
			'phone' => '3215552692'
		];
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
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 4 doesn't matched modified record 4"
		) && $this->assert(
			$this->r5id = $this->pdoe->saverec('person',$this->rec5),
			"couldn't save record 5".$this->_dberr($this->pdoe)
		) && $this->assert(
			$this->rec5fetched = $this->pdoe->fetch('person',$this->r5id),
			"couldn't refetch saved record 5".$this->_dberr($this->pdoe)
		) && $this->assert(
			cmpTestRec($this->rec4refetched,$this->rec4fetched),
			"refetched record 5 doesn't matched saved record 5"
		);
	}

	function test_findEverything() {
		$persons = $this->pdoe->find('person');
		if(! ($first = reset($persons)) )
			return true;
		$keys = array_keys($first);
		echo implode("\t",$keys),"\n";
		foreach($persons as $person) 
			echo implode("\t",array_values($person)),"\n";
		return true;
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

}





