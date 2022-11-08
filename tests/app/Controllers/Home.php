<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $post = $this->request->getPost();
        if (!empty($post)) {
            $db = db_connect();
            if ($post["action"] === "register") {
                $builder = $db->table('users');
                $result = $builder->where("user_email", $post["email"])->get();
                if ($user = $result->getRow()) {
                    $builder->where("user_id", $user->user_id);
                }
                $builder->set([
                    "user_email" => $post["email"],
                    "user_password" => $post["password"],
                ]);
                $user ? $builder->update() : $builder->insert();
                print $user ? "Updated user" : "Created user";
            } else if ($post["action"] === "login") {
                $builder = $db->table('users');
                $user = $this->session->findSession()->where('user_email', $post["email"])->get()->getRow();
                if ($user) {
                    $this->session->loginSuccess($user, !empty($post["remember"]));
                    $_SESSION["user_id"] = $user->user_id;
                    $_SESSION["user_email"] = $user->user_email;
                    print "Logged in";
                } else {
                    print "Invalid email/PW";
                }
            } else {
                $this->session->destroy();
                $_SESSION = [];
            }
        }
        return view('welcome_message');
    }

    public function randompage($num)
    {
        $warn = "";
        if (empty($_SESSION["user_id"])) {
            $warn = "<h2>LOGGED OUT!</h2>";
        }
        print "<html><body>$warn<p>Random page $num</p>" . anchor("home/randompage/" . (rand() * 100000), "Another random page") . "<br>" . anchor("", "Home") . "</body></html>";
    }
}
