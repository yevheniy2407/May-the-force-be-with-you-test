<?php

namespace Model;

use App;
use System\Core\CI_Model;

class Login_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();

    }

    public static function logout()
    {
        App::get_ci()->session->unset_userdata('id');
    }

    public static function login(User_model $user)
    {
        App::get_ci()->session->set_userdata('id', $user->get_id());
    }
}
