<?php
session_start();
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

                                    <!-- Reset password form column -->
                                    <div class="col-lg-5 col-md-6">
                                        <div class="card-body p-5">
                                            <!-- Header -->
                                            <div class="text-center">
                                                <img class="mb-3" src="../assets/favicon.png" alt="Logo" style="height: 48px" />
                                                <h1 class="display-5 mb-0">Reset Password</h1>
                                                <div class="subheading-1 mb-5">to continue</div>
                                            </div>

                                            <!-- Reset password form -->
                                            <form method="POST" action="../auth/process_reset_request.php" class="mb-5">

                                                <div class="mb-4">
                                                    <input type="email" name="email" id="email" class="form-control" placeholder="Email Address" required>
                                                    <div class="form-text">You will receive an email with a link to reset your password.</div>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-4">
                                                    <a class="small fw-500 text-decoration-none" href="login">Return to login</a>
                                                    <button type="submit" class="btn btn-primary">Reset Password</button>
                                                </div>

                                            </form>

                                            <!-- Footer text -->
                                            <div class="text-center">
                                                <a class="small fw-500 text-decoration-none" href="register">Need an account? Sign up!</a>
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