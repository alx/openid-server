<?php

/**
 * Authentication backend implementations.
 */

require_once "backends.php";

class AuthBackend_MYSQL extends Backend_MYSQL {
    function _init()
    {
        // Create tables for OpenID storage backend.
        $tables = array(
                        "CREATE TABLE accounts (" .
                        "id INTEGER AUTO_INCREMENT PRIMARY KEY, " .
                        "username VARCHAR(255) UNIQUE, " .
                        "password VARCHAR(32))",
                        );

        foreach ($tables as $t) {
            $this->db->query($t);
        }
    }

    function newAccount($username, $password, $query)
    {
        $result = $this->db->query("INSERT INTO accounts (username, password) " .
                                   "VALUES (?, ?)",
                                   array($username,
                                         $this->_encodePassword($password)));

        // $query is ignored for this implementation, but you might
        // choose to change the login process to incorporate other
        // user details like an email address.  $query is the HTTP
        // query in which the account registration form was submitted.
        // You'll only need to bother with $query if you've modified
        // the account registration form template and need to access
        // your new fields.

        if (PEAR::isError($result)) {
            print_r($result);
            return false;
        } else {
            return true;
        }
    }

    function removeAccount($username)
    {
        $this->db->query("DELETE FROM accounts WHERE username = ?",
                         array($username));
    }

    function authenticate($username, $password)
    {
        $result = $this->db->getOne("SELECT id FROM accounts WHERE " .
                                    "username = ? AND password = ?",
                                    array($username,
                                          $this->_encodePassword($password)));
        if (PEAR::isError($result) || (!$result)) {
            return false;
        } else {
            return true;
        }
    }

    function setPassword($username, $password)
    {
        $result = $this->db->query("UPDATE accounts SET password = ? WHERE username = ?",
                                   array($this->_encodePassword($password),
                                         $username));
        if (PEAR::isError($result)) {
            return false;
        } else {
            return true;
        }
    }

    function _encodePassword($p)
    {
        return md5($p);
    }

    function search($str = null)
    {
        if ($str != null) {
            $str = "%$str%";

            // Return should be a list of account names; nothing more.
            $result = $this->db->getCol("SELECT username FROM accounts WHERE username ".
                                        "LIKE ? ORDER BY username",
                                        0, array($str));
        } else {
            $result = $this->db->getCol("SELECT username FROM accounts ORDER BY username", 0);
        }

        if (PEAR::isError($result)) {
            return array();
        } else {
            return $result;
        }
    }
}

?>