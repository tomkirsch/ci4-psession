<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Psession</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <div style="display:flex;" ?>

        <?php if (!empty($_SESSION["user_id"])) : ?>
            <form method="POST">
                <p>Logged in as user <?= $_SESSION["user_id"] ?></p>
                <button type="submit" name="action" value="logout">Log Out</button>
            </form>
        <?php else : ?>
            <form method="POST" style="margin-right: 10px;">
                <p>Register:</p>
                <p><input type="email" name="email" placeholder="Email"></p>
                <p><input type="password" name="password" placeholder="Password"></p>
                <button type="submit" name="action" value="register">Register</button>
            </form>
            <form method="POST">
                <p>Login:</p>
                <p><input type="email" name="email" placeholder="Email"></p>
                <p><label><input type="checkbox" name="remember" value="1"> Remember Me</label></p>
                <button type="submit" name="action" value="login">Log In</button>
            </form>
        <?php endif ?>
    </div>
    <?= anchor("home/randompage/" . (rand() * 100000), "Random page") ?>
</body>

</html>