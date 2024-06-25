<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . '/libraries/RestController.php';
require APPPATH . '/libraries/Format.php';

use chriskacerguis\RestServer\RestController;

/**
 * Comment controller, handling comment-related functionality.
 * Uses the comment model to handle database operations for comment data.
 */
class Comment extends RestController
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CommentModel');
	}

	/**
	 * Handles the creation of a new comment.
	 */
	public function comment_post()
	{
		$postId = $this->uri->segment(3);
		if (!$postId) {
			$this->response([
				'error' => 'Missing post ID'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$commentText = $this->post('text');
		if (!$commentText) {
			$this->response([
				'error' => 'Comment text is required'
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
			$newComment = $this->CommentModel->addComment($postId, $commentText, $userId);
			if ($newComment) {
				$this->response([
					'message' => 'Comment successfully added',
					'comment' => $newComment
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to add comment'
				], RestController::HTTP_INTERNAL_ERROR);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the upvoting and downvoting of a comment.
	 */
	public function vote_post()
	{
		$commentId = $this->uri->segment(3);
		if (!$commentId) {
			$this->response([
				'error' => 'Missing comment ID'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$vote = $this->post('vote');
		if (!in_array($vote, ['up', 'down'])) {
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
			$voteCounts = $this->CommentModel->updateVote($userId, $commentId, $vote);
			if ($voteCounts) {
				$this->response([
					'message' => 'Vote successfully updated',
					'votes' => $voteCounts
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to add vote'
				], RestController::HTTP_INTERNAL_ERROR);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	/**
	 * Handles the deletion of a comment.
	 */
	public function comment_delete()
	{
		$commentId = $this->uri->segment(3);
		if (!$commentId) {
			$this->response([
				'error' => 'Missing comment ID'
			], RestController::HTTP_BAD_REQUEST);
			return;
		}

		$userId = $this->session->userdata('user')['user_id'];
		$isAuthorised = $this->CommentModel->checkUserComment($userId, $commentId);
		if ($isAuthorised === null) {
			$this->response([
				'error' => 'Failed to check user access'
			], RestController::HTTP_UNAUTHORIZED);
			return;
		} else {
			if (!$isAuthorised) {
				$this->response([
					'error' => 'Unauthorised access'
				], RestController::HTTP_UNAUTHORIZED);
				return;
			}
		}

		try {
			$result = $this->CommentModel->deleteComment($commentId);
			if ($result) {
				$this->response([
					'message' => 'Comment successfully deleted'
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'error' => 'Failed to delete comment'
				], RestController::HTTP_INTERNAL_ERROR);
			}
		} catch (Exception $e) {
			$this->response([
				'error' => $e->getMessage()
			], RestController::HTTP_INTERNAL_ERROR);
		}
	}
}
