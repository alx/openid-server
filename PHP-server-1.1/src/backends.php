<?php

/**
 * Storage backend implementations.  PEAR DB is required to use these
 * storage backends.
 */

require_once "DB.php";

class Backend_MYSQL {
    function connect($parameters)
    {
        $this->database = $parameters['database'];
        $parameters['phptype'] = 'mysql';
        $this->db =& DB::connect($parameters);

        if (!PEAR::isError($this->db)) {
            $this->db->setFetchMode(DB_FETCHMODE_ASSOC);
            $this->db->autoCommit(true);

            if (PEAR::isError($this->db)) {
                /*
                 trigger_error("Could not connect to database '".
                 $parameters['database'].
                 "': " .
                 $this->db->getMessage(),
                 E_USER_ERROR);
                */
                return false;
            }

            $this->_init();
        } else {
            return false;
        }

        return true;
    }
}

?>