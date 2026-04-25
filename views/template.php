
 <?php require_once "partials/head.php"  ?>
    <body class="nav-fixed bg-light">
        <!-- Top app bar navigation menu-->
        <?php require_once "../partials/topnav.php"  ?>
        <!-- Layout wrapper-->
        <div id="layoutDrawer">
            <!-- Layout navigation-->
            <?php require_once "../partials/sidebar.php"  ?>
            <!-- Layout content-->
            <div id="layoutDrawer_content">
                <!-- Main page content-->
                <main>
                    <!-- Page header-->
                    <header class="bg-dark">
                        <div class="container-xl px-5"><h1 class="text-white py-3 mb-0 display-6">Blank Page</h1></div>
                    </header>
                    
<!-- 
                    
your content goes here
-->

                </main>
                <!-- Footer-->
                <!-- Min-height is set inline to match the height of the drawer footer-->
                <?php require_once "../partials/footer.php"  ?>
            </div>
        </div>
        <?php require_once "../partials/scripts.php"  ?>
</body>    
</html>
