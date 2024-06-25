<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Post model to handle database operations for post-related data.
 * Using transactions for safe insert, update and delete operations
 * source: https://codeigniter.com/userguide3/database/transactions.html
 */
class PostModel extends CI_Model
{
    function __construct()
    {
        $this->load->database();
    }

    /**
     * Return all posts from the database.
     * Includes their analytics.
     */
    public function getAll()
    {
        $this->db->select(
            'shotwell_posts.*, 
            shotwell_post_analytics.upvotes, shotwell_post_analytics.downvotes, shotwell_post_analytics.comments_count'
        );
        $this->db->from('shotwell_posts');

        // using left join so all posts are returned even if there is no analytics data
        // (there should always be analytics data, this is just for added safety)
        // Source: https://codeigniter.com/userguide3/database/query_builder.html?highlight=join
        $this->db->join('shotwell_post_analytics', 'shotwell_post_analytics.post_id = shotwell_posts.id', 'left');
        $query = $this->db->get();

        return $query->result_array();
    }

    /**
     * Return a single post from the database by ID.
     * Includes analytics and comments.
     * Includes the user details of the poster and the commenters.
     */
    public function getSingle(string $postId)
    {
        // get the post with analytics and the poster user details
        $this->db->select(
            'shotwell_posts.id, shotwell_posts.title, shotwell_posts.desc, shotwell_posts.tags, 
            shotwell_posts.img_path, shotwell_posts.created, shotwell_posts.updated, 
            shotwell_post_analytics.upvotes, shotwell_post_analytics.downvotes, 
            shotwell_users.id as user_id, shotwell_users.email, shotwell_users.first_name, 
            shotwell_users.last_name, shotwell_users.img_path as user_img_path, 
            shotwell_users.created as user_created, shotwell_users.updated as user_updated'
        );
        $this->db->from('shotwell_posts');
        $this->db->join('shotwell_post_analytics', 'shotwell_post_analytics.post_id = shotwell_posts.id', 'left');
        $this->db->join('shotwell_users', 'shotwell_users.id = shotwell_posts.user_id', 'left');
        $this->db->where('shotwell_posts.id', $postId);
        $postQuery = $this->db->get();
        $postData = $postQuery->row_array();

        if (!$postData) {
            return null;
        }

        $postData['user_details'] = [
            'id' => $postData['user_id'],
            'email' => $postData['email'],
            'first_name' => $postData['first_name'],
            'last_name' => $postData['last_name'],
            'img_path' => $postData['user_img_path'],
            'created' => $postData['user_created'],
            'updated' => $postData['user_updated']
        ];

        // remove duplicate user data
        unset(
            $postData['user_id'],
            $postData['email'],
            $postData['first_name'],
            $postData['last_name'],
            $postData['user_img_path'],
            $postData['user_created'],
            $postData['user_updated']
        );

        // get all comments under the post with the commenter details
        $this->db->select(
            'shotwell_comments.id, shotwell_comments.post_id, shotwell_comments.user_id, 
            shotwell_comments.text, shotwell_comments.created, 
            shotwell_users.email, shotwell_users.first_name, shotwell_users.last_name, 
            shotwell_users.img_path'
        );
        $this->db->from('shotwell_comments');
        $this->db->join('shotwell_users', 'shotwell_users.id = shotwell_comments.user_id', 'left');
        $this->db->where('shotwell_comments.post_id', $postId);
        $commentsQuery = $this->db->get();
        $commentsData = $commentsQuery->result_array();

        // get all upvotes and downvotes for all comments
        $commentIds = array_column($commentsData, 'id');
        if (!empty($commentIds)) {
            $this->db->select('comment_id, vote');
            $this->db->from('shotwell_comment_votes');
            $this->db->where_in('comment_id', $commentIds);
            $votesQuery = $this->db->get();
            $votes = $votesQuery->result_array();

            // counting votes
            $voteCounts = [];
            foreach ($votes as $vote) {
                if (!isset($voteCounts[$vote['comment_id']])) {
                    $voteCounts[$vote['comment_id']] = ['upvotes' => 0, 'downvotes' => 0];
                }
                if ($vote['vote'] == 1) {
                    $voteCounts[$vote['comment_id']]['upvotes'] += 1;
                } else {
                    $voteCounts[$vote['comment_id']]['downvotes'] += 1;
                }
            }

            // using pass by reference (&) to modify the original array
            // Source: https://www.phpreferencebook.com/samples/php-pass-by-reference/
            // also using null coalescing (??) to provide default values if upvotes or downvotes are not found
            // Source: https://www.phptutorial.net/php-tutorial/php-null-coalescing-operator/
            foreach ($commentsData as $key => &$comment) {
                $comment['upvotes'] = $voteCounts[$comment['id']]['upvotes'] ?? 0;
                $comment['downvotes'] = $voteCounts[$comment['id']]['downvotes'] ?? 0;
            }
        }

        $postData['comments'] = $commentsData;
        return $postData;
    }

    /**
     * Inserts a new post into the database.
     */
    public function insertPost($data)
    {
        $this->db->trans_start();
        $this->db->insert('shotwell_posts', $data);
        $insertId = $this->db->insert_id();
        $this->db->trans_complete();

        if ($this->db->trans_status() === TRUE) {
            return $insertId;
        } else {
            return false;
        }
    }

    /** 
     * Initialises the analytics for a new post.
     */
    public function initPostAnalytics($postId)
    {
        $analyticsData = [
            'post_id' => $postId,
            'upvotes' => 0,
            'downvotes' => 0,
            'comments' => 0
        ];

        $this->db->trans_start();
        $this->db->insert('shotwell_post_analytics', $analyticsData);
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Updates an existing post in the database.
     */
    public function updatePost($id, $data)
    {
        $this->db->trans_start();

        $this->db->where('id', $id);
        $this->db->update('shotwell_posts', $data);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Deletes an existing post from the database.
     * Also deletes comments and analytics related to the post,
     * and votes related to the post and comments.
     */
    public function deletePost($postId)
    {
        $this->db->trans_start();

        // get comment IDs
        $this->db->select('id');
        $this->db->from('shotwell_comments');
        $this->db->where('post_id', $postId);
        $commentQuery = $this->db->get();
        $commentIds = $commentQuery->result_array();

        // delete comment votes
        if (!empty($commentIds)) {
            // using anonymous function to map over array
            // turning the associative array into an array of IDs for simplicity later
            // source: https://www.phptutorial.net/php-tutorial/php-array_map/
            $commentIdArray = array_map(function ($comment) {
                return $comment['id'];
            }, $commentIds);
            $this->db->where_in('comment_id', $commentIdArray);
            $this->db->delete('shotwell_comment_votes');
        }

        // delete comments
        $this->db->delete('shotwell_comments', ['post_id' => $postId]);
        // delete post votes
        $this->db->delete('shotwell_post_votes', ['post_id' => $postId]);
        // delete post analytics
        $this->db->delete('shotwell_post_analytics', ['post_id' => $postId]);
        // delete the post itself
        $this->db->delete('shotwell_posts', ['id' => $postId]);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Updates the vote of a user for a post.
     * Checks if the user has already voted on the post,
     * in which case it removes the vote or toggles it,
     * depending on the new vote.
     */
    public function updateVote($userId, $postId, $newVote)
    {
        $this->db->trans_start();

        $voteNumeric = ($newVote === 'up') ? 1 : 0;

        // get current vote
        $this->db->select('vote');
        $this->db->from('shotwell_post_votes');
        $this->db->where('user_id', $userId);
        $this->db->where('post_id', $postId);
        $query = $this->db->get();
        $currentVote = $query->row_array();

        if ($currentVote) { // if user has a vote
            if ($currentVote['vote'] == $voteNumeric) { // same vote as before
                $this->db->where('user_id', $userId);
                $this->db->where('post_id', $postId);
                $this->db->delete('shotwell_post_votes');
                $change = -1; // decrease count because vote is removed
            } else { // opposite vote
                $this->db->set('vote', $voteNumeric);
                $this->db->where('user_id', $userId);
                $this->db->where('post_id', $postId);
                $this->db->update('shotwell_post_votes');
                $change = 2; // 2 because vote is toggled
            }
        } else { // user has no previous vote
            $this->db->insert('shotwell_post_votes', [
                'user_id' => $userId,
                'post_id' => $postId,
                'vote' => $voteNumeric
            ]);
            $change = 1; // increase count
        }

        // update or initialise post analytics
        $this->db->select('upvotes, downvotes');
        $this->db->from('shotwell_post_analytics');
        $this->db->where('post_id', $postId);
        $analytics = $this->db->get()->row_array();

        if ($analytics) {
            // adjust the appropriate vote counter
            if ($newVote === 'up') {
                $this->db->set('upvotes', 'upvotes + ' . ($change == 2 ? 1 : $change), FALSE);
                if ($change == 2) { // if changing vote from down to up, also decrement downvotes
                    $this->db->set('downvotes', 'downvotes - 1', FALSE);
                }
            } else {
                $this->db->set('downvotes', 'downvotes + ' . ($change == 2 ? 1 : $change), FALSE);
                if ($change == 2) { // if changing vote from up to down, also decrement upvotes
                    $this->db->set('upvotes', 'upvotes - 1', FALSE);
                }
            }
            $this->db->where('post_id', $postId);
            $this->db->update('shotwell_post_analytics');
        } else {
            // no vote exists, creating new one
            $analyticsData = [
                'post_id' => $postId,
                'upvotes' => $newVote === 'up' ? 1 : 0,
                'downvotes' => $newVote === 'down' ? 1 : 0
            ];
            $this->db->insert('shotwell_post_analytics', $analyticsData);
        }

        // get latest vote counts
        $this->db->select('upvotes, downvotes');
        $this->db->from('shotwell_post_analytics');
        $this->db->where('post_id', $postId);
        $updatedCounts = $this->db->get()->row_array();

        $this->db->trans_complete();
        if ($this->db->trans_status() === TRUE) {
            return $updatedCounts;
        } else {
            return false;
        }
    }

    /**
     * Search for posts in the database by title and tags.
     * Checks if the search term is a substring of the title or description.
     * Check if the tags are contained by the post.
     * Using like for partial matches.
     * Source: https://codeigniter.com/userguide3/database/query_builder.html#looking-for-similar-data
     */
    public function searchPosts($searchTerm, $searchTags)
    {
        $this->db->select(
            'shotwell_posts.*, shotwell_post_analytics.upvotes, 
            shotwell_post_analytics.downvotes, shotwell_post_analytics.comments_count'
        );
        $this->db->from('shotwell_posts');
        $this->db->join('shotwell_post_analytics', 'shotwell_post_analytics.post_id = shotwell_posts.id', 'left');

        if (!empty($searchTerm)) {
            $this->db->like('shotwell_posts.title', $searchTerm);
            $this->db->or_like('shotwell_posts.desc', $searchTerm);
        }
        if (!empty($searchTags)) {
            foreach ($searchTags as $tag) {
                $this->db->or_like('shotwell_posts.tags', $tag);
            }
        }

        $query = $this->db->get();
        return $query->result_array();
    }

    /**
     * Get all posts created by a specific user.
     */
    public function getPostsByUser($userId)
    {
        $this->db->select(
            'shotwell_posts.*, shotwell_post_analytics.upvotes, 
            shotwell_post_analytics.downvotes, shotwell_post_analytics.comments_count'
        );
        $this->db->from('shotwell_posts');
        $this->db->join('shotwell_post_analytics', 'shotwell_post_analytics.post_id = shotwell_posts.id', 'left');
        $this->db->where('shotwell_posts.user_id', $userId);
        $query = $this->db->get();
        return $query->result_array();
    }
}
