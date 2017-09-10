<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('trainid', 'name', 'email', 'zone'));
            $randnum = rand(1111111111,9999999999);

            $response = array();

            // reading post params
            $trainid = $app->request->post('trainid');    
            $tcid =  $randnum;     
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $zone = $app->request->post('zone');
            
            // validating email address
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($trainid, $tcid, $name, $email, $zone);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }
            // echo json response
            echoRespnse(201, $response);
        });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'zone'));

            // reading post params
            $email = $app->request()->post('email');
            $zone = $app->request()->post('zone');
            $response = array();

            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $zone)) {
                // get the user by email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }

            echoRespnse(200, $response);
        });

/*
 * ------------------------ METHODS WITH AUTHENTICATION ------------------------
 */

/**
 * Listing all trains of particual user
 * method GET
 * url /trains         
 */
$app->get('/trains', function() {
            $response = array();
            $db = new DbHandler();

            // fetching all user trains
            $result = $db->getAllUserTrains();

            $response["error"] = false;
            $response["trains"] = array();

            // looping through result and preparing trains array
            while ($train = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["trainid"] = $train["trainid"];
                $tmp["name"] = $train["name"];
                $tmp["last_date"] = $train["last_date"];
                $tmp["last_time"] = $train["last_time"];
                $tmp["no_of_penalty"] = $train["no_of_penalty"];
                
                array_push($response["trains"], $tmp);
            }

            echoRespnse(200, $response);
        });

/**
 * Listing single train of particual user
 * method GET
 * url /trains/:id
 * Will return 404 if the train doesn't belongs to user
 */
$app->get('/trains/:id', function($trainid) {
            $response = array();
            $db = new DbHandler();

            // fetch train
            $result = $db->getTrain($trainid);

            if ($result != NULL) {
                $response["error"] = false;
                $response["trainid"] = $result["trainid"];
                $response["name"] = $result["name"];
                $response["last_date"] = $result["last_date"];
                $response["last_time"] = $result["last_time"];
                $response["no_of_penalty"] = $result["no_of_penalty"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Creating new train in db
 * method POST
 * params - name
 * url - /trains/
 */
$app->post('/trains', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('train'));

            $response = array();
            $train = $app->request->post('train');

            global $user_id;
            $db = new DbHandler();

            // adding new train
            $train_id = $db->createTrain($user_id, $train);

            if ($train_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Train added successfully";
                $response["train_id"] = $train_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to add train. Please try again";
                echoRespnse(200, $response);
            }            
        });

/**
 * Updating existing train
 * method PUT
 * params train, status
 * url - /trains/:id
 */
$app->put('/trains/:id', function($trainid) use($app) {
            // check for required params
            verifyRequiredParams(array('name', 'last_date', 'last_time', 'no_of_penalty'));
        
            $name = $app->request->put('name');
            $last_date = $app->request->put('last_date');
            $last_time = $app->request->put('last_time');
            $no_of_penalty = $app->request->put('no_of_penalty');

            $db = new DbHandler();
            $response = array();

            // updating train
            $result = $db->updateTrain($name, $last_date, $last_time, $no_of_penalty);
            if ($result) {
                // train updated successfully
                $response["error"] = false;
                $response["message"] = "Train updated successfully";
            } else {
                // train failed to update
                $response["error"] = true;
                $response["message"] = "Train failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

// /**
//  * Deleting train. Users can delete only their trains
//  * method DELETE
//  * url /trains
//  */
// $app->delete('/trains/:id', function($train_id) use($app) {
//             global $user_id;

//             $db = new DbHandler();
//             $response = array();
//             $result = $db->deleteTrain($user_id, $train_id);
//             if ($result) {
//                 // train deleted successfully
//                 $response["error"] = false;
//                 $response["message"] = "Train deleted succesfully";
//             } else {
//                 // train failed to delete
//                 $response["error"] = true;
//                 $response["message"] = "Train failed to delete. Please try again!";
//             }
//             echoRespnse(200, $response);
//         });

/**
 * Listing all trains of particual user
 * method GET
 * url /trains         
 */
 $app->get('/trains', function() {
    $response = array();
    $db = new DbHandler();

    // fetching all user trains
    $result = $db->getAllUserTrains();

    $response["error"] = false;
    $response["trains"] = array();

    // looping through result and preparing trains array
    while ($train = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["trainid"] = $train["trainid"];
        $tmp["name"] = $train["name"];
        $tmp["last_date"] = $train["last_date"];
        $tmp["last_time"] = $train["last_time"];
        $tmp["no_of_penalty"] = $train["no_of_penalty"];
        
        array_push($response["trains"], $tmp);
    }

    echoRespnse(200, $response);
});

/**
* Listing single train of particual user
* method GET
* url /trains/:id
* Will return 404 if the train doesn't belongs to user
*/
$app->get('/trains/:id', function($trainid) {
    $response = array();
    $db = new DbHandler();

    // fetch train
    $result = $db->getTrain($trainid);

    if ($result != NULL) {
        $response["error"] = false;
        $response["trainid"] = $result["trainid"];
        $response["name"] = $result["name"];
        $response["last_date"] = $result["last_date"];
        $response["last_time"] = $result["last_time"];
        $response["no_of_penalty"] = $result["no_of_penalty"];
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "The requested resource doesn't exists";
        echoRespnse(404, $response);
    }
});

/**
* Creating new coach in db
* method POST
* params - name
* url - /coaches/
*/
$app->post('trains/:id/coaches', function($trainid) use ($app) {
    // check for required params
    verifyRequiredParams(array('coach'));

    $response = array();
    $train = $app->request->post('coach');

    $db = new DbHandler();

    // adding new train
    $train_id = $db->createCoach($coachid, $no_of_penalty);

    if ($trainid != NULL) {
        $response["error"] = false;
        $response["message"] = "Coach added successfully";
        $response["train_id"] = $train_id;
        echoRespnse(201, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to add coach. Please try again";
        echoRespnse(200, $response);
    }            
});

/**
 * Listing all trains of particual user
 * method GET
 * url /trains         
 */
 $app->get('/:id/coaches', function($trainid) {
    $response = array();
    $db = new DbHandler();

    // fetching all user coaches
    $result = $db->getAllUserCoaches($trainid);

    $response["error"] = false;
    $response["coaches"] = array();

    // looping through result and preparing coaches array
    while ($coach = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["trainid"] = $coach["trainid"];
        $tmp["coachid"] = $coach["coachid"];
        $tmp["no_of_penalty"] = $coach["no_of_penalty"];
        
        array_push($response["coaches"], $tmp);
    }

    echoRespnse(200, $response);
});

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
        
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>