<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Catz extends CI_Controller {
	
	function __construct()
	{
		parent::__construct();
		$this->load->model('Cat');
	}

	// Web 1.0 version
	public function select()
	{
		$newcat = $this->Cat->getcat();
		// build a full web page containing the URL
		$this->load->view('catzview',array('cat' => $newcat));
	}
	
	// Web 2.0 version
	public function select2()
	{
		$newcat = $this->Cat->getcat();
		// build a full web page containing the URL
		$this->load->view('catzview2',array('cat' => $newcat));
	}

	// used by Web 2.0 version to get URL of next cat
	public function geturl()
	{
		$newcat = $this->Cat->getcat();
		log_message('DEBUG',"*** NEW CAT URL IS " . $newcat);
		echo $newcat;
	}
}
