<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . '/libraries/RestController.php';
require APPPATH . '/libraries/Format.php';

use chriskacerguis\RestServer\RestController;

/**
 * Authentication controller, handling account-related functionality.
 * Uses the user model to handle database operations for user data.
 * 
 * I used this REST structure as a guide for responses and dealing with model functions:
 * https://www.fundaofwebit.com/post/codeigniter-3-restful-api-tutorial-using-postman
 * (used across all classes)
 * 
 * Form validation methods source:
 * https://codeigniter.com/userguide3/libraries/form_validation.html
 * (used across many classes)
 * 
 * When I use the form validation library, I have to manually parse json payload from client,
 * because the form validator expects html form data, but axios (http client library) sends json data.
 * Source: https://stackoverflow.com/questions/8596216/post-json-to-codeigniter-controller
 * (used across many classes)
 * 
 * Also using CodeIgniter's security library to sanitise inputs for manual parsing.
 * Source: https://codeigniter.com/userguide3/libraries/security.html#xss-filtering
 * (used across many classes)
 */
class Auth extends RestController
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('UserModel');
		// uses html in the validation errors by default, this is preventing it so the client can render it manually
		$this->form_validation->set_error_delimiters('', '');
	}

	/**
	 * Register POST endpoint.
	 * Accepts an email and password.
	 * Uses the form validation library of CodeIgniter.
	 * Calls the register function of the user model to register the user.
	 */
	public function register_post()
	{
		// manually getting json payload from client
		$data = json_decode($this->security->xss_clean($this->input->raw_input_stream), true);
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[shotwell_users.email]');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() === FALSE) {
			$this->response([
				'error' => validation_errors()
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$email = $this->post('email');
		$password = $this->post('password');

		try {
			$registration_success = $this->UserModel->register($email, $password);
			if (!$registration_success) {
				$this->response([
					'error' => 'User registration failed'
				], RestController::HTTP_INTERNAL_ERROR);
				return;
			}

			$this->response([
				'message' => 'User registered successfully'
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Login POST endpoint.
	 * Accepts an email and password.
	 * Uses the form validation library of CodeIgniter.
	 * Calls the login function of the user model to log in the user.
	 * Sets session for user.
	 */
	public function login_post()
	{
		// manually getting json payload from client
		$data = json_decode($this->security->xss_clean($this->input->raw_input_stream), true);
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() === FALSE) {
			$this->response([
				'error' => validation_errors()
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$email = $this->post('email');
		$password = $this->post('password');

		try {
			$user = $this->UserModel->login($email, $password);
			if (!$user) {
				$this->response([
					'error' => 'Invalid email or password'
				], RestController::HTTP_UNAUTHORIZED);
				return;
			}

			// creating the session
			$this->session->set_userdata('user', [
				'user_id' => $user['id'],
				'email' => $user['email'],
			]);

			$this->response([
				'message' => 'Login successful',
				'user' => $user
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Logout GET endpoint.
	 * Deletes the active session of the user.
	 */
	public function logout_get()
	{
		if (!$this->session->userdata('user')) {
			$this->response([
				'error' => 'No user is currently logged in.'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$this->session->unset_userdata('user');
		$this->session->sess_destroy();

		$this->response([
			'message' => 'User logged out successfully'
		], RestController::HTTP_OK);
	}

	/**
	 * Account DELETE endpoint.
	 * Accepts a password, checks if the password is correct.
	 * Calls the delete function of the user model to delete the user.
	 * Also deletes the active session of the user.
	 */
	public function account_delete()
	{
		// manually getting json payload from client
		$data = json_decode($this->security->xss_clean($this->input->raw_input_stream), true);
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() === FALSE) {
			$this->response([
				'error' => validation_errors()
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$userId = $this->session->userdata('user')['user_id'];
		if (!$userId) {
			$this->response([
				'error' => 'User not authenticated'
			], RestController::HTTP_UNAUTHORIZED);
			return;
		}

		$inputPassword = $data['password'];

		try {
			if (!$this->UserModel->validateUserPassword($userId, $inputPassword)) {
				$this->response([
					'error' => 'Invalid password'
				], RestController::HTTP_UNAUTHORIZED);
				return;
			}

			$deleted = $this->UserModel->deleteUser($userId);
			if (!$deleted) {
				$this->response([
					'error' => 'An error occurred while deleting the account'
				], RestController::HTTP_INTERNAL_ERROR);
				return;
			}

			// deleting the session
			$this->session->sess_destroy();

			$this->response([
				'message' => 'Account deleted successfully'
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Session GET endpoint.
	 * Returns the user who has an active session.
	 * Used by the client on startup to check if the user is logged in.
	 */
	public function session_get()
	{
		if ($this->session->userdata('user')) {
			$user = $this->UserModel->getUser($this->session->userdata('user')['user_id']);
			$this->response([
				'isAuthenticated' => true,
				'user' => $user
			], RestController::HTTP_OK);
		} else {
			$this->response([
				'isAuthenticated' => false
			], RestController::HTTP_OK);
		}
	}
}
