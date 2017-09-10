<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /* ------------- `users` table method ------------------ */

    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
    public function createUser($trainid, $tcid, $name, $email, $zone) {
        require_once 'PassHash.php';
        $response = array();

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {

            // Generating API key
            $api_key = $this->generateApiKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO tc(trainid, tcid, name, email, zone, api_key) values(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $trainid, $tcid, $name, $email, $zone, $api_key);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $zone) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT zone FROM tc WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->bind_result($zone);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            return TRUE;
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT tcid from tc WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key FROM tc WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($name, $email, $api_key);
            $stmt->fetch();
            $user = array();
            $user["name"] = $name;
            $user["email"] = $email;
            $user["api_key"] = $api_key;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // $api_key = $stmt->get_result()->fetch_assoc();
            // TODO
            $stmt->bind_result($api_key);
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /* ------------- `trains` table method ------------------ */

    /**
     * Creating new train
     * @param String $user_id user id to whom train belongs to
     * @param String $train train text
     */
    public function createTrain($user_id, $train) {
        $stmt = $this->conn->prepare("INSERT INTO trains(train) VALUES(?)");
        $stmt->bind_param("s", $train);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // train row created
            // now assign the train to user
            $new_train_id = $this->conn->insert_id;
            $res = $this->createUserTrain($user_id, $new_train_id);
            if ($res) {
                // train created successfully
                return $new_train_id;
            } else {
                // train failed to create
                return NULL;
            }
        } else {
            // train failed to create
            return NULL;
        }
    }

    /**
     * Fetching single train
     * @param String $train_id id of the train
     */
    public function getTrain($trainid) {
        $stmt = $this->conn->prepare("SELECT t.trainid, t.name, t.last_date, t.last_time, t.no_of_penalty from trains t WHERE t.trainid = ?");
        $stmt->bind_param("i", $trainid);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($trainid, $name, $last_date, $last_time, $no_of_penalty);
            // TODO
            // $train = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["trainid"] = $trainid;
            $res["name"] = $name;
            $res["last_date"] = $last_date;
            $res["last_time"] = $last_time;
            $res["no_of_penalty"] = $no_of_penalty;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching all user trains
     * @param String $user_id id of the user
     */
    public function getAllUserTrains() {
        $stmt = $this->conn->prepare("SELECT * FROM trains");
        $stmt->execute();
        $trains = $stmt->get_result();
        $stmt->close();
        return $trains;
    }

    /**
     * Fetching all user trains
     * @param String $user_id id of the user
     */
     public function getAllUserCoaches($trainid) {
        $stmt = $this->conn->prepare("SELECT * FROM coaches where trainid = ?");
        $stmt->bind_param("i", $trainid);
        $stmt->execute();
        $coaches = $stmt->get_result();
        $stmt->close();
        return $coaches;
    }

    /**
     * Updating train
     * @param String $train_id id of the train
     * @param String $train train text
     * @param String $status train status
     */
    public function updateTrain($name, $last_date, $last_time, $no_of_penalty) {
        $stmt = $this->conn->prepare("UPDATE trains t set t.name = ?, t.last_date = ?, t.last_time = ?, t.no_of_penalty = ? WHERE t.trainid = ?");
        $stmt->bind_param("sssis", $name, $last_date, $last_time, $no_of_penalty, $trainid);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    // /**
    //  * Deleting a train
    //  * @param String $train_id id of the train to delete
    //  */
    // public function deleteTrain($train_id) {
    //     $stmt = $this->conn->prepare("DELETE t FROM trains t, user_trains ut WHERE t.id = ? AND ut.train_id = t.id AND ut.user_id = ?");
    //     $stmt->bind_param("ii", $train_id);
    //     $stmt->execute();
    //     $num_affected_rows = $stmt->affected_rows;
    //     $stmt->close();
    //     return $num_affected_rows > 0;
    // }

    /* ------------- `user_trains` table method ------------------ */

    /**
     * Function to assign a train to user
     * @param String $user_id id of the user
     * @param String $train_id id of the train
     */
    public function createUserTrain($user_id, $train_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_trains(user_id, train_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $train_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }

}

?>
