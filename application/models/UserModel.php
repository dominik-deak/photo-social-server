<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * User Model to handle database operations for user data.
 * Using transactions for safe insert, update and delete operations
 * Source: https://codeigniter.com/userguide3/database/transactions.html
 */
class UserModel extends CI_Model
{
    function __construct()
    {
        $this->load->database();
    }

    /**
     * Registers a new user.
     * bcrypt method source: https://stackoverflow.com/a/17073604
     */
    public function register($email, $password)
    {
        $this->db->trans_start();

        // hashing password for security and privacy
        $pw_hash = password_hash($password, PASSWORD_BCRYPT);
        $data = [
            'email' => $email,
            'password' => $pw_hash,
        ];
        $this->db->insert('shotwell_users', $data);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Logs the user in using their email and password.
     */
    public function login($email, $password)
    {
        $this->db->where('email', $email);
        $query = $this->db->get('shotwell_users');
        if ($query->num_rows() <= 0) {
            return false;
        }

        $user = $query->row_array();
        // using password_verify to check the password hash against the one in the database
        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // removing password from the session array for privacy
        unset($user['password']);
        return $user;
    }

    /**
     * Deletes a user from the database.
     * Also deletes all posts, comments and votes made by the user.
     * Also deletes the image associated with the user from the disk.
     */
    public function deleteUser($userId)
    {
        $this->db->trans_start();

        // get user image
        $basePath = '../images/';
        $this->db->select('img_path');
        $this->db->from('shotwell_users');
        $this->db->where('id', $userId);
        $user = $this->db->get()->row();
        // delete it from the disk
        if ($user && $user->img_path) {
            $imageName = basename($user->img_path);
            if (file_exists($basePath . $imageName)) {
                unlink($basePath . $imageName);
            }
        }

        // get images of all posts made by the user
        $this->db->select('img_path');
        $this->db->from('shotwell_posts');
        $this->db->where('user_id', $userId);
        $posts = $this->db->get()->result();
        // delete them from the disk
        foreach ($posts as $post) {
            if ($post->img_path) {
                $imageName = basename($post->img_path);
                if (file_exists($basePath . $imageName)) {
                    unlink($basePath . $imageName);
                }
            }
        }

        // delete votes for comments
        $this->db->where('user_id', $userId);
        $this->db->delete('shotwell_comment_votes');
        // delete comments
        $this->db->where('user_id', $userId);
        $this->db->delete('shotwell_comments');

        // get post IDs
        $this->db->select('id');
        $this->db->from('shotwell_posts');
        $this->db->where('user_id', $userId);
        $postIds = $this->db->get()->result_array();
        if (!empty($postIds)) {
            $postIdsArray = array_map(function ($post) {
                return $post['id'];
            }, $postIds);

            // delete analytics and votes for posts
            $this->db->where_in('post_id', $postIdsArray);
            $this->db->delete('shotwell_post_analytics');
            $this->db->where('user_id', $userId);
            $this->db->delete('shotwell_post_votes');
            // delete posts
            $this->db->where_in('id', $postIdsArray);
            $this->db->delete('shotwell_posts');
        }

        // delete the user
        $this->db->where('id', $userId);
        $this->db->delete('shotwell_users');

        $this->db->trans_complete();
        return $this->db->trans_status() === TRUE;
    }

    /**
     * Checks if the password param matches the hash in the database.
     */
    public function validateUserPassword($userId, $inputPassword)
    {
        $this->db->where('id', $userId);
        $user = $this->db->get('shotwell_users')->row();

        // using password_verify to check the password hash against the one in the database
        if ($user && password_verify($inputPassword, $user->password)) {
            return true;
        }
        return false;
    }

    /**
     * Returns a user from the database.
     */
    public function getUser($userId)
    {
        $this->db->where('id', $userId);
        $query = $this->db->get('shotwell_users');
        $user = $query->row_array();

        if (!$user) {
            return null;
        }

        unset($user['password']);
        return $user;
    }

    /**
     * Updates a user in the database.
     * Checks if any fields have changed. If so, updates the user, otherwise does nothing.
     */
    public function updateUser($userId, $data)
    {
        $this->db->trans_start();

        $currentUser = $this->getUser($userId);
        if (!$currentUser) return false;

        $updateNeeded = false;
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $currentUser) || $currentUser[$key] !== $value) {
                $updateNeeded = true;
                break;
            }
        }

        if ($updateNeeded) {
            $this->db->where('id', $userId);
            $this->db->update('shotwell_users', $data);
        }

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return false;
        }
        return $updateNeeded;
    }

    /**
     * Returns the path of a user image.
     */
    public function getImagePath($userId)
    {
        $this->db->select('img_path');
        $this->db->where('id', $userId);
        $query = $this->db->get('shotwell_users');

        if ($query->num_rows() == 0) {
            return null;
        }

        $result = $query->row_array();
        return $result['img_path'];
    }
}
