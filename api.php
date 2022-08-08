<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
require ('inc/init.php');
include_once ('emailservice/index.php');
include_once ('adminapi/index.php');
include_once ('group/index.php');
include_once ('notification/index.php');
include_once ('smsservice/index.php');


$action = $_REQUEST['action'];

$target_dir = "uploads";
// $domain_name = "http://ec2-13-233-61-175.ap-south-1.compute.amazonaws.com/" ;
$domain_name = "http://conveyenceoffice.livestockloader.com/uploads/";

if ($action == 'login')
{

    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $email = $data->email;
    $player_id = $data->player_id;
    $password = md5($data->password);
    $sql = "SELECT * FROM users WHERE u_email='$email' AND u_password='$password'";
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);

    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

    if ($nor > 0)
    {
        $query = sprintf("UPDATE users SET u_player_id='%s' where u_id='%d'", $player_id, $row['u_id']);
        if (!$result = mysqli_query($con, $query))
        {
            echo "Error : - " . $query . ":-" . mysqli_error($con);
            exit;
        }
        $row['u_player_id'] = $player_id;
        $arr = array(
            'status' => 200,
            'data' => $row
        );
        echo json_encode($arr);
        exit;

    }
    else
    {
        $arr = array(
            'status' => '400',
            'message' => ''
        );
        echo json_encode($arr);
    }
    exit;
}

if ($action == 'signup')
{
    // Type your website name or domain name here.
    global $con;

    $email = $_REQUEST['email'];
    $player_id = $_REQUEST['player_id'];
    $invite_token = $_REQUEST['invite_token'];

    //check user exist
    $check = "SELECT * FROM users WHERE u_email='$email'";
    $result = mysqli_query($con, $check) or die(mysqli_error());
    $nor = mysqli_num_rows($result);
    if ($nor > 0)
    {
        $arr = array(
            'status' => '500',
            'message' => 'This email already exist.'
        );
        echo json_encode($arr);
    }
    else
    {
        $fullname = $_REQUEST['name'];
        $password = md5($_REQUEST['password']);
        $randomString = getRandomString(25);
        $filename = "default.png";
        if ($_FILES)
        {
            $ext = explode('.', $_FILES['photo']['name']) [count(explode('.', $_FILES['photo']['name'])) - 1];
            $filename = rand() . "_" . time() . "." . $ext;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . "/" . $filename))
            {
                $arr = array(
                    'status' => '500',
                    'message' => "Unable to upload image"
                );
                exit;
            }
        }
        $today = date("Y-m-d H:m:s");
        $purchase_time = $today;
        $plan_expiry_time = date("Y-m-d H:m:s", strtotime($today . '+ 14 days'));
        $u_otp_expiry = date("Y-m-d H:m:s", strtotime($today . '+ 3 days'));
        $u_otp = rand(1000, 9999);

        $sql = sprintf("INSERT INTO users SET email_verified='0', phone_verified='0', u_fullname='%s', u_email='%s', u_password='%s', u_image='%s', u_token='%s',u_role='user',u_status='1', u_player_id='%s', purchase_time='%s', plan_expiry_time='%s', u_otp = '%s', u_otp_expiry='%s' ", $fullname, $email, $password, $filename, $randomString, $player_id, $purchase_time, $plan_expiry_time, $u_otp, $u_otp_expiry);
        // Printing response message on screen after successfully inserting the image .
        if (mysqli_query($con, $sql))
        {
            $id = mysqli_insert_id($con);
            $sql2 = "SELECT * FROM users WHERE u_id='$id'";
            $result2 = mysqli_query($con, $sql2) or die(mysqli_error());

            $row = mysqli_fetch_array($result2, MYSQLI_ASSOC);

            $arr = array(
                'status' => '200',
                'token' => $randomString,
                'data' => $row
            );
            sendVerificationMail($email, $randomString, $u_otp);
            // group_accept_invite($con,$invite_token,$row);
            // file_get_contents('https://conveyenceoffice.livestockloader.com/emailservice/index.php?email='.$row['u_email'].'&token='.$randomString.'&type=sendverifyemail');
            echo json_encode($arr);

        }
        else
        {
            $arr = array(
                'status' => '400',
                'error' => mysqli_error($con)
            );
            echo json_encode($arr);
        }
    }
    exit;
}

if ($action == 'verify_otp')
{
    // Type your website name or domain name here.
    global $con;

    $email = $_REQUEST['email'];
    $u_otp = $_REQUEST['otp'];
    //check user exist
    $check = "SELECT * FROM users WHERE u_email='$email'";
    $result = mysqli_query($con, $check) or die(mysqli_error());
    $nor = mysqli_num_rows($result);
    if ($nor > 0)
    {
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        if ($u_otp == $row['u_otp'])
        {
            $u_otp_expiry = $row['u_otp_expiry'];
            $today = date("Y-m-d H:m:s");
            if (strtotime($today) > strtotime($u_otp_expiry))
            {
                $arr = array(
                    'status' => '500',
                    'message' => 'OTP has expired'
                );
                echo json_encode($arr);
                die();
            }
            else
            {
                $query = sprintf("UPDATE users SET email_verified='1' where u_email ='%s' ", $email);
                if (!$result = mysqli_query($con, $query))
                {
                    echo "Error : - " . $query . ":-" . mysqli_error($con);
                    exit;
                }
                $arr = array(
                    'status' => '200',
                    'message' => 'OTP has verified'
                );
                echo json_encode($arr);
                die();
            }
        }
        else
        {
            $arr = array(
                'status' => '500',
                'message' => 'OTP is not valid'
            );
            echo json_encode($arr);
            die();
        }
        echo json_encode($arr);
    }
    else
    {
        $arr = array(
            'status' => '400',
            'message' => 'OTP is not valid'
        );
        echo json_encode($arr);
    }
    exit;
}

if ($action == 'getprofile')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $sql = "SELECT * FROM users WHERE u_token='$token'";
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);

    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $row['u_image'] = $domain_name . $row['u_image'];
    if ($nor > 0)
    {
        $arr = array(
            'status' => '200',
            'data' => $row
        );
        echo json_encode($arr);

    }
    else
    {
        $arr = array(
            'status' => '400',
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'getUsers')
{
    global $con;
    $pagesize = 10;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $page = $data->page;
    $previous = $data->page;
    $name = $data->name;
    $paginationcondition = paginationCondition($page, $pagesize);
    $condition = $name != '' ? " WHERE u_fullname LIKE '%$name%' " : '';

    $feched_data = getMultipleRowData($con, '*', 'users', $condition . $paginationcondition);
    foreach ($feched_data as $key => $dt)
    {
        $feched_data[$key]['u_image'] = $domain_name . $dt['u_image'];
    }
    echo json_encode($feched_data);
    exit;

}
if ($action == 'getUsersonSearch')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $word = $data->word;

    $sql = "SELECT * FROM users WHERE u_fullname LIKE '%" . $word . "%'";

    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);
    if ($nor > 0)
    {
        $array = array();

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
        {
            $row['u_image'] = $domain_name . $row['u_image'];
            $array[] = $row;
        }
        $resPonce = array(
            'status' => '200',
            'data' => $array
        );
        echo json_encode($resPonce);
    }
    else
    {
        $resPonce = array(
            'status' => '400'

        );
        echo json_encode($resPonce);
    }
    exit;
}

if ($action == 'sendPushNotification')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $to_id = $data->to_id;
    $message = $data->message;
    $query = sprintf("SELECT u_player_id FROM users WHERE u_id='%d' ", $to_id);
    if (!$result = mysqli_query($con, $query))
    {
        echo "Error : - " . $query . ":-" . mysqli_error($con);
        exit;
    }

    //    $data = sendNotification(mysqli_fetch_assoc($result)['u_player_id'],$message,'');
    $data = sendNotification('ExponentPushToken[VX-MB7OVwkGppiKw6QWuaG]', $message, ['s', 'a', 'n', 't', 'o', 's', 'h']);
    // $token = $_GET['token'];
    // $data = sendNotification($token);
    //    VX-MB7OVwkGppiKw6QWuaG
    echo json_encode($data);
    exit;
}
if ($action == 'getmynotifications')
{
    global $con;
    echo json_encode(getMyNotification($con, json_decode(file_get_contents('php://input')) , $domain_name));
    exit;
}

if ($action == 'clearmynotifications')
{
    global $con;
    echo json_encode(clearMyNotification($con, json_decode(file_get_contents('php://input'))));
    exit;
}
if ($action == 'getloadboards')
{

    error_reporting(0);
    global $con;

    //$sql = "ALTER TABLE live_stock_type ADD weight varchar(255) NULL AFTER qty";
    //mysqli_query($con, $sql);
    //$sql = "ALTER TABLE live_stock_type ADD loadName varchar(255) NULL AFTER weight";
    //mysqli_query($con, $sql);
    //$sql = "ALTER TABLE loads DROP COLUMN deleted_by";
    //mysqli_query($con, $sql);
    //$sql = "ALTER TABLE loads ADD deleted_by enum('0','1','2') DEFAULT '0' AFTER other_can_call";
    //mysqli_query($con, $sql);
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $page = $data->page;
    $sortBy = trim($data->sortBy) == 'created_at' ? 'id DESC' : $data->sortBy;
    $livestocktype = $data->livestocktype;
    $limit = 10;
    if ($data->page)
    {
        $minval = ($data->page - 1) * $limit;
        $limitcond = "LIMIT " . $minval . "," . $limit;
    }
    $condition = '';
    if ($livestocktype != '')
    {
        $condition = " AND ( name='" . implode("' OR name='", explode(',', $livestocktype)) . "' )";
    }

    //loads.*,users.u_company,users.u_countrycode,users.u_mobileno,users.u_image,users.u_fullname,users.u_id,users.u_token,users.email_verified, users.phone_verified
    //users.u_token!='".$token."' AND
    $sql = "SELECT *, CONVERT(SUBSTRING(rate,2),UNSIGNED INTEGER) as price FROM loads INNER JOIN users ON users.u_token=loads.user_id WHERE loads.journey_status!='Completed' AND loads.deleted_by = '0' ORDER BY $sortBy $limitcond";
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error($con));
    $no = mysqli_num_rows($result);
    $array = [];
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
    {
        $live_stock_type = get_live_stock_data($con, $row['id'], $condition);
        if (count($live_stock_type) > 0)
        {
            $row['live_stock_type'] = $live_stock_type; //unserialize($row['live_stock_type']);
            $row['pickup_latlng'] = unserialize($row['pickup_latlng']);
            $row['drop_latlng'] = unserialize($row['drop_latlng']);
            $row['mapdata'] = unserialize($row['mapdata']);
            $row['days'] = getdatedifference($row['pickup_date'], $row['drop_date']);
            $row['u_image'] = $domain_name . $row['u_image'];
            $array[] = $row;
        }
    }
    if (empty($array) || $no < 7)
    {
        $resPonce = array(
            'status' => '200',
            'data' => $array,
            'thisPage' => null
        );
        echo json_encode($resPonce);
    }
    else
    {
        $resPonce = array(
            'status' => '200',
            'data' => $array,
            'thisPage' => $previous + 1
        );
        echo json_encode($resPonce);
    }
    exit;
}
if ($action == 'getloadbyid')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $id = $data->id;

    $sql = "SELECT * FROM loads WHERE id='$id'";
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);

    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

    if ($nor > 0)
    {
        $arr = array(
            'status' => '200',
            'data' => $row
        );
        echo json_encode($arr);

    }
    else
    {
        $arr = array(
            'status' => '400',
        );
        echo json_encode($arr);
    }
    exit;
}

if ($action == 'updateprofile')
{
    global $con;
    $token = $_REQUEST['token'];
    $about = $_REQUEST['about'];
    $company = $_REQUEST['company'];
    $phone = $_REQUEST['phone'];

    $city = $_REQUEST['city'];
    $state = $_REQUEST['state'];
    $zip = $_REQUEST['zip'];
    $usDot = $_REQUEST['usDot'];

    $query = sprintf("UPDATE users SET u_about='%s',u_company='%s',u_image='%s',u_mobileno='%s',
		u_city='%s',u_state='%s',u_zip='%s',u_usDot='%s' WHERE u_token='%s'", $about, $company, $filename, $phone, $city, $state, $zip, $usDot, $token);
    if (!$result = mysqli_query($con, $query))
    {
        echo "Error : - " . $query . ":-" . mysqli_error($con);
        exit;
    }

    if ($_FILES)
    {
        $ext = explode('.', $_FILES['photo']['name']) [count(explode('.', $_FILES['photo']['name'])) - 1];
        $filename = "/" . rand() . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $filename);
        $query = sprintf("UPDATE users SET u_image='%s' WHERE u_token='%s'", $filename, $token);
        if (!$result = mysqli_query($con, $query))
        {
            echo "Error : - " . $query . ":-" . mysqli_error($con);
            exit;
        }
    }
    echo json_encode(array(
        'status' => '200'
    ));
    exit;
}

if ($action == 'gettrailer')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;

    $sql = "SELECT * FROM users WHERE u_token='$token'";
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);
    if ($nor > 0)
    {
        echo getAllTrailers($con, $domain_name);
    }
    else
    {
        $resPonce = array(
            'status' => '400',
            'data' => ''
        );
        echo json_encode($resPonce);
    }
    exit;
}
if ($action == 'addtrailer')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    //$sql = "DELETE FROM customer_trailer WHERE u_token = '$data->token'";
    //mysqli_query($con, $sql);


    $t_id = $data->trailerType;
    $u_token = $data->token;
    $t_lstype = $data->livestockType;
    // $livestockType = $data->loadDetails->livestockType;
    json_decode($t_lstype);
    $t_lstype = serialize($t_lstype);

    $t_name = $data->name;
    $t_vin = $data->vin;
    $t_max_load = $data->maxLoad;
    $t_cmeasurement = $data->trailerMeasurement;
    $location = $data->location ? $data->location : '';
    json_decode($t_cmeasurement);
    $string = serialize($t_cmeasurement);
    $t_total = $data->totalWeight;
    json_decode($t_total);
    $t_total_str = serialize($t_total);
    $sql = "INSERT INTO customer_trailer (t_id,u_token,t_lstype,t_name,t_vin,t_cmeasurement,t_total, t_location, t_max_load)
        VALUES ('$t_id','$u_token','$t_lstype','$t_name','$t_vin','$string','$t_total_str','$location', '$t_max_load')";

    if (mysqli_query($con, $sql))
    {
        $arr = array(
            'status' => '200',
            'id' => mysqli_insert_id($con)
        );
        echo json_encode($arr);
    }
    else
    {
        $arr = array(
            'status' => '400'
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'addtrailerImage')
{
    global $con;
    $id = $_REQUEST['t_id'];
    if ($_FILES)
    {
        $ext = explode('.', $_FILES['photo']['name']) [count(explode('.', $_FILES['photo']['name'])) - 1];
        $filename = rand() . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . "/" . $filename))
        {
            $query = sprintf("UPDATE customer_trailer SET t_image='%s' WHERE id='%s'", $filename, $id);
            if (!$result = mysqli_query($con, $query))
            {
                echo "Error : - " . $query . ":-" . mysqli_error($con);
                exit;
            }
        }
    }
    echo json_encode(array(
        'status' => '200',
        'file' => $_FILES['photo']['name']
    ));
    exit;
}
if ($action == 'updatetrailer')
{
    global $con;
    //$sql = "ALTER TABLE customer_trailer ADD t_max_load VARCHAR(255) NULL AFTER t_location";
    //mysqli_query($con, $sql);
    $data = json_decode(file_get_contents('php://input'));
    $id = $data->id;
    $t_id = $data->trailerType;
    $u_token = $data->token;
    $t_lstype = $data->livestockType;
    json_decode($t_lstype);
    $t_lstype = serialize($t_lstype);

    $t_name = $data->name;
    $t_vin = $data->vin;
    $t_max_load = $data->maxLoad;
    $t_cmeasurement = $data->trailerMeasurement;
    json_decode($t_cmeasurement);
    $string = serialize($t_cmeasurement);
    $t_total = $data->totalWeight;
    json_decode($t_total);
    $t_total_str = serialize($t_total);
    $location = $data->location ? $data->location : '';

    $sql = "UPDATE customer_trailer SET t_id='$t_id',u_token='$u_token',t_lstype='$t_lstype',t_name='$t_name',t_vin='$t_vin',t_cmeasurement='$string',t_total='$t_total_str', t_location='$location', t_max_load='$t_max_load' WHERE id='$id'";
    if (mysqli_query($con, $sql))
    {
        $arr = array(
            'status' => '200',
            'id' => $id
        );
        echo json_encode($arr);
    }
    else
    {
        $arr = array(
            'status' => '400'
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'deletetrailer')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $id = $data->id;
    $sql = "DELETE FROM customer_trailer  WHERE id='$id'";
    $result = mysqli_query($con, $sql) or die(mysqli_error());

    if (!empty($result))
    {
        $arr = array(
            'status' => '200'
        );
        echo json_encode($arr);
    }
    else
    {
        $arr = array(
            'status' => '400'
        );
        echo json_encode($arr);
    }

}
if ($action == 'deleteload')
{
    //(user_id='%s' OR driver='%s')
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $id = $data->id;
    $token = $data->token;
    $sql = "SELECT * FROM loads WHERE id='$id'";
    $loadres = mysqli_query($con, $sql) or die(mysqli_error());
    $load = mysqli_fetch_array($loadres, MYSQLI_ASSOC);
    $deleted_by = '0';
    if ($token == $load['user_id'])
    {
        $deleted_by = '1';
    }
    elseif ($token == $load['driver'])
    {
        $deleted_by = '2';
    }

    $sql = "UPDATE loads set deleted_by='$deleted_by' WHERE id=" . $id;
    $res = mysqli_query($con, $sql) or die(mysqli_error());

    //$query = sprintf("DELETE FROM live_stock_type WHERE load_id='%d'",$id);
    //if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
    if (!empty($res))
    {
        echo json_encode(array(
            'status' => '200'
        ));
    }
    else
    {
        echo json_encode(array(
            'status' => '400'
        ));
    }
    exit;
}
if ($action == 'getCustomTrailer')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $limit = $data->limit;
    if ($limit == 'all')
    {
        $sql = "SELECT * FROM customer_trailer  WHERE u_token='$token'";
        // Execute the query
        $result = mysqli_query($con, $sql) or die(mysqli_error());
        $nor = mysqli_num_rows($result);
        if ($nor > 0)
        {
            $array = array();
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
            {
                $t_id = $row['t_id'];
                $sql2 = "SELECT * FROM trailers WHERE t_id ='$t_id'";
                $result2 = mysqli_query($con, $sql2) or die(mysqli_error());
                $row2 = mysqli_fetch_array($result2, MYSQLI_ASSOC);
                $row['trailer_image'] = $row['t_image'] ? $domain_name . $row['t_image'] : "";
                $row['t_image'] = $domain_name . $row2['t_image'];
                $row['t_trailertype'] = $row2['t_name'];
                $mes = $row['t_cmeasurement'];
                $mes = unserialize($mes);
                $row['t_cmeasurement'] = $mes;
                $tot = $row['t_total'];
                $tot = unserialize($tot);
                $row['t_total'] = $tot;
                $row['t_lstype'] = unserialize($row['t_lstype']);
                $array[] = $row;
            }
            $resPonce = array(
                'status' => '200',
                'data' => $array
            );
            echo json_encode($resPonce);

            //   $arr = mysqli_fetch_array($result,MYSQLI_ASSOC);

        }
        else
        {
            $resPonce = array(
                'status' => '400',
                'data' => ''
            );
            echo json_encode($resPonce);
        }
    }
    else
    {

        $sql = "SELECT * FROM customer_trailer WHERE id ='$limit'";
        // Execute the query
        $result = mysqli_query($con, $sql) or die(mysqli_error());
        $nor = mysqli_num_rows($result);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        //var_dump($row);
        $mes = $row['t_cmeasurement'];
        $mes = unserialize($mes);
        $row['t_cmeasurement'] = $mes;
        $tot = $row['t_total'];
        $tot = unserialize($tot);
        $row['t_total'] = $tot;
        $t_id = $row['t_id'];
        $sql2 = "SELECT * FROM trailers WHERE t_id ='$t_id'";
        $result2 = mysqli_query($con, $sql2) or die(mysqli_error());
        $row2 = mysqli_fetch_array($result2, MYSQLI_ASSOC);
        $row['trailer_image'] = $row['t_image'] ? $domain_name . $row['t_image'] : "";
        $row['t_image'] = $domain_name . $row2['t_image'];
        $row['t_trailertype'] = $row2['t_name'];
        if ($nor > 0)
        {
            $resPonce = array(
                'status' => '200',
                'data' => $row
            );
            echo json_encode($resPonce);

        }
        else
        {
            $resPonce = array(
                'status' => '400',
                'data' => ''
            );
            echo json_encode($resPonce);
        }
    }
    exit;
}
if ($action == 'addload')
{
    error_reporting(0);
    global $con;

    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $extra_note = $data
        ->loadDetails->extra_note;
    $other_can_call = $data
        ->loadDetails->other_can_call;
    $pickup_date = $data
        ->pickUp->date;
    $pickup_time = $data
        ->pickUp->time;
    $pickup_address = $data
        ->pickUp->address;
    $drop_date = $data
        ->dropOff->date;
    $drop_time = $data
        ->dropOff->time;
    $drop_address = $data
        ->dropOff->address;
    $total_weight = $data
        ->loadDetails->totalWeight;
    $num_of_loads = $data
        ->loadDetails->numOfLoads;
    $rate = $data
        ->loadDetails->rate;
    $rate_type = $data
        ->loadDetails->rate_type;
    $private = $data
        ->loadDetails->private;
    $pickup_latlng = $data
        ->pickUp->latlng;
    $driver = $data->driver;
    json_decode($pickup_latlng);
    $pickup_latlng = serialize($pickup_latlng);
    $pickup_landmark = $data
        ->pickUp->landmark;

    $drop_latlng = $data
        ->dropOff->latlng;
    json_decode($drop_latlng);
    $drop_latlng = serialize($drop_latlng);
    $drop_landmark = $data
        ->dropOff->landmark;
    $distance = distance($data
        ->dropOff
        ->latlng->lat, $data
        ->dropOff
        ->latlng->lng, $data
        ->pickUp
        ->latlng->lat, $data
        ->pickUp
        ->latlng
        ->lng);
    $mapdata = serialize(json_decode($data->mapdata));
    $sql = sprintf("INSERT INTO loads set 
			pickup_date='%s', 
			pickup_time='%s',
			pickup_address='%s',
			drop_date='%s',
			drop_time='%s',
			drop_address='%s',
			total_weight='%s',
			no_of_loads='%s',
			rate='%s',
			rate_type='%s',
			private='%s',
			user_id='%s', pickup_latlng='%s', pickup_landmark='%s', drop_latlng='%s', drop_landmark='%s', journey_status='Available', distance='%d', extra_note='%s', other_can_call='%s', mapdata='%s' ", $pickup_date, $pickup_time, $pickup_address, $drop_date, $drop_time, $drop_address, $total_weight, $num_of_loads, $rate, $rate_type, $private, $token, $pickup_latlng, $pickup_landmark, $drop_latlng, $drop_landmark, $distance, $extra_note, $other_can_call, $mapdata);
    if (!$result = mysqli_query($con, $sql))
    {
        echo "Error : - " . $sql . ":-" . mysqli_error($con);
        exit;
    }

    $id = mysqli_insert_id($con);
    $livestock = $data
        ->loadDetails->livestockType;
    for ($i = 0;$i < count($livestock);$i++)
    {
        for ($j = 0;$j < count($livestock[$i]);$j++)
        {
            $loadName = $livestock[$i][$j]->loadName;
            $query = sprintf("INSERT INTO live_stock_type SET name='%s', qty='%d', weight='%s', loadName='%s', load_id='%s' ", $livestock[$i][$j]->name, $livestock[$i][$j]->qty, $livestock[$i][$j]->weight, $loadName, $id);
            if (!$result = mysqli_query($con, $query))
            {
                echo "Error : - " . $query . ":-" . mysqli_error($con);
                exit;
            }
        }
    }

    if ($driver)
    {
        $query = sprintf("INSERT INTO load_invitation SET load_id='%s', driver_id='%d', status='%s', status_updated_at='%s' ", $id, $driver, 'pending', date('Y-m-d H:i:s'));
        if (!$result = mysqli_query($con, $query))
        {
            $error = "Error : - " . $query . ":-" . mysqli_error($con);
        }
        $last_id = mysqli_insert_id($con);
        $user = "SELECT * FROM users WHERE u_token='$token'";
        $result = mysqli_query($con, $user) or die(mysqli_error());
        $row2 = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $array = array(
            'msg' => "You have been invited as a driver " . $row2['u_fullname'] . ", view details",
            'sender_name' => $row2['u_fullname'],
            'sender_id' => $row2['u_id'],
            'receiver_id' => $driver,
            'message_type' => 'addedtoload',
            'group_id' => $last_id
        );
        createNotification($con, $array);
    }
    echo json_encode(array(
        'status' => '200'
    ));
    exit;

}
if ($action == 'updateload')
{
    error_reporting(0);
    global $con;

    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $extra_note = $data
        ->loadDetails->extra_note;
    $other_can_call = $data->other_can_call;
    $id = $data->id;
    $pickup_date = $data
        ->pickUp->date;
    $pickup_time = $data
        ->pickUp->time;
    $pickup_address = $data
        ->pickUp->address;
    $drop_date = $data
        ->dropOff->date;
    $drop_time = $data
        ->dropOff->time;
    $drop_address = $data
        ->dropOff->address;
    $total_weight = $data
        ->loadDetails->totalWeight;
    $num_of_loads = $data
        ->loadDetails->numOfLoads;
    $rate = $data
        ->loadDetails->rate;
    $rate_type = $data
        ->loadDetails->rate_type;
    $private = $data
        ->loadDetails->private;
    $pickup_latlng = $data
        ->pickUp->latlng;
    json_decode($pickup_latlng);
    $pickup_latlng = serialize($pickup_latlng);
    $pickup_landmark = $data
        ->pickUp->landmark;

    $drop_latlng = $data
        ->dropOff->latlng;
    json_decode($drop_latlng);
    $drop_latlng = serialize($drop_latlng);
    $drop_landmark = $data
        ->dropOff->landmark;
    $distance = distance($data
        ->dropOff
        ->latlng->lat, $data
        ->dropOff
        ->latlng->lng, $data
        ->pickUp
        ->latlng->lat, $data
        ->pickUp
        ->latlng
        ->lng);
    $mapdata = serialize(json_decode($data->mapdata));
    $sql = sprintf("UPDATE loads set 
			pickup_date='%s', 
			pickup_time='%s',
			pickup_address='%s',
			drop_date='%s',
			drop_time='%s',
			drop_address='%s',
			total_weight='%s',
			no_of_loads='%s',
			rate='%s',
			rate_type='%s',
			private='%s',
			user_id='%s', pickup_latlng='%s', pickup_landmark='%s', drop_latlng='%s', drop_landmark='%s', journey_status='Available', distance='%d', extra_note='%s', other_can_call='%s', mapdata='%s' WHERE id='%d' ", $pickup_date, $pickup_time, $pickup_address, $drop_date, $drop_time, $drop_address, $total_weight, $num_of_loads, $rate, $rate_type, $private, $token, $pickup_latlng, $pickup_landmark, $drop_latlng, $drop_landmark, $distance, $extra_note, $other_can_call, $mapdata, $id);

    $query = sprintf("DELETE FROM live_stock_type WHERE load_id='%d' ", $id);
    if (!$result = mysqli_query($con, $query))
    {
        echo "Error : - " . $query . ":-" . mysqli_error($con);
        exit;
    }

    $livestock = $data
        ->loadDetails->livestockType;
    for ($i = 0;$i < count($livestock);$i++)
    {
        for ($j = 0;$j < count($livestock[$i]);$j++)
        {
            $loadName = $livestock[$i][$j]->loadName;
            $query = sprintf("INSERT INTO live_stock_type SET name='%s', qty='%d', weight='%s', loadName='%s', load_id='%s' ", $livestock[$i][$j]->name, $livestock[$i][$j]->qty, $livestock[$i][$j]->weight, $loadName, $id);
            if (!$result = mysqli_query($con, $query))
            {
                echo "Error : - " . $query . ":-" . mysqli_error($con);
                exit;
            }
        }
    }

    if (mysqli_query($con, $sql))
    {
        if ($driver)
        {
            $sql = "SELECT * FROM loads WHERE id='$id'";
            $loadres = mysqli_query($con, $sql) or die(mysqli_error());
            $load = mysqli_fetch_array($loadres, MYSQLI_ASSOC);
            if ((!$load['driver']) or ($load['driver'] and $load['driver'] != $driver))
            {
                $query = sprintf("INSERT INTO load_invitation SET load_id='%s', driver_id='%d', status='%s', status_updated_at='%s' ", $id, $driver, 'pending', date('Y-m-d H:i:s'));
                if (!$result = mysqli_query($con, $query))
                {
                    $error = "Error : - " . $query . ":-" . mysqli_error($con);
                }
                $last_id = mysqli_insert_id($con);
                $user = "SELECT * FROM users WHERE u_token='$token'";
                $result = mysqli_query($con, $user) or die(mysqli_error());
                $row2 = mysqli_fetch_array($result, MYSQLI_ASSOC);
                $array = array(
                    'msg' => "You have been invited as a driver " . $row2['u_fullname'] . ", view details",
                    'sender_name' => $row2['u_fullname'],
                    'sender_id' => $row2['u_id'],
                    'receiver_id' => $driver,
                    'message_type' => 'addedtoload',
                    'group_id' => $last_id
                );
                createNotification($con, $array);
            }
        }

        $arr = array(
            'status' => '200',
            'driver' => $driver,
            'load' => $id
        );
        echo json_encode($arr);

    }
    else
    {
        $arr = array(
            'status' => '400',
            'error' => mysqli_error($con)
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'updateNote')
{
    error_reporting(0);
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $extra_note = $data->extra_note;
    $id = $data->load_id;
    $sql = sprintf("UPDATE loads set extra_note='%s' WHERE id='%d' ", $extra_note, $id);
    if (mysqli_query($con, $sql))
    {
        $arr = array(
            'status' => '200',
            'load' => $id
        );
        echo json_encode($arr);

    }
    else
    {
        $arr = array(
            'status' => '400',
            'error' => mysqli_error($con)
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'updateDriver')
{
    $data = json_decode(file_get_contents('php://input'));
    $driver = $data->driver;
    $id = $data->id;
    if ($driver)
    {
        $sql = "SELECT * FROM loads WHERE id='$id'";
        $loadres = mysqli_query($con, $sql) or die(mysqli_error());
        $load = mysqli_fetch_array($loadres, MYSQLI_ASSOC);
        if ((!$load['driver']) or ($load['driver'] and $load['driver'] != $driver))
        {
            $query = sprintf("DELETE FROM load_invitation WHERE load_id='%s' ", $id);
            $result = mysqli_query($con, $query) or die(mysqli_error());
            $query = sprintf("INSERT INTO load_invitation SET load_id='%s', driver_id='%d', status='%s', status_updated_at='%s' ", $id, $driver, 'pending', date('Y-m-d H:i:s'));
            if (!$result = mysqli_query($con, $query))
            {
                $error = "Error : - " . $query . ":-" . mysqli_error($con);
            }
            $last_id = mysqli_insert_id($con);
            $user = "SELECT * FROM users WHERE u_token='$token'";
            $result = mysqli_query($con, $user) or die(mysqli_error());
            $row2 = mysqli_fetch_array($result, MYSQLI_ASSOC);
            $array = array(
                'msg' => "You have been invited as a driver " . $row2['u_fullname'] . ", view details",
                'sender_name' => $row2['u_fullname'],
                'sender_id' => $row2['u_id'],
                'receiver_id' => $driver,
                'message_type' => 'addedtoload',
                'group_id' => $last_id
            );
            createNotification($con, $array);
        }
    }
    $arr = array(
        'status' => '200',
        'driver' => $driver,
        'load' => $id
    );
    echo json_encode($arr);
}
if ($action == 'getload')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $limit = $data->limit;
    $filter = $data->filter;
    $id = $data->id;
    $condition = '';
    if ($filter == 'complete')
    {
        $condition = "AND journey_status = 'Completed' AND deleted_by != '1'";
    }
    elseif ($filter == 'progress')
    {
        $condition = "AND journey_status != 'Completed' AND deleted_by != '1'";
    }
    elseif ($filter == 'deleted')
    {
        //$condition = "AND driver = '$token' AND deleted_by = '1' ";
        $condition = "AND deleted_by = '1' ";

    }

    $limitcond = '';
    if (!$limit == 'all')
    {
        $minval = ($data->page) * $limit;
        $limitcond = "LIMIT " . $minval . "," . $limit;
    }

    $sql = sprintf("SELECT * FROM loads WHERE (user_id='%s' OR driver='%s') %s %s ORDER BY id DESC", $token, $id, $condition, $limitcond);
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $nor = mysqli_num_rows($result);
    $progress = [];
    $completed = [];
    if ($nor > 0)
    {
        $array = array();
        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
        {
            $row['live_stock_type'] = get_live_stock_data($con, $row['id'], ''); //get_live_stock_data($con,$row['id']); //unserialize($row['live_stock_type']);
            $row['pickup_latlng'] = unserialize($row['pickup_latlng']);
            $row['drop_latlng'] = unserialize($row['drop_latlng']);
            $row['mapdata'] = unserialize($row['mapdata']);
            $row['days'] = getdatedifference($row['pickup_date'], $row['drop_date']);
            $userQuery = sprintf("SELECT * FROM users WHERE u_token='" . $row['user_id'] . "'");
            $userResult = mysqli_query($con, $userQuery);
            $row['user'] = mysqli_fetch_assoc($userResult);
            if ($row['driver'])
            {
                $userQuery = sprintf("SELECT * FROM users WHERE u_id=" . $row['driver']);
                $userResult = mysqli_query($con, $userQuery);
                $row['driver'] = mysqli_fetch_assoc($userResult);
                $row['driver']['requestStatus'] = 'accepted';
            }
            else
            {
                $query = "SELECT * FROM load_invitation where load_id=" . $row['id'] . " and status='pending'";
                $loadRequest = mysqli_query($con, $query);
                $loadInvitation = mysqli_fetch_assoc($loadRequest);
                if ($loadInvitation)
                {
                    $userQuery = sprintf("SELECT * FROM users WHERE u_id=" . $loadInvitation['driver_id']);
                    $loadRequest = mysqli_query($con, $userQuery);
                    $row['driver'] = mysqli_fetch_assoc($loadRequest);
                    $row['driver']['requestStatus'] = 'pending';
                }

            }
            $array[] = $row;
        }
        $resPonce = array(
            'status' => '200',
            'data' => $array
        );
        echo json_encode($resPonce);

        //   $arr = mysqli_fetch_array($result,MYSQLI_ASSOC);

    }
    else
    {
        $resPonce = array(
            'status' => '400',
            'data' => ''
        );
        echo json_encode($resPonce);
    }
    exit;
}
if ($action == 'savenumber')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $mobileno = $data->mobileno;
    $sql = "UPDATE users SET u_mobileno='$mobileno' WHERE u_token='$token'";
    if (mysqli_query($con, $sql))
    {
        $arr = array(
            'status' => '200'
        );
        echo json_encode($arr);
    }
    else
    {
        $arr = array(
            'status' => '400'
        );
        echo json_encode($arr);
    }
    exit;
}
if ($action == 'saveimage')
{
    $ext = explode('.', $_FILES['photo']['name']) [count(explode('.', $_FILES['photo']['name'])) - 1];
    $filename = rand() . "_" . time() . "." . $ext;
    $target_dir = $target_dir . "/" . $filename;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir))
    {
        $target_dir = $domain_name . $filename;

        $arr = array(
            'status' => '200',
            'url' => $target_dir
        );
        echo json_encode($arr);

    }
    exit;
}
if ($action == 'savedoc')
{
    $target_dir = "uploads";
    $type = $_REQUEST['filetype'];
    $resName = $_FILES['doc']['name'];
    $filename = rand() . "_" . time() . $type;
    $target_dir = $target_dir . "/" . $filename;
    if (move_uploaded_file($_FILES['doc']['tmp_name'], $target_dir))
    {
        $target_dir = $domain_name . $target_dir;
        $arr = array(
            'status' => '200',
            'url' => $target_dir,
            'filename' => $resName
        );
        echo json_encode($arr);
    }
    exit;
}

if ($action == 'gettrailersboards')
{
    error_reporting(0);
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $token = $data->token;
    $pagesize = 10;
    $livestocktype = $data->livestocktype;
    $page = $data->page;
    $previous = $data->page;
    $paginationCond = paginationCondition($page, $pagesize);
    $livestockarray = $livestocktype != '' ? explode(',', $livestocktype) : [];
    $condition = ($data->trailerType != '') ? " AND customer_trailer.t_id='" . $data->trailerType . "'" : "";
    //customer_trailer.u_token != '".$token."'
    $sql = "SELECT *, customer_trailer.t_image as trailer_image FROM customer_trailer INNER JOIN trailers ON customer_trailer.t_id=trailers.t_id LEFT JOIN users on users.u_token=customer_trailer.u_token WHERE customer_trailer.u_token != 'NULL' " . $condition . " ORDER BY id DESC " . $paginationCond;
    // Execute the query
    $result = mysqli_query($con, $sql) or die(mysqli_error());
    $no = mysqli_num_rows($result);
    $array = [];
    while ($row = mysqli_fetch_assoc($result))
    {
        $row['t_lstype'] = unserialize($row['t_lstype']);
        $row['t_total'] = unserialize($row['t_total']);
        $row['t_cmeasurement'] = unserialize($row['t_cmeasurement']);
        $row['u_image'] = $domain_name . $row['u_image'];
        $row['t_image'] = $domain_name . $row['t_image'];
        $row['trailer_image'] = $row['trailer_image'] ? $domain_name . $row['trailer_image'] : '';

        if (count(array_intersect(($row['t_lstype']) , $livestockarray)) > 0)
        {
            $array[] = $row;
        }
        if (count($livestockarray) == 0)
        {
            $array[] = $row;
        }
    }
    if (empty($array) || $no < 7)
    {
        $resPonce = array(
            'status' => '200',
            'data' => $array,
            'thisPage' => null
        );
        echo json_encode($resPonce);
    }
    else
    {
        $resPonce = array(
            'status' => '200',
            'data' => $array,
            'thisPage' => $previous + 1
        );
        echo json_encode($resPonce);
    }
    exit;
}
if ($action == 'addcalculation')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $trailer_id = $data->trailer_id;
    $user_id = $data->user_id;
    $calculatedLoad = serialize($data->calculatedLoad);
    $maxLoad = serialize($data->maxLoad);
    $compartment = serialize($data->compartment);

    $data = array(
        'user_id' => $user_id,
        'trailer_id' => $trailer_id,
        'calculatedLoad' => $calculatedLoad,
        'maxLoad' => $maxLoad,
        'compartment' => $compartment
    );

    createDatabaseRecord($con, $data, 'calculations');
    echo json_encode(array(
        'status' => '200',
        'message' => 'record added'
    ));

}
if ($action == 'getcalculation')
{
    global $con;

    $data = json_decode(file_get_contents('php://input'));
    $user_id = $data->user_id;
    //$sql = "DELETE FROM calculations WHERE 1";
    //mysqli_query($con, $sql);
    $calculations = getMultipleRowData($con, 'calculations.*, CONCAT("http://conveyenceoffice.livestockloader.com/uploads/",trailers.t_image) as
   t_image, trailers.t_name', 'calculations', ' LEFT JOIN trailers on calculations.trailer_id=trailers.t_id WHERE user_id=' . $user_id);
    $resPonce = array();
    foreach ($calculations as $calc)
    {
        $calc['calculatedLoad'] = unserialize($calc['calculatedLoad']);
        $calc['maxLoad'] = unserialize($calc['maxLoad']);
        $calc['compartment'] = unserialize($calc['compartment']);
        $resPonce[] = $calc;
    }
    echo json_encode($resPonce);
    exit;
}
if ($action == 'deletecalculation')
{
    global $con;
    $data = json_decode(file_get_contents('php://input'));
    $id = $data->id;
    $sql = "DELETE FROM calculations WHERE id =" . $id;
    mysqli_query($con, $sql);
    echo json_encode(array(
        'status' => '200','message'=>'record deleted'));

   }
if($action == 'updatepassword'){
	global $con;
	$data = json_decode(file_get_contents('php://input'));
  
	$email  = $data ->email;
	$password  = $data ->password;
    $sql = "UPDATE users SET u_password='$password' WHERE u_email='$email'";  
	   
			 
		   if (mysqli_query($con, $sql)) {
					  $arr = array(
						   'status'=>'200'
						   
						   );
				   echo json_encode($arr);
			   } else {
				  $arr = array(
						   'status'=>'400'
						   );
				   echo json_encode($arr);
			   }
			   exit;
	   
}
if($action == 'updatepushtoken'){
	// Type your website name or domain name here.
	global $con;
	 $data = json_decode(file_get_contents('php://input'));
	$id  = $data ->id;
	$token  = $data ->push_token;
	 $sql = "UPDATE users SET  push_token='$token' WHERE u_id='$id'";
	$result = mysqli_query($con,$sql) or die(mysqli_error()); 
		if(!empty($result)){
			$arr = array(
				'status'=>'200',
				);
			echo json_encode($arr);
		
	}else{
		$arr = array(
			'status'=>'400',
			);
		echo json_encode($arr);
	}
	exit;
}
if($action == 'getnotification'){
	global $con;
	$data = json_decode(file_get_contents('php://input'));
	$id  = $data->user_id;
 $sql = "SELECT * from notification WHERE receiver_id='$id'"; 
   $result = mysqli_query($con,$sql) or die(mysqli_error()); 
   $nor = mysqli_num_rows($result);
   if($nor > 0){
	 $array = array();

	   while($row = mysqli_fetch_array($result,MYSQLI_ASSOC)){
			   $array[] = $row;
	   }
		   $resPonce = array(
		   'status'=>'200',
		   'data'=>$array
		   );
	  echo json_encode($resPonce);
   }else{
	   $resPonce = array(
		   'status'=>'200',
		   'data'=>$array
		   );
	  echo json_encode($resPonce);
   }
   exit;
}
	if($action == 'gettokenbyid'){
		global $con;
		$data = json_decode(file_get_contents('php://input'));
		$id  = $data ->id;
		$sql = "SELECT push_token FROM users WHERE u_id='$id'";  
		// Execute the query

		$result = mysqli_query($con,$sql) or die(mysqli_error()); 

		$row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		$row['u_image']=$domain_name.$row['u_image'];
		$arr = array(
		'status'=>'200',
		'data'=>$row
		);
		echo json_encode($arr);
		exit;
	}

	


		if($action == 'forget-password'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$email  = $data->email;
			
			$query = sprintf("SELECT * FROM users WHERE u_email='%s' ",$email);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			$rowcount=mysqli_num_rows($result);
			$row = mysqli_fetch_assoc($result);
			if($rowcount>0)
			{
				
				$token = (rand(1000,10000)); //md5(getRandomString(40));
				$query = sprintf("UPDATE users SET password_reset_token='%s' WHERE u_id='%s' ",$token,$row['u_id']);
				if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
				$subject = "Password Reset Instructions OTP";
				$body = '<p>Hello '.$row['u_fullname'].',</p>

				<p>You have requested for password reset, Please find below otp.</p>
				
				<p>OTP : '.$token.' </p>
				
				<p>Please Ignore this mail if you have not requested for password reset.</p>
				
				<p>Thanks &amp; Regards,<br />
				Live Stock Loader Team</p>
				';
				sendMail($email,$body,$subject);

				 $arr = array(
					'status'=>'200',
					'message'=>"Mail with instructions Sent on your email Id (".$email.") Successully",
					'data'=>$row
					);
				echo json_encode($arr);
			}
			else{
	 			$arr = array(
					'status'=>'403',
					'message'=>"Please check with your email Id, we are not having any user with this email."
					);
				echo json_encode($arr);
			}
			exit;
		}

		if($action == 'password-change'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$password  = $data->password;
			$cpassword  = $data->cpassword;
			$u_id  = $data->u_id;
			if($password!=$cpassword)
				{
					$arr = array(
						'status'=>'400',
						'message'=>"Password Missmatch"
						);
					echo json_encode($arr); exit;
				}
				$query = sprintf("UPDATE users SET u_password='%s', password_reset_token='' WHERE u_id = '%s'",md5($password),$u_id);
				if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}

				$row = mysqli_fetch_assoc($result);
				$arr = array(
					'status'=>'200',
					'message'=>"Password Changed Successfully!!",
					'data'=>$row
					);
					echo json_encode($arr); exit;

		}
		if($action == 'otp-check'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$token  = $data->otp;
			$u_id  = $data->u_id;

			$query = sprintf("SELECT * from users WHERE password_reset_token = '%s' AND u_id='%s'",$token,$u_id);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			if(mysqli_num_rows($result)>0)
			{
				$row = mysqli_fetch_assoc($result);
				$arr = array(
					'status'=>'200',
					'message'=>"OTP verified Successfully!!",
					'data'=>$row
					);
					echo json_encode($arr); exit;
			}
			else{
				$arr = array(
					'status'=>'403',
					'message'=>"Wrong or Expired Token!"
					);
					echo json_encode($arr); exit;
			}
		}

		if($action == 'mobile-verification'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$mobileno  = $data->mobileno;
			$country_code  = $data->country_code;
			$u_id  = $data->u_id;
			$otp = (rand(1000,10000));

			$query = sprintf("UPDATE users SET u_countrycode='%s', u_mobileno='%s', phone_verification_otp='%s' WHERE u_id = '%s'",$country_code,$mobileno,$otp,$u_id);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			group_accept_invite($con,getSingleRowData($con,'*','users',' WHERE u_id='.$u_id));
			$msg='Your OTP for mobile verification is !'.$otp;
			if($mobileno!=='9175129361' && $mobileno!=='9340828729' && $mobileno!=='9755475661' && $mobileno!=='7798565860' )
			{
				$res = sendSMS($country_code,$mobileno,$msg);
			}
			
			if($res['error']) {
				$arr = array(
					'status'=>'400',
					'message'=>$res['message']
					);
			} else {
				$arr = array(
					'status'=>'200',
					'message'=>"OTP sent!",
					'otp'=>$otp
					);
			}
			
				echo json_encode($arr); exit;
		}

		if($action == 'mobile-verification-check'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$otp  = $data->otp;
			$u_id  = $data->u_id;
			$query = sprintf("SELECT * FROM users WHERE u_id = '%s' AND phone_verification_otp='%s' AND phone_verified!='1'",$u_id,$otp);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			if(mysqli_num_rows($result)>0)
			{
				$query = sprintf("UPDATE users SET phone_verified='1', phone_verification_otp='' WHERE u_id = '%s'",$u_id);
				if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
				$arr = array(
					'status'=>'200',
					'message'=>"Phone Verified "
					);
					echo json_encode($arr); exit;
			}
			else{
				$arr = array(
					'status'=>'403',
					'message'=>"Wrong OTP or Expired! "
					);
					echo json_encode($arr); exit;
			}
		}


		if($action == 'email-verification'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$email  = $data->email;
			$u_id  = $data->u_id;
			$randomString = md5(getRandomString(25));
			
			$query = sprintf("SELECT * FROM users  WHERE u_id = '%s'",$u_id);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			if(mysqli_num_rows($result)>0)
			{
				$row = mysqli_fetch_assoc($result);
				$query = sprintf("UPDATE users SET email_verification_token='%s' WHERE u_id = '%s'",$randomString,$u_id);
				if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
				sendVerificationMail($row['u_email'],$randomString);
				$arr = array(
					'status'=>'200',
					'token'=>$randomString,
					'data'=>$row
					);
				echo json_encode($arr);
			}
			else{
				$arr = array(
					'status'=>'403',
					'message'=>"Wrong User Data"
					);
				echo json_encode($arr); 
			}
			exit;
		}
		if($action == 'changeloadstatus'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$query = sprintf("UPDATE loads set journey_status='%s' WHERE id = '%s'",$data->status,$data->load_id);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			echo json_encode(array(
				'status'=>'200',
				'message'=>"Status updated successfully"
				));
			exit;
		}
		if($action == 'adminlogin'){
			global $con;
			echo json_encode(loginFunction($con,json_decode(file_get_contents('php://input')))); exit;
		}
		if($action == 'addCalculatorCategory'){
			global $con;
			echo json_encode(addcategory($con,json_decode(file_get_contents('php://input')))); exit;
		}
		// if($action == 'updateCalculatorCategory'){
		// 	global $con;
		// 	echo json_encode(updatecategory($con,json_decode(file_get_contents('php://input'))));
		// }
		if($action == 'getCalculatorCategory'){
			global $con;
			echo json_encode(getcalcategory($con)); exit;
		}

		if($action == 'createAnimal'){
			global $con;
			echo json_encode(createAnimal($con,json_decode(file_get_contents('php://input')))); exit;
		}
		if($action == 'getAllAnimals'){
			global $con;
			echo json_encode(getAnimals($con)); exit;
		}
		if($action == 'getAnimalsByCat'){
			global $con;
			echo json_encode(getAnimalsByCat($con,json_decode(file_get_contents('php://input')))); exit;
		}
		// echo json_encode($_REQUEST); exit;
		if($action == 'deleteAnimal'){
			global $con;
			echo json_encode(deleteAnimals($con,json_decode(file_get_contents('php://input')))); exit;
		}
		if($action == 'deleteCategory'){
			global $con;
			echo json_encode(deleteCategory($con,json_decode(file_get_contents('php://input')))); exit;
		}


		if($action == 'addAdminTrailer'){
			global $con;
			echo createTrailers($con,$target_dir); exit;
		}
		if($action == 'getAllTrailers'){
			global $con;
			echo getAllTrailers($con,$domain_name); exit;
		}
		
		if($action == 'deleteTrailer'){
			global $con;
			echo json_encode(deleteTrailer($con,json_decode(file_get_contents('php://input')))); exit;
		}

		if($action == 'updateMembership'){
			global $con;
			// echo json_encode(array('data'=>$_POST,'enc'=>json_decode(file_get_contents('php://input'))));
			echo json_encode(updateMembership($con,json_decode(file_get_contents('php://input')))); exit;
		}
		if($action == 'getMembership'){
			global $con;
			echo json_encode(getMembership($con)); exit;
		}

		if($action == 'getCalculatorData'){
			global $con;
			echo json_encode(getCalcularData($con)); exit;
		}

		/*
		 Dashboard API :- 
		*/
		if($action == 'getTabsData'){
			global $con;
			echo json_encode(array(
				'users'=>getCountOfTableData($con,'users',''),
				'users_trailers'=>getCountOfTableData($con,'customer_trailer',''),
				'active_loads'=> getCountOfTableData($con,'loads',' WHERE journey_status!="Completed"'),
				'completed_loads'=> getCountOfTableData($con,'loads',' WHERE journey_status="Completed"')
			)); exit;
		}

		if($action == 'getLatest10Loads'){
			global $con;
			echo json_encode(getLatest10Loads($con,$domain_name,$_GET['loadtype'])); exit;
		}
		
		if($action=='deleteUser')
		{
			$id = $_GET['id'];
			$query = sprintf("DELETE FROM users WHERE u_id='%d' ",$id);
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			echo json_encode(array(
				'status'=>'200',
				'message'=>"User deleted successfully"
				));
			exit;
		}
		

		if($action == 'getallusers'){
			global $con;
			$query = sprintf("SELECT * FROM users");
			if(!$result = mysqli_query($con, $query)){echo "Error : - ".$query.":-".mysqli_error($con); exit;}
			$array = [];
			while($row = mysqli_fetch_assoc($result))
			{
				$row['u_image']=$domain_name.$row['u_image'];
				$array[]=$row;
			}
			echo json_encode($array); exit;
		}
		
		
		if($action == 'loadInvitation'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$id  = $data->id;
			$query = "SELECT * FROM load_invitation where id=".$id;
			if(!$result = mysqli_query($con, $query)){$error = "Error : - ".$query.":-".mysqli_error($con);}
			$array = [];
			$row = mysqli_fetch_assoc($result);
			$userQuery = sprintf("SELECT * FROM users WHERE u_id=". $row['driver_id']);
			$userResult = mysqli_query($con, $userQuery);
			$driverDetail=mysqli_fetch_assoc($userResult);
			$row['driver_id']= $driverDetail;
			$query = sprintf("SELECT * FROM loads WHERE id=". $row['load_id']);
			$loadResult = mysqli_query($con, $query);
			$load = mysqli_fetch_assoc($loadResult);
			$live_stock_type = get_live_stock_data($con,$load['id'],'');
			if(count($live_stock_type)>0)
			{
				$load['live_stock_type'] = $live_stock_type; //unserialize($row['live_stock_type']);
				$load['pickup_latlng']=unserialize($load['pickup_latlng']);
				$load['drop_latlng']=unserialize($load['drop_latlng']);
				$load['mapdata']=unserialize($load['mapdata']);
				$load['days'] = getdatedifference($load['pickup_date'],$load['drop_date']);
				$load['u_image'] = $domain_name.$load['u_image'];
			}
			$row['load_id']=$load;
			$userQuery = sprintf("SELECT * FROM users WHERE u_token='". $load['user_id']."'");
			$userResult = mysqli_query($con, $userQuery);
			$loadUser=mysqli_fetch_assoc($userResult);
			$row['user']=$loadUser;
			echo json_encode($row); exit;
		}
		
		if($action == 'loadInvitations'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$driver  = $data->driver;
			$status  = $data->status ? $data->status : 'pending';
			$query = "SELECT * FROM load_invitation where driver_id=".$driver." and status='".$status."'";
			$userQuery = sprintf("SELECT * FROM users WHERE u_id=". $driver);
			$userResult = mysqli_query($con, $userQuery);
			$driverDatail=mysqli_fetch_assoc($userResult);
			if(!$result = mysqli_query($con, $query)){$error = "Error : - ".$query.":-".mysqli_error($con);}
			$array = [];
			while($row = mysqli_fetch_assoc($result))
			{
				$row['driver_id']= $driverDatail;
				$query = sprintf("SELECT * FROM loads WHERE id=". $row['load_id']);
				$loadResult = mysqli_query($con, $query);
				while($load = mysqli_fetch_assoc($loadResult))
				{
					$row['load_id']=$load;
				}
				$array[]=$row;
			}
			echo json_encode($array); exit;
		}
		
		if($action == 'acceptLoadInvitation'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$id  = $data->id;
			$query = sprintf("UPDATE load_invitation SET status='%s' WHERE id = '%s'",'accepted',$id);
			$error = '';
			if(!$result = mysqli_query($con, $query)){
				$error = "Error : - ".$query.":-".mysqli_error($con);
			}
			$query = sprintf("SELECT * FROM load_invitation WHERE id=". $id);
			if(!$loadInvitationRes = mysqli_query($con, $query)){
				$error = "Error : - ".$query.":-".mysqli_error($con);
			}
			$loadInvitation = mysqli_fetch_assoc($loadInvitationRes);
			$sql = sprintf("UPDATE loads set driver='%s' WHERE id = '%s'", $loadInvitation['driver_id'],$loadInvitation['load_id']);
			if(!mysqli_query($con, $sql)) {
				$error = "Error : - ".$sql.":-".mysqli_error($con);
			}
			
			$sql = "SELECT * FROM users WHERE u_id=".$loadInvitation['driver_id'];
			if(!$res = mysqli_query($con, $sql)) {
				$error = "Error : - ".$sql.":-".mysqli_error($con);
			}
			$driver = mysqli_fetch_assoc($res);
			
			
			$sql = "SELECT * FROM loads WHERE id=".$loadInvitation['load_id'];
			if(!$res = mysqli_query($con, $sql)) {
				$error = "Error : - ".$sql.":-".mysqli_error($con);
			}
			$load = mysqli_fetch_assoc($res);
			
			$sql = "SELECT * FROM users WHERE u_token='".$load['user_id']."'";
			if(!$res = mysqli_query($con, $sql)) {
				$error = "Error : - ".$sql.":-".mysqli_error($con);
			} 
			$loader = mysqli_fetch_assoc($res);
			
			$array = array('msg'=>"Your invitation for the load has been accepted by ".$driver['u_fullname'],
					'sender_name'=>$driver['u_fullname'],
					'sender_id'=>$driver['u_id'],
					'receiver_id'=>$loader['u_id'],
					'message_type'=>'loadInvitationAccepted',
					'group_id'=>$id);
			createNotification($con,$array);
			echo json_encode(array(
				'status'=>'200',
				'message'=>"inviation accepted successfully",
				"error"=> $error
				));
			exit;	
			
		}
		
		if($action == 'cancelLoadInvitation'){
			global $con;
			$data = json_decode(file_get_contents('php://input'));
			$id  = $data->id;
			$query = sprintf("UPDATE load_invitation SET status='%s' WHERE id = '%s'",'cancelled',$id);
			if(!$result = mysqli_query($con, $query)){
				echo "Error : - ".$query.":-".mysqli_error($con);
				exit;
			}
			
			$query = sprintf("SELECT * FROM load_invitation WHERE id=". $id);
			$res = mysqli_query($con, $query);
			$loadInvitation = mysqli_fetch_assoc($res);
			
			$sql = "SELECT * FROM users WHERE u_id=".$loadInvitation['driver_id'];
			$res = mysqli_query($con,$sql) or die(mysqli_error()); 
			$driver = mysqli_fetch_assoc($res);
			
			$sql = "SELECT * FROM loads WHERE id=".$loadInvitation['load_id'];
			$res = mysqli_query($con,$sql) or die(mysqli_error()); 
			$load = mysqli_fetch_assoc($res);
			
			$sql = "SELECT * FROM users WHERE u_token='".$load['user_id']."'";
			$res = mysqli_query($con,$sql) or die(mysqli_error()); 
			$loader = mysqli_fetch_assoc($res);
			
			$array = array('msg'=>"Your invitation for the load has been cancelled by ".$driver['u_fullname'],
					'sender_name'=>$driver['u_fullname'],
					'sender_id'=>$driver['u_id'],
					'receiver_id'=>$loader['u_id'],
					'message_type'=>'loadInvitationCancelled',
					'group_id'=>$id);
			createNotification($con,$array);
			echo json_encode(array(
				'status'=>'200',
				'message'=>"inviation camcelled successfully"
				)); 
			exit;	
			
		}
/*
GROUP API Starts HERE
*/
	if($action == 'getgroupofuser')
	{
		global $con;
		echo json_encode(group_getgroupofuser($con,json_decode(file_get_contents('php://input')))); exit;
	}
	if($action == 'getusersofgroup')
	{
		global $con;
		echo json_encode(group_getusersofgroup($con,json_decode(file_get_contents('php://input')))); exit;
	}
    if($action == 'addgroup'){
		global $con;
		echo json_encode(group_addgroup($con,json_decode(file_get_contents('php://input')))); exit;
    }
    if($action == 'invitationaccrej'){
        global $con;
		echo json_encode(group_invitationaccrej($con,json_decode(file_get_contents('php://input')))); exit;
	}
	if($action == 'getmygroup'){
        global $con;
		echo json_encode(getmygroups($con,json_decode(file_get_contents('php://input')))); exit;
    }
	if($action == 'getgroupbyid'){
        global $con;
		echo json_encode(group_getgroupbyid($con,json_decode(file_get_contents('php://input')))); exit;
	}

	if($action == 'getactiveloadsofgroup'){
        global $con;
		echo json_encode(group_getactiveloadsofgroup($con,json_decode(file_get_contents('php://input')),$domain_name)); exit;
	}
	if($action == 'getcompletedloadsofgroup'){
        global $con;
		echo json_encode(group_getcompletedloadsofgroup($con,json_decode(file_get_contents('php://input')),$domain_name)); exit;
	}

	if($action == 'groupadminactiononinvite'){
        global $con;
		echo json_encode(group_groupadminactiononinvite($con,json_decode(file_get_contents('php://input')))); exit;
	}

	if($action == 'exitgroup'){
        global $con;
		echo json_encode(group_exitgroup($con,json_decode(file_get_contents('php://input')))); exit;
	}
	if($action == 'deletegroup'){
        global $con;
		echo json_encode(group_deletegroup($con,json_decode(file_get_contents('php://input')))); exit;
	}

	
/*
GROUP API Ends HERE
*/

/*
Notification API Starts Here
*/
if($action == 'sendmessage'){
	global $con;
	echo json_encode(noti_sendmessage($con,json_decode(file_get_contents('php://input')),$domain_name)); exit;
}

if($action == 'update-profile-notification')
{
	global $con;
	echo json_encode(noti_update_profile($con,json_decode(file_get_contents('php://input')))); exit;
}

		function getRandomString($n=25){
            $randomString = ''; 
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
            for ($i = 0; $i < $n; $i++) { 
                $index = rand(0, strlen($characters) - 1); 
                $randomString .= $characters[$index]; 
			} 
			return $randomString;
		}

		function getdatedifference($date1,$date2)
		{
			return date_diff(date_create($date1),date_create($date2))->format("%a Days");
		}
		

		//include_once('smsservice/index.php');
?>
