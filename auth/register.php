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

                                    <!-- Register form column -->
                                    <div class="col-lg-5 col-md-6">
                                        <div class="card-body p-5">
                                            <!-- Header -->
                                            <div class="text-center">
                                                <img class="mb-3" src="../assets/favicon.png" alt="Logo" style="height: 48px" />
                                                <h1 class="display-5 mb-0">Create Account</h1>
                                                <div class="subheading-1 mb-5">to continue</div>
                                            </div>

                                            <!-- Flash messages -->
                                            <?php if (isset($_SESSION['error'])): ?>
                                                <div class="alert alert-danger">
                                                    <?= $_SESSION['error'];
                                                    unset($_SESSION['error']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (isset($_SESSION['success'])): ?>
                                                <div class="alert alert-success">
                                                    <?= $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (isset($_SESSION['warning'])): ?>
                                                <div class="alert alert-warning">
                                                    <?= $_SESSION['warning'];
                                                    unset($_SESSION['warning']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Register form -->
                                            <form method="POST" action="../auth/process_register.php" class="mb-5">

                                                <div class="mb-4">
                                                    <input type="text" name="full_names" class="form-control" placeholder="Full Name" required>
                                                </div>

                                                <div class="mb-4">
                                                    <input type="text" name="username" id="username" class="form-control" placeholder="Username">
                                                    <small id="usernameMsg"></small>
                                                </div>

                                                <div class="mb-4">
                                                    <input type="email" name="email" id="email" class="form-control" placeholder="Email Address">
                                                    <small id="emailMsg"></small>
                                                </div>

                                                <div class="mb-4">
                                                    <input type="tel" name="phone_number" class="form-control" placeholder="Phone Number">
                                                </div>

                                                <div class="mb-4">
                                                    <div class="input-group">
                                                        <input type="password" name="password" id="password" class="form-control" placeholder="Password">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', this)" tabindex="-1">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 7.61 17 4.5 12 4.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <small id="passwordMsg"></small>
                                                </div>

                                                <div class="mb-4">
                                                    <div class="input-group">
                                                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm Password" required>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirmPassword', this)" tabindex="-1">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 7.61 17 4.5 12 4.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-4">
                                                    <a class="small fw-500 text-decoration-none" href="login">Already have an account? Sign in</a>
                                                    <button type="submit" class="btn btn-primary">Create Account</button>
                                                </div>

                                            </form>
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
    <script>
        function checkUsername(value) {
            let msg = document.getElementById("usernameMsg");
            if (value.length < 3) {
                msg.innerHTML = "Username must be at least 3 characters";
                msg.style.color = "orange";
                return;
            }
            fetch("../auth/check_user.php?type=username&value=" + value)
                .then(r => r.text())
                .then(data => {
                    if (data === "taken") {
                        msg.innerHTML = "❌ Username already taken";
                        msg.style.color = "red";
                    } else {
                        msg.innerHTML = "✔ Username available";
                        msg.style.color = "green";
                    }
                });
        }

        function checkEmail(value) {
            let msg = document.getElementById("emailMsg");
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(value)) {
                msg.innerHTML = "❌ Invalid email format";
                msg.style.color = "red";
                return;
            }
            fetch("../auth/check_user.php?type=email&value=" + value)
                .then(r => r.text())
                .then(data => {
                    if (data === "taken") {
                        msg.innerHTML = "❌ Email already registered";
                        msg.style.color = "red";
                    } else {
                        msg.innerHTML = "✔ Email available";
                        msg.style.color = "green";
                    }
                });
        }

        function checkPassword(value) {
            let msg = document.getElementById("passwordMsg");
            let strength = 0;
            if (value.length >= 8) strength++;
            if (/[A-Z]/.test(value)) strength++;
            if (/[0-9]/.test(value)) strength++;
            if (/[^A-Za-z0-9]/.test(value)) strength++;
            if (value.length === 0) {
                msg.innerHTML = "";
                return;
            }
            if (strength <= 1) {
                msg.innerHTML = "Weak password";
                msg.style.color = "red";
            } else if (strength === 2) {
                msg.innerHTML = "Medium password";
                msg.style.color = "orange";
            } else {
                msg.innerHTML = "Strong password";
                msg.style.color = "green";
            }
        }

        document.getElementById("username").addEventListener("keyup", function() {
            checkUsername(this.value);
        });
        document.getElementById("email").addEventListener("keyup", function() {
            checkEmail(this.value);
        });
        document.getElementById("password").addEventListener("keyup", function() {
            checkPassword(this.value);
        });
    </script>
</body>

</html>