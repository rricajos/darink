<!doctype html>
<html lang="en">

<head>
    <title>Sign In / Sign Up - Darink</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- Themify Icons -->
    <link rel="stylesheet" href="css/themify-icons.css">
    <!-- Custom Styles -->
    <link href="css/style.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: 'Rubik', sans-serif;
        }

        .auth-container {
            max-width: 420px;
            margin: 100px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .auth-container h2 {
            font-weight: 500;
            color: #333;
        }

        .nav-tabs {
            border-bottom: none;
        }

        .nav-tabs .nav-item .nav-link {
            color: #ff4d7e;
            border-radius: 30px;
            padding: 10px 20px;
            font-size: 16px;
            transition: 0.3s;
        }

        .nav-tabs .nav-item .nav-link.active {
            background: #ff4d7e;
            color: #fff;
            border-radius: 30px;
        }

        .form-group {
            position: relative;
        }

        .form-control {
            border-radius: 30px;
            padding: 10px 15px;
            padding-left: 40px;
        }

        .form-group .ti {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn-primary {
            background: #ff4d7e;
            border: none;
            border-radius: 30px;
            padding: 10px;
            font-size: 16px;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background: #e03a68;
        }

        .forgot-password {
            font-size: 0.9em;
            color: #ff4d7e;
            text-decoration: none;
            display: block;
            margin-top: 10px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <!-- Sign In / Sign Up Form -->
    <div class="auth-container">
        <h2>Welcome to Darink</h2>

        <ul class="nav nav-tabs nav-justified mt-4 mb-4" id="authTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="signInTab" data-toggle="tab" href="#signIn" role="tab">Sign In</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="signUpTab" data-toggle="tab" href="#signUp" role="tab">Sign Up</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Sign In Form -->
            <div class="tab-pane fade show active" id="signIn" role="tabpanel">
                <form>
                    <div class="form-group">
                        <span class="ti-email ti"></span>
                        <input type="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <span class="ti-lock ti"></span>
                        <input type="password" class="form-control" placeholder="Password" required>
                    </div>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                    <button type="submit" class="btn btn-primary btn-block mt-3">Sign In</button>
                </form>
            </div>

            <!-- Sign Up Form -->
            <div class="tab-pane fade" id="signUp" role="tabpanel">
                <form>
                    <div class="form-group">
                        <span class="ti-user ti"></span>
                        <input type="text" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <span class="ti-email ti"></span>
                        <input type="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <span class="ti-lock ti"></span>
                        <input type="password" class="form-control" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mt-3">Sign Up</button>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap -->
    <script src="js/jquery-3.2.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>

</body>

</html>
