<?php 

/**
*  Corresponding Class to test YourClass class
*
*  For each class in your library, there should be a corresponding Unit-Test for it
*  Unit-Tests should be as much as possible independent from other test going on.
*
*  @author yourname
*/
class SteemLayerTest extends PHPUnit_Framework_TestCase{
	
  /**
  * Just check if the YourClass has no syntax error 
  *
  * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
  * any typo before you even use this library in a real project.
  *
  */
  public function testIsThereAnySyntaxError(){
	$var = new DragosRoua\SteemApiTools\SteemLayer;
	$this->assertTrue(is_object($var));
	unset($var);
  }
  
  /**
  * Just check if the YourClass has no syntax error 
  *
  * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
  * any typo before you even use this library in a real project.
  *
  */
  
  public function testgetRequest(){
	$var = new DragosRoua\SteemApiTools\SteemLayer;
	$method = 'method';
	$params = 'params';
	$request = array(
            "jsonrpc" => "2.0",
            "method" => $method,
            "params" => $params,
            "id" => 0
            );
    $request_json = json_encode($request);        
	$this->assertTrue($var->getRequest('method','params') == $request_json);
	unset($var);
  }
  
}