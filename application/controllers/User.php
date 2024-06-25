<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . '/libraries/RestController.php';
require APPPATH . '/libraries/Format.php';

use chriskacerguis\RestServer\RestController;

/**
 * User controller, handling user-related functionality after logging in.
 * Uses the user model to handle database operations for user data.
 */
class User extends RestController
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('UserModel');
	}

	/**
	 * Reads the user id from the uri, 
	 * fetches the user, and returns it.
	 */
	public function user_get()
	{
		$userId = $this->uri->segment(3);
		if (!$userId) {
			$this->response([
				'error' => 'User not authenticated'
			], RestController::HTTP_UNAUTHORIZED);
			return;
		}

		try {
			$user = $this->UserModel->getUser($userId);
			if (!$user) {
				$this->response([
					'message' => 'User not found'
				], RestController::HTTP_NOT_FOUND);
			} else {
				$this->response([
					'user' => $user
				], RestController::HTTP_OK);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Utility function to get the public url of a newly uploaded image.
	 */
	private function get_public_image_url($fileName)
	{
		$basePath = str_replace('/api', '', base_url());
		return $basePath . 'images/' . $fileName;
	}

	/**
	 * Handles the file upload and returns the public url of the uploaded image.
	 * Source: https://codeigniter.com/userguide3/libraries/file_uploading.html
	 */
	private function handleFileUpload()
	{
		if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != '') {
			$config['upload_path'] = '../images/';
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['max_size'] = 10000;
			$config['encrypt_name'] = TRUE; // renames files to prevent duplicates on disk

			$this->load->library('upload', $config);

			if (!$this->upload->do_upload('file')) {
				$this->response([
					'error' => $this->upload->display_errors('', '')
				], RestController::HTTP_BAD_REQUEST);
				return false;
			}

			$fileData = $this->upload->data();
			return $this->get_public_image_url($fileData['file_name']);
		}
		return null; // no file uploaded
	}

	/**
	 * Updates the user with the new data.
	 * Uses the handleFileUpload function to upload the image.
	 * 
	 * Using POST instead of PUT for this update, 
	 * because php doesn't support file uploads with PUT.
	 * `multipart/form-data` is only available for POST.
	 * 
	 * Sources describing the same problem:
	 * - https://stackoverflow.com/a/9469615
	 * - https://codereview.stackexchange.com/q/69882
	 */
	public function user_post()
	{
		$userId = $this->uri->segment(3);
		if (!$userId) {
			$this->response([
				'error' => 'User not authenticated'
			], RestController::HTTP_UNAUTHORIZED);
			return;
		}

		$data = [];
		$firstName = $this->post('first_name');
		if ($firstName !== null) {
			$data['first_name'] = $firstName;
		}
		$lastName = $this->post('last_name');
		if ($lastName !== null) {
			$data['last_name'] = $lastName;
		}

		$uploadedImagePath = $this->handleFileUpload();
		if ($uploadedImagePath === false) {
			return;
		}

		if ($uploadedImagePath) { // if a new image is uploaded
			// update image path
			$data['img_path'] = $uploadedImagePath;

			// delete the old image
			$existingImagePath = $this->UserModel->getImagePath($userId);
			if ($existingImagePath && file_exists($existingImagePath)) {
				unlink($existingImagePath);
			}
		} elseif ($this->post('img_path') === '') { // if no new image is uploaded, or the old image is removed
			// remove existing image
			$data['img_path'] = '';
			$existingImagePath = $this->UserModel->getImagePath($userId);
			if ($existingImagePath && file_exists($existingImagePath)) {
				unlink($existingImagePath);
			}
		}

		if (!empty($data) && $this->UserModel->updateUser($userId, $data)) {
			$this->response([
				'message' => 'User updated successfully'
			], RestController::HTTP_OK);
		} else {
			$this->response([
				'error' => 'Failed to update user'
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}
}
