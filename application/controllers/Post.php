<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . '/libraries/RestController.php';
require APPPATH . '/libraries/Format.php';

use chriskacerguis\RestServer\RestController;

/**
 * Post controller, handling post-related functionality.
 * Uses the post model to handle database operations for post data.
 */
class Post extends RestController
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('PostModel');
		$this->load->helper(['url', 'file']);
	}

	/**
	 * Fetches all posts.
	 */
	public function all_get()
	{
		if (!$this->session->userdata('user')) {
			$this->response([
				'error' => 'User not authenticated'
			], RestController::HTTP_UNAUTHORIZED);
			return;
		}

		try {
			$posts_with_analytics = $this->PostModel->getAll();
			$this->response([
				'posts' => $posts_with_analytics
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Fetches a single post using its ID.
	 */
	public function single_get()
	{
		$postId = urldecode($this->uri->segment(3));

		if (empty($postId)) {
			$this->response([
				'error' => 'No post ID provided'
			], RestController::HTTP_BAD_REQUEST);
		}

		try {
			$post = $this->PostModel->getSingle($postId);
			$this->response([
				'post' => $post
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Fetches a list of posts created by a specific user.
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
			$posts = $this->PostModel->getPostsByUser($userId);
			$this->response([
				'posts' => $posts
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
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
			$config['encrypt_name'] = TRUE; // renames files to prevent duplicates

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
	 * Utility function to get the public url of a newly uploaded image.
	 */
	private function get_public_image_url($fileName)
	{
		$basePath = str_replace('/api', '', base_url());
		return $basePath . 'images/' . $fileName;
	}

	/**
	 * Checks if the title, description and tags have been provided.
	 */
	private function validatePostData($data)
	{
		return !empty($data['title']) && !empty($data['desc']) && !empty($data['tags']);
	}

	/**
	 * Handles the creation of a new post.
	 * Uses the handleFileUpload function to upload the image.
	 * Also initialises analytics for the post.
	 */
	public function post_post()
	{
		$imgPath = $this->handleFileUpload();
		if ($imgPath === false) {
			return;
		}

		$user_id = $this->session->userdata('user')['user_id'];
		$postData = [
			'user_id' => $user_id,
			'title' => $this->post('title'),
			'desc' => $this->post('desc'),
			'tags' => $this->post('tags'),
			'img_path' => $imgPath
		];

		if (!$this->validatePostData($postData)) {
			$this->response([
				'error' => 'Validation failed. Make sure all fields are correct!'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		try {
			$postId = $this->PostModel->insertPost($postData);
			if ($postId) {
				$this->response([
					'message' => 'Post created successfully',
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to create post'
				], RestController::HTTP_INTERNAL_ERROR);
			}

			$result = $this->PostModel->initPostAnalytics($postId);
			if (!$result) {
				$this->response([
					'error' => 'Failed to initialise post analytics'
				], RestController::HTTP_INTERNAL_ERROR);
			} else {
				$this->response([
					'message' => 'Post created successfully'
				], RestController::HTTP_OK);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the update of an existing post.
	 */
	public function post_put()
	{
		$postId = $this->uri->segment(3);
		if (!$postId) {
			$this->response([
				'error' => 'Missing post ID'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$postData = [
			'title' => $this->put('title'),
			'desc' => $this->put('desc'),
			'tags' => $this->put('tags')
		];

		try {
			$result = $this->PostModel->updatePost($postId, $postData);
			if (!$result) {
				$this->response([
					'error' => 'Failed to update post'
				], RestController::HTTP_INTERNAL_ERROR);
			}

			$this->response([
				'message' => 'Post updated successfully'
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the deletion of a post.
	 */
	public function post_delete()
	{
		$postId = $this->uri->segment(3);
		if (empty($postId)) {
			$this->response([
				'error' => 'No post ID provided'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		try {
			$result = $this->PostModel->deletePost($postId);
			if ($result) {
				$this->response([
					'message' => 'Post deleted successfully'
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to delete post'
				], RestController::HTTP_INTERNAL_ERROR);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the upvoting and downvoting of a post.
	 */
	public function vote_post()
	{
		$postId = $this->uri->segment(3);
		if (!$postId) {
			$this->response([
				'error' => 'Missing post ID'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$newVote = $this->post('vote');
		// specifies allowed votes
		if (!in_array($newVote, ['up', 'down'])) {
			$this->response([
				'error' => 'Invalid vote type'
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

		try {
			$voteCounts = $this->PostModel->updateVote($userId, $postId, $newVote);
			if ($voteCounts) {
				$this->response([
					'message' => 'Vote successfully updated',
					'votes' => $voteCounts
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to update vote'
				], RestController::HTTP_INTERNAL_ERROR);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the searching of posts based on a search term and tags.
	 */
	public function search_post()
	{
		$searchTerm = $this->post('searchTerm');
		$searchTags = $this->post('searchTags');

		if (empty($searchTerm) && empty($searchTags)) {
			$this->all_get();
			return;
		}

		try {
			$posts = $this->PostModel->searchPosts($searchTerm, $searchTags);
			$this->response([
				'posts' => $posts
			], RestController::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}
}
