<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Comment model to handle database operations for comments.
 * Using transactions for safe insert, update and delete operations
 * source: https://codeigniter.com/userguide3/database/transactions.html
 */
class CommentModel extends CI_Model
{
    function __construct()
    {
        $this->load->database();
    }

    /**
     * Inserts a new comment into the database.
     * ALso updates the post analytics by incrementing the comments count.
     */
    public function addComment($postId, $text, $userId)
    {
        $data = [
            'post_id' => $postId,
            'text' => $text,
            'user_id' => $userId
        ];

        $this->db->trans_start();

        $this->db->insert('shotwell_comments', $data);
        $insertId = $this->db->insert_id();

        if ($insertId) {
            // getting the newly added comment
            $this->db->select('c.id, c.post_id, c.text, c.user_id, u.first_name, u.last_name, u.img_path, u.email');
            $this->db->from('shotwell_comments c');
            $this->db->join('shotwell_users u', 'u.id = c.user_id');
            $this->db->where('c.id', $insertId);
            $newComment = $this->db->get()->row_array();

            // check for existing post analytics record
            $this->db->select('id, comments_count');
            $this->db->from('shotwell_post_analytics');
            $this->db->where('post_id', $postId);
            $analytics = $this->db->get()->row_array();

            if ($analytics) { // update existing record
                $this->db->set('comments_count', 'comments_count + 1', FALSE);
                $this->db->where('post_id', $postId);
                $this->db->update('shotwell_post_analytics');
            } else { // insert new record
                $analyticsData = [
                    'post_id' => $postId,
                    'comments_count' => 1,
                    'upvotes' => 0,
                    'downvotes' => 0
                ];
                $this->db->insert('shotwell_post_analytics', $analyticsData);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === TRUE && isset($newComment)) {
            return $newComment;
        } else {
            return false;
        }
    }

    /**
     * Updates the vote of a user for a comment.
     * Checks if the user has already voted on the comment,
     * in which case it removes the vote or toggles it,
     * depending on the new vote.
     */
    public function updateVote($userId, $commentId, $newVote)
    {
        $this->db->trans_start();

        if ($newVote === 'up') {
            $newVote = 1;
        } elseif ($newVote === 'down') {
            $newVote = 0;
        }

        // check current vote
        $this->db->select('vote');
        $this->db->from('shotwell_comment_votes');
        $this->db->where('user_id', $userId);
        $this->db->where('comment_id', $commentId);
        $query = $this->db->get();
        $currentVote = $query->row_array();

        if ($currentVote) { // if user has a vote
            if ($currentVote['vote'] == $newVote) { // same vote as before
                // negate it
                $this->db->where('user_id', $userId);
                $this->db->where('comment_id', $commentId);
                $this->db->delete('shotwell_comment_votes');
            } else { // opposite vote
                // update it
                $this->db->set('vote', $newVote);
                $this->db->where('user_id', $userId);
                $this->db->where('comment_id', $commentId);
                $this->db->update('shotwell_comment_votes');
            }
        } else { // if user has no previous vote
            // add a new vote
            $this->db->insert('shotwell_comment_votes', [
                'user_id' => $userId,
                'comment_id' => $commentId,
                'vote' => $newVote
            ]);
        }

        // using SUM sql function to get number of upvotes and downvotes
        $this->db->select('SUM(vote = 1) AS upvotes, SUM(vote = 0) AS downvotes');
        $this->db->from('shotwell_comment_votes');
        $this->db->where('comment_id', $commentId);
        $voteCounts = $this->db->get()->row_array();

        // also update comments table
        if ($voteCounts) {
            $this->db->where('id', $commentId);
            $this->db->update('shotwell_comments', [
                'upvotes' => $voteCounts['upvotes'],
                'downvotes' => $voteCounts['downvotes']
            ]);
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === TRUE) {
            return $voteCounts;
        } else {
            return false;
        }
    }

    /**
     * Deletes a comment from the database.
     * First deletes the comment votes.
     * Updates the post analytics by decrementing the comments count.
     */
    public function deleteComment($commentId)
    {
        $this->db->trans_start();

        // get comment
        $this->db->select('post_id');
        $this->db->from('shotwell_comments');
        $this->db->where('id', $commentId);
        $query = $this->db->get();
        $result = $query->row_array();

        if (!$result) {
            $this->db->trans_rollback();
            return false;
        }
        $postId = $result['post_id'];

        // delete the comment votes
        $this->db->where('comment_id', $commentId);
        $this->db->delete('shotwell_comment_votes');

        // delete the comment itself
        $this->db->where('id', $commentId);
        $this->db->delete('shotwell_comments');

        // check if other comments remain for post
        $this->db->select('COUNT(id) AS remaining_comments');
        $this->db->from('shotwell_comments');
        $this->db->where('post_id', $postId);
        $count_result = $this->db->get()->row_array();
        $remaining_comments = $count_result['remaining_comments'];

        // update the comments count in post analytics
        $this->db->where('post_id', $postId);
        $this->db->set('comments_count', $remaining_comments, FALSE);
        $this->db->update('shotwell_post_analytics');

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Checks if a comment belongs to a specific user.
     */
    public function checkUserComment($userId, $commentId)
    {
        $this->db->from('shotwell_comments');
        $this->db->where('id', $commentId);
        $this->db->where('user_id', $userId);
        $query = $this->db->get();

        return $query->num_rows() > 0;
    }
}
