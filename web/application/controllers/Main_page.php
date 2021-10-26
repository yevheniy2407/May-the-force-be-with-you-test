<?php

use Model\Analytics_model;
use Model\Boosterpack_model;
use Model\Comment_model;
use Model\Enum\Transaction_type;
use Model\Login_model;
use Model\Post_model;
use Model\User_model;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{

    public function __construct()
    {

        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $boosterpacks =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $boosterpacks]);
    }

    public function login()
    {
        $email = (string)App::get_ci()->input->post('login');
        $password = (string)App::get_ci()->input->post('password');
        // TODO: check if values not empty and validate?

        $user = User_model::find_user_by_email($email);
        if ( ! $user->is_loaded() || $user->get_password() !== $password)
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS);
        }

        Login_model::login($user);

        return $this->response_success([
            'user' => User_model::preparation($user, 'default')
        ]);
    }

    public function logout()
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        Login_model::logout();

        redirect('/');
    }

    public function comment(int $reply_comment_id = NULL)
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $new_comment_data = [
            'user_id' => $user->get_id(),
            'reply_id' => $reply_comment_id,
            'assign_id' => (int)App::get_ci()->input->post('postId'),
            'text' => (string)App::get_ci()->input->post('commentText')
        ];

        $comment_id = Comment_model::create($new_comment_data);
        if ( ! $comment_id)
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR);
        }

        return $this->response_success();
    }

    public function like_comment(int $comment_id)
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $comment = Comment_model::find_comment_by_id($comment_id);
        if ( ! $comment->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS);
        }

        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        $is_likes_incremented = $comment->increment_likes($user); // TODO: likes may end or internal error, better to throw exceptions
        if ( ! $is_likes_incremented)
        {
            App::get_s()->rollback()->execute();
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_UNAVAILABLE);
        }

        App::get_s()->commit()->execute();

        return $this->response_success();
    }

    public function like_post(int $post_id)
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $post = Post_model::find_post_by_id($post_id);
        if ( ! $post->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS);
        }

        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        $is_likes_incremented = $post->increment_likes($user);
        if ( ! $is_likes_incremented){
            App::get_s()->rollback()->execute();
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_UNAVAILABLE);
        }

        App::get_s()->commit()->execute();

        return $this->response_success();
    }

    public function add_money()
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $sum = (float)App::get_ci()->input->post('sum');

        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        $is_money_added = $user->add_money($sum, Analytics_model::OBJECT_WALLET, Transaction_type::REFILL);
        if ( ! $is_money_added)
        {
            App::get_s()->rollback()->execute();
            log_message('error', 'User can`t add money.');
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR);
        }

        App::get_s()->commit()->execute();

        return $this->response_success();
    }

    public function get_post(int $post_id)
    {
        $post = Post_model::find_post_by_id($post_id);
        if ( ! $post->is_loaded()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS);
        }

        return $this->response_success([
            'post' => Post_model::preparation($post, 'full_info')
        ]);
    }

    public function get_comment_replies(int $comment_id)
    {
        $comments = Comment_model::preparation_many(Comment_model::get_all_by_replay_id($comment_id), 'default');
        return $this->response_success(['comments' => $comments]);
    }

    public function buy_boosterpack()
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $boosterpack_id = (int)App::get_ci()->input->post('id');
        $boosterpack = Boosterpack_model::find_boosterpack_by_id($boosterpack_id);
        if ( ! $boosterpack->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS);
        }

        $refill_sum = $boosterpack->get_price() + $boosterpack->get_us();
        if ( $user->get_wallet_balance() < $refill_sum )
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_UNAVAILABLE);
        }

        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        $is_money_removed = $user->remove_money($refill_sum, Analytics_model::OBJECT_BOOSTERPACK, Transaction_type::BUY, $boosterpack->get_id());
        if ( ! $is_money_removed)
        {
            App::get_s()->rollback()->execute();
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR);
        }

        $likes_won = $boosterpack->open();

        $is_likes_added = $user->add_likes($likes_won, Analytics_model::OBJECT_BOOSTERPACK, Transaction_type::WON_LIKES, $boosterpack->get_id());
        if ( ! $is_likes_added)
        {
            App::get_s()->rollback()->execute();
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR);
        }

        App::get_s()->commit()->execute();

        return $this->response_success([
            'amount' => $likes_won
        ]);
    }

    public function get_boosterpack_info(int $bootserpack_info)
    {
        $user = User_model::get_user();
        if ( ! $user->is_loaded())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }


        //TODO получить содержимое бустерпака
    }
}
