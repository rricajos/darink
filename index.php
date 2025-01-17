<!DOCTYPE html>
<html lang="en">

<head>
    <title>darink.app</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- icon -->
    <link rel="icon" href="assets/images/heart.svg" type="image/svg+xml">

    <!-- font awesome css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- rricajos css -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php

    // default view
    $v = 'views/login.php';

    require_once 'controllers/UserController.php';
    $u = new UserController();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if (isset($_SESSION['user_id'])) {
            // check token to be continue...
            $USER_ID = $u->getUserById($_SESSION['user_id']);
            $USER_TOKEN = 'X';
        }


        switch ($_POST['case']) {

            case 'loggin':
                if ($u->login($_POST['nickname'], $_POST['password'])) {
                    $v = 'views/home.php';
                }
                break;

            case 'loggout':
                header('Location: index.php'); // get
                exit;

            case 'add':
                $id;
                require_once 'controllers/LunchController.php';
                $l = new LunchController($USER_ID);
                $l->createLunch();
                $v = 'views/lunch.php';
                break;

            default:
                break;

        }

        $_POST = [];
    } else {
        $_SESSION = [];
    }

    include_once 'views/_header.php';
    include_once $v;
    include_once 'views/_footer.php';


    ?>
</body>

</html>