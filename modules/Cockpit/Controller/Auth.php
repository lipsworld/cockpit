<?php
/**
 * This file is part of the Cockpit project.
 *
 * (c) Artur Heinze - 🅰🅶🅴🅽🆃🅴🅹🅾, http://agentejo.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cockpit\Controller;

class Auth extends \LimeExtra\Controller {


    public function check() {

        if ($data = $this->param('auth')) {

            $user = $this->module('cockpit')->authenticate($data);

            if ($user && !$this->module('cockpit')->hasaccess('cockpit', 'backend', @$user['group'])) {
                $user = null;
            }

            if ($user) {
                $this->app->trigger('cockpit.account.login', [&$user]);
                $this->module('cockpit')->setUser($user);
            }

            if ($this->req_is('ajax')) {
                return $user ? json_encode(['success' => true, 'user' => $user, 'avatar'=> md5($user['email'])]) : '{"success": false}';
            } else {
                $this->reroute('/');
            }
        }

        return false;
    }


    public function login() {

        return $this->render('cockpit:views/layouts/login.php');
    }

    public function logout() {

        $this->module('cockpit')->logout();

        if ($this->req_is('ajax')) {
            return '{"logout":1}';
        } else {
            $this->reroute('/auth/login?logout=1');
        }
    }

    public function forgotpassword() {

        return $this->render('cockpit:views/layouts/forgotpassword.php');
    }

    public function requestreset() {

        if ($user = $this->param('user')) {

            $query = ['active' => true];

            if ($this->app->helper('utils')->isEmail($user)) {
                $query['email'] = $user;
            } else {
                $query['user'] = $user;
            }

            $user = $this->app->storage->findOne('cockpit/accounts', $query);

            if (!$user) {
                return $this->stop(['error' => $this('i18n')->get('User does not exist')], 404);
            }

            $token  = uniqid('rp-').'-'.time();
            $target = $this->app->param('', $this->app->getSiteUrl(true).'/auth/newpassword');
            $data   = ['_id' => $user['_id'], '_reset_token' => $token];

            $this->app->storage->save('cockpit/accounts', $data);
            $message = $this->app->view('cockpit:emails/recover.php', compact('user','token','target'));

            try {
                $response = $this->app->mailer->mail(
                    $user['email'],
                    $this->param('subject', $this->app->getSiteUrl().' - '.$this('i18n')->get('Password Recovery')),
                    $message
                );
            } catch (\Exception $e) {
                $response = $e->getMessage();
            }

            if ($response !== true) {
                return $this->stop(['error' => $this('i18n')->get($response)], 404);
            }

            return ['message' => $this('i18n')->get('Recovery email sent')];
        }

        return $this->stop(['error' => $this('i18n')->get('User required')], 412);
    }

    public function newpassword() {

        if ($token = $this->param('token')) {

            $user = $this->app->storage->findOne('cockpit/accounts', ['_reset_token' => $token]);

            if (!$user) {
                return false;
            }

            $user['md5email'] = md5($user['email']);

            return $this->render('cockpit:views/layouts/newpassword.php', compact('user', 'token'));
        }

        return false;

    }

    public function resetpassword() {

        if ($token = $this->param('token')) {

            $user = $this->app->storage->findOne('cockpit/accounts', ['_reset_token' => $token]);
            $password = trim($this->param('password'));

            if (!$user || !$password) {
                return false;
            }

            $data = ['_id' => $user['_id'], 'password' =>$this->app->hash($password), '_reset_token' => null];

            $this->app->storage->save('cockpit/accounts', $data);

            return ['success' => true, 'message' => $this('i18n')->get('Password updated')];
        }

        return $this->stop(['error' => $this('i18n')->get('Token required')], 412);
    }
}
