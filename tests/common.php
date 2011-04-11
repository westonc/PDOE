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





