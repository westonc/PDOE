<?php

define('TEST_SET_ABORT',-1);
define('ABORT_ON_FAIL',1);
define('_T','    ');
$_t = _T;

class WTestSet {

	var $log;

	function __construct() { 
		$this->clearLog(); 
	}

	function clearLog() {
		$this->log = array(); 
	}

	function bookend($prefix,$indent=_T,$abortOnFail=false) {
		$failure = false;
		$symbol = ($prefix == 'setup') ? 'α' : ( ($prefix == 'takedown') ? 'Ω' : '-' );
		foreach(get_class_methods(get_class($this)) as $methodName) {
			$matches = array();
			if(strpos($methodName,$prefix.'_') === 0) {
				echo $indent,"$symbol $methodName: ";
				$this->clearLog();
				try {
					if($this->$methodName()) 
						echo "done\n";
					else {
						echo "fail (",$this->log2str(', '),")\n";
						if($abortOnFail) {
							$failure = true;				
							break;
						}
					}
				} catch(Exception $e) {
					echo "fail (exception: ",$e->getMessage()," [line ",$e->getLine()," of ",$e->getFile(),"] ) \n";
					if($abortOnFail) {
						$failure = true;				
						break;
					}
				}
			}
		}
		if($failure && $abortOnFail) {
			$fail_flag_name = "_{$prefix}_failed";
			$this->$fail_flag_name = true;
		}
		return !$failure;
	}

	function setup($indent=_T) {  // extend and/or use default small setup_ methods
		return $this->bookend('setup',$indent,ABORT_ON_FAIL);
	}

	function run($indent=_T) {
		if(isset($this->_setup_failed)) return 0;
			
		$testMethods = array();
		foreach(get_class_methods(get_class($this)) as $method) {
			$matches = array();
			if(preg_match('/^test_?(.*)$/',$method,$matches)) {
				$testMethods[$matches[1]] = $method;
			}
		}
		$testMethodCount = count($testMethods);
		echo $indent,$testMethodCount," test methods\n";
		$passed = 0; $failed = 0; $i = 0;
		foreach($testMethods as $testName => $methodName) {
			$i++;
			echo $indent,"#[$i/$testMethodCount] $testName: ";
			$this->clearLog();
			try {
				if($this->$methodName()) {
					$passed++;
					echo "pass\n";
				} else {
					$failed++;
					echo "fail (",$this->log2str(', '),")\n";
				}
			} catch(Exception $e) {
				$failed++;
				echo "fail (exception: ",$e->getMessage()," [line ",$e->getLine()," of ",$e->getFile(),"] ) \n";
				if($e->getCode() == TEST_SET_ABORT) {
					echo $indent,"This is a test set aborting failure, exiting test set\n";
					break;
				}
			}
		}
		echo $indent,"pass: $passed  fail: $failed total: $testMethodCount\n";

		return ($passed/$testMethodCount);
	}

	function takedown($indent=_T) { //extend or use default
		return $this->bookend('takedown',$indent);
	}

	function assert($condition,$failmsg) {
		if(!$condition) {
			$this->log($failmsg);
			return false;
		} else 
			return $this;
	}

	function log() {
		$args = func_get_args();
		$this->log[] = implode(null,$args);
	}

	function last_log() {
		return end($this->log);
	}

	function log2str($sep="\n") {
		return implode($sep,$this->log);
	}

}

?>
