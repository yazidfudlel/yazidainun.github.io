<?php
ob_start();
header("X-Robots-Tag: noindex,nofollow");
use PHPMailer\PHPMailer\PHPMailer;
require __DIR__.'/vendor/autoload.php';
if (getenv('APP_ENV') == 'production') {
    error_reporting(0);
    @ini_set('display_errors', 0);
}else{
    error_reporting(1);
    @ini_set('display_errors', 1);
}

//load the environment variablea
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

//Initialize the flat file
$storage = new Flatbase\Storage\Filesystem('storage');
$flatbase = new Flatbase\Flatbase($storage);

//Initialize csrf_token
session_start();
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['token'];

if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
    $method = strtolower($_SERVER['REQUEST_METHOD']);
    //only post and delete method allowed
    if( $method === 'post' && isset($_REQUEST['method_field'])) {
        $tmp = strtolower((string)$_REQUEST['method_field']);
        if( in_array( $tmp, array( 'delete' ))) {
            $method = $tmp;
        }else{
            header('HTTP/1.0 501 Not Implemented');
            die();
        }
        unset($tmp);
    }

    //securing form from XSS
    // iterating over POST data
    foreach($_POST as $key => $value) { 
        //first we are doing non-destructive modifications
        //in case we will need to show the data back in the form on error
        $value = trim($value); 
        if (get_magic_quotes_gpc()) $value = stripslashes($value); 
        $value = htmlspecialchars($value,ENT_QUOTES); 
        $_POST[$key] = $value; 
        //here go "destructive" modifications, specific to the storage format
        $value = str_replace("\r","",$value);
        $value = str_replace("\n","<br>",$value);
        $value = str_replace("|","&brvbar;",$value);
        $msg[$key] = $value;
    }


    // now, just run the logic that's appropriate for the requested method
    switch( $method ) {
        case "post":
            // logic for POST here
            if (!empty($_POST['csrf_token'])) {
                if (hash_equals($_SESSION['token'], $_POST['csrf_token'])) {
                    // Proceed to process the form data

                    //validation
                    $validation = true;
                    if (!$msg['name']) { #name not filled
                        $_SESSION['flash_name'] = 'Please fill the name';
                        $validation = false;
                    }
                    if (!$msg['phone']) { #phone not filled
                        $_SESSION['flash_phone'] = 'Please fill the phone';
                        $validation = false;
                    }
                    if (!$msg['attendance']) { #attendance not filed
                        $_SESSION['flash_attendance'] = 'Please fill the attendance';
                        $validation = false;
                    }
                    if (!$msg['greeting']) { #greeting not filed
                        $_SESSION['flash_greeting'] = 'Please fill the greeting';
                        $validation = false;
                    }
                    if ($validation==false) {
                        $_SESSION['flash_old_name'] = $msg['name'];
                        $_SESSION['flash_old_phone'] = $msg['phone'];
                        $_SESSION['flash_old_attendance'] = $msg['attendance'];
                        $_SESSION['flash_old_greeting'] = $msg['greeting'];
                    }else{
                        //check if the inserted email is already exist
                        $phone_already_exist = $flatbase->read()->in('rsvp')->where('phone', '=', $msg['phone'])->first();
                        if (!$phone_already_exist) {
                            if (getenv('MAIL_FUNCTION')) {
                                //send the email first
                                //Create a new PHPMailer instance
                                $mail = new PHPMailer;
                                if (getenv('MAIL_SMTP')) {
                                    //Tell PHPMailer to use SMTP
                                    $mail->isSMTP();
                                    //Enable SMTP debugging
                                    // 0 = off (for production use)
                                    // 1 = client messages
                                    // 2 = client and server messages
                                    $mail->SMTPDebug = getenv('MAIL_DEBUG');
                                    //Set the hostname of the mail server
                                    $mail->Host = getenv('MAIL_HOST');
                                    //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
                                    $mail->Port = getenv('MAIL_PORT');
                                    //Set the encryption system to use - tls (deprecated) or ssl
                                    $mail->SMTPSecure = getenv('MAIL_ENCRYPTION');
                                    //Whether to use SMTP authentication
                                    $mail->SMTPAuth = true;
                                    //Username to use for SMTP authentication - use full email address for gmail
                                    $mail->Username = getenv('MAIL_USERNAME');
                                    //Password to use for SMTP authentication
                                    $mail->Password = getenv('MAIL_PASSWORD');
                                }
                                //Set who the message is to be sent from
                                $mail->setFrom( getenv('MAIL_FROM_ADDRESS'), $msg['name']);
                                //Set who the message is to be sent to
                                $mail->addAddress(getenv('MAIL_TO_ADDRESS'), getenv('MAIL_TO_NAME'));
                                //Set the subject line
                                $mail->Subject = getenv('MAIL_SUBJECT');
                                //Read an HTML message body from an external file, convert referenced images to embedded,
                                //convert HTML into a basic plain-text alternative body
                                $mail->IsHTML(true);    // set email format to HTML
                                $body = file_get_contents(getenv('MAIL_FILE_NAME'));
                                $body = preg_replace("{MAIL_TO_NAME}", getenv('MAIL_TO_NAME'), $body);
                                $body = preg_replace("{NAME}", $msg['name'], $body);
                                $body = preg_replace("{PHONE}", $msg['phone'], $body);
                                $body = preg_replace("{ATTENDANCE}", ($msg['attendance']==1) ? "hadir" : "tidak hadir", $body);
                                $body = preg_replace("{GREETING}", $msg['greeting'], $body);
                                $mail->msgHTML($body, __DIR__);
                                //Send the message, check for errors
                                $mail->send();
                            }
                            // if no errors - insert the rsvp and greeting to the file
                            $rsvp_id =  uniqid().uniqid();
                            $flatbase->insert()->in('rsvp')->set([
                                'id' => $rsvp_id,
                                'name' => $msg['name'], 
                                'phone' => $msg['phone'],
                                'attendance' => $msg['attendance'],
                                'created_at'  => time()
                            ])->execute();
                            $flatbase->insert()->in('greetings')->set([
                                'id' => uniqid().uniqid(),
                                'rsvp_id' => $rsvp_id, 
                                'greeting' => $msg['greeting']
                            ])->execute();
                        }
                    }
                    //and then redirect
                    exit(header("Location: index.php"));
                } else {
                    // Log this as a warning and keep an eye on these attempts
                    header('HTTP/1.0 501 Not Implemented');
                    die();
                }
            } 
            break;

        case "delete":
            // logic for DELETE here
            if (!empty($_POST['csrf_token'])) {
                if (hash_equals($_SESSION['token'], $_POST['csrf_token'])) {
                    // Proceed to process the form data
                    if (!$msg['id']){
                        Header("Location: ".getenv('STORAGE_VIEW_FILE_NAME')); 
                        exit();
                    }else{
                        // if no errors - delete the subscriber from the file
                        $flatbase->delete()->in('greetings')->where('id', '==', $msg['id'])->execute();
                        //and then redirect
                        Header("Location: ".getenv('STORAGE_VIEW_FILE_NAME')); 
                        exit();
                    }
                } else {
                        // Log this as a warning and keep an eye on these attempts
                    header('HTTP/1.0 501 Not Implemented');
                    die();
                }
            } 
            break;

        default:
        header('HTTP/1.0 501 Not Implemented');
        die();
    }
}
ob_flush();