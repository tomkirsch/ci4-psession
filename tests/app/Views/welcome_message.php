<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Welcome to CodeIgniter 4!</title>
    <meta name="description" content="The small framework with powerful features">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">

<body>


    <!-- CONTENT -->

    <?php if (!empty($_SESSION["user_id"])) : ?>
        <form method="POST">
            <p>Logged in as user <?= $_SESSION["user_id"] ?></p>

            <button type="submit" name="action" value="logout">Log Out</button>
        </form>
    <?php else : ?>
        <p>You are not logged in.</p>
        <form method="POST" style="margin-right: 10px;">
            <p>Register:</p>
            <p><input type="email" name="email" placeholder="Email"></p>
            <p><input type="password" name="password" placeholder="Password"></p>
            <button type="submit" name="action" value="register">Register</button>
        </form>
        <form method="POST">
            <p>Or, Login:</p>
            <p><input type="email" name="email" placeholder="Email"></p>
            <p><label><input type="checkbox" name="remember" value="1"> Remember Me</label></p>
            <button type="submit" name="action" value="login">Log In</button>
        </form>
    <?php endif ?>

    <p><?= anchor("home/randompage/" . (rand() * 100000), "Visit a random page") ?></p>
</body>

</html>