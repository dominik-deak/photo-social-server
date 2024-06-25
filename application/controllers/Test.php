<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . '/libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

/**
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array
 *
 * @package         CodeIgniter
 * @subpackage      Rest Server
 * @category        Controller
 * @author          Phil Sturgeon, Chris Kacerguis
 * @license         MIT
 * @link            https://github.com/chriskacerguis/codeigniter-restserver
 */
class Test extends RestController
{
    function __construct()
    {
        // Construct the parent class
        parent::__construct();

        // Configure limits on our controller methods
        // Ensure you have created the 'limits' table and enabled 'limits' within application/config/rest.php
        // See the rest.php config file for the SQL to set up the limits file (limits are disabled by default)
        $this->methods['users_get']['limit'] = 500; // 500 requests per hour per user/key
        $this->methods['users_post']['limit'] = 100; // 100 requests per hour per user/key
        $this->methods['users_delete']['limit'] = 50; // 50 requests per hour per user/key
    }

    // called when a GET request is made for test/index - note the URL is for test/index, not test/index_get
    public function index_get()
    {
        $this->load->view('testview');
    }

    public function index_post()
    {
        // just a test message to know the right function has been called
        print "hello, index_post() called";
    }

    public function index_put()
    {
        // just a test message to know the right function has been called
        print "hello, index_put() called";
    }

    // called when the ajax call in testview calls Test/index with the DELETE request method
    public function index_delete()
    {
        // just a test message to know the right function has been called
        print "hello, index_delete() called";
    }
}
