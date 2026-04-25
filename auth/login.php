<?php
session_start();

// If user already logged in, go to dashboard
if (isset($_SESSION['user'])) {
    header("Location: ../dashboard/");
    exit;
}


require_once "../partials/head.php";
?>

<body class="bg-pattern-waihou">
    <!-- Layout wrapper -->
    <div id="layoutAuthentication">
        <!-- Layout content -->
        <div id="layoutAuthentication_content">
            <main>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-xxl-10 col-xl-10 col-lg-12">
                            <div class="card card-raised shadow-10 mt-5 mt-xl-10 mb-4">
                                <div class="row g-0">

                                    <!-- Login form column -->
                                    <div class="col-lg-5 col-md-6">
                                        <div class="card-body p-5">
                                            <!-- Header -->
                                            <div class="text-center">
                                                <img class="mb-3" src="../assets/favicon.png" alt="Logo" style="height: 48px" />
                                                <h1 class="display-5 mb-0">Login</h1>
                                                <div class="subheading-1 mb-5">to continue</div>
                                            </div>

                                            <!-- Error message -->
                                            <?php if (!empty($_SESSION['error'])): ?>
                                                <div class="alert alert-danger">
                                                    <?= $_SESSION['error']; ?>
                                                </div>
                                                <?php unset($_SESSION['error']); ?>
                                            <?php endif; ?>

                                            <?php if (isset($_SESSION['success'])): ?>
                                                <div class="alert alert-success">
                                                    <?= $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Login form -->
                                            <form method="POST" action="process_login.php" class="mb-5">
                                                <div class="mb-4">
                                                    <input type="text" name="username" class="form-control" placeholder="Username or Email" required>
                                                </div>
                                                <div class="mb-4">
                                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                                </div>

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                                        <label class="form-check-label" for="remember">Remember me</label>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-4">
                                                    <a class="small fw-500 text-decoration-none" href="resetPassword">Forgot Password?</a>
                                                    <button type="submit" class="btn btn-primary">Login</button>
                                                </div>
                                            </form>

                                            <!-- Footer text -->
                                            <div class="text-center">
                                                <a class="small fw-500 text-decoration-none" href="register">New user? Create an account</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Background image column -->
                                    <div class="col-lg-7 col-md-6 d-none d-md-block"
                                        style="background-image: url('../assets/img/bg1.jpg');
                                                background-size: cover;
                                                background-repeat: no-repeat;
                                                background-position: center;">
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div id="layoutAuthentication_footer">
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
</body>

</html>