<?php
    require __DIR__ . '/vendor/autoload.php';

    if (getenv('APP_ENV') == 'production') {
        error_reporting(0);
        @ini_set('display_errors', 0);
    } else {
        error_reporting(1);
        @ini_set('display_errors', 1);
    }
    
    //load the environment variablea
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();

    $storage = new Flatbase\Storage\Filesystem('storage');
    $flatbase = new Flatbase\Flatbase($storage);
    
    session_start();
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['token'];

    $datas = $flatbase;

    $filter = new Twig\TwigFilter('timeago', function ($time) {

        $time = time() - $time; 
      
        $units = array (
          604800 => 'minggu',
          86400 => 'hari',
          3600 => 'jam',
          60 => 'menit',
          1 => 'detik'
        );
      
        foreach ($units as $unit => $val) {
            if ($time < $unit) continue;
            $numberOfUnits = floor($time / $unit);
            return ($val == 'second')? 'beberapa saat yang lalu' : 
                    (($numberOfUnits>1) ? $numberOfUnits : 'beberapa')
                    .' '.$val.(($numberOfUnits>1) ? '' : '').' yang lalu';
        }
      
    });

    $loader = new Twig\Loader\FilesystemLoader(__DIR__ . '/templates/'.getenv('TEMPLATE_PATH'));
    
    $twig = new \Twig\Environment($loader, [
        'debug' => true,
    ]);

    $twig->addFilter($filter);
    
    echo $twig->render('index.html.twig', [
        'datas' => $datas, 
        'csrf_token' => $csrf_token, 
        'session' => $_SESSION
    ]);

    if (isset($_SESSION['flash_name'])) {
        unset($_SESSION['flash_name']);
    }
    if (isset($_SESSION['flash_old_name'])) {
        unset($_SESSION['flash_old_name']);
    }
    if (isset($_SESSION['flash_phone'])) {
        unset($_SESSION['flash_phone']);
    }
    if (isset($_SESSION['flash_old_phone'])) {
        unset($_SESSION['flash_old_phone']);
    }
    if (isset($_SESSION['flash_attendance'])) {
        unset($_SESSION['flash_attendance']);
    }
    if (isset($_SESSION['flash_old_attendance'])) {
        unset($_SESSION['flash_old_attendance']);
    }
    if (isset($_SESSION['flash_greeting'])) {
        unset($_SESSION['flash_greeting']);
    }
    if (isset($_SESSION['flash_old_greeting'])) {
        unset($_SESSION['flash_old_greeting']);
    }
?>