<?php

/**
 * ExampleAPI - Laravel API example with enterprise directory authentication.
 *
 * PHP version 7
 *
 * This auth controller is an example for creators to use and extend for
 * enterprise directory integrated single-sign-on
 *
 * @category  default
 *
 * @author    Metaclassing <Metaclassing@SecureObscure.com>
 * @copyright 2015-2016 @authors
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 */
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
//use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
// added by 3
use Tymon\JWTAuth\Facades\JWTAuth;
use Validator;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesUsers;
    //use ThrottlesLogins;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';
    private $ldap = 0;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Let unauthenticated users attempt to authenticate, all other functions are blocked
        $this->middleware('jwt.auth', ['except' => ['authenticate']]);
    }

    // Added by 3, try to cert auth, if that fails try to post ldap username/password auth, if that fails go away.
    public function authenticate(Request $request)
    {
        $error = '';
        // Only authenticate users based on CERTIFICATE info passed from webserver
        if ($_SERVER['SSL_CLIENT_VERIFY'] == 'SUCCESS') {
            try {
                return $this->goodauth($this->certauth());
            } catch (\Exception $e) {
                // Cert auth failure, continue to LDAP auth test
                $error .= "\tError with TLS client certificate authentication {$e->getMessage()}\n";
            }
        }
        if (env('LDAP_AUTH')) {
            // Attempt to authenticate all users based on LDAP username and password in the request
            try {
                return $this->goodauth($this->ldapauth($request));
            } catch (\Exception $e) {
                $error .= "\tError with LDAP authentication {$e->getMessage()}\n";
            }
        }
        abort(401, "All authentication methods available have failed\n".$error);
    }

    protected function certauth()
    {
        // Make sure we got a client certificate from the web server
        if (!$_SERVER['SSL_CLIENT_CERT']) {
            throw new \Exception('TLS client certificate missing');
        }
        // try to parse the certificate we got
        $x509 = new \phpseclib\File\X509();
        // NGINX screws up the cert by putting a bunch of tab characters into it so we need to clean those out
        $asciicert = str_replace("\t", '', $_SERVER['SSL_CLIENT_CERT']);
        $cert = $x509->loadX509($asciicert);
        $cnarray = \Metaclassing\Utility::recursiveArrayTypeValueSearch($x509->getDN(), 'id-at-commonName');
        $cn = reset($cnarray);
        if (!$cn) {
            throw new \Exception('Authentication failure, could not extract CN from TLS client certificate');
        }
        $dnparts = $x509->getDN();
        $parts = [];
        foreach ($dnparts['rdnSequence'] as $part) {
            $part = reset($part);
            $type = $part['type'];
            $value = reset($part['value']);
            switch ($type) {
                case 'id-domainComponent':
                    $parts[] = 'DC='.$value;
                    break;
                case 'id-at-organizationalUnitName':
                    $parts[] = 'OU='.$value;
                    break;
                case 'id-at-commonName':
                    $parts[] = 'CN='.$value;
                    break;
            }
        }
        $dnstring = implode(',', array_reverse($parts));

        // TODO write some checking to make sure the cert DN matches the user DN in AD

        return [
                'username' => $cn,
                'dn'       => $dnstring,
                ];
    }

    protected function ldapauth(Request $request)
    {
        if (!$request->has('username') || !$request->has('password')) {
            throw new \Exception('Missing username or password');
        }

        $username = $request->input('username');
        $password = $request->input('password');
        //echo "Auth testing for {$username} / {$password}\n";

        $this->ldapinit();

        if (!$this->ldap->authenticate($username, $password)) {
            throw new \Exception('LDAP authentication failure');
        }

        // get the username and DN and return them in the data array
        $ldapuser = $this->ldap->user()->info($username, ['*'])[0];

        return [
                'username' => $ldapuser['cn'][0],
                'dn'       => $ldapuser['dn'],
                'email'    => $ldapuser['mail'][0],
                ];
    }

    // This is called when any good authentication path succeeds, and creates a user in our table if they have not been seen before
    protected function goodauth(array $data)
    {
        // If a user does NOT exist, create them
        if (User::where('dn', '=', $data['dn'])->exists()) {
            $user = User::where('dn', '=', $data['dn'])->first();
        } else {
            $user = $this->create($data);
        }

        // IF we are using LDAP, place them into LDAP groups as Bouncer roles
        if (env('LDAP_AUTH')) {
            $userldapinfo = $this->getLdapUserByName($user->username);
            if (isset($userldapinfo['memberof'])) {
                // remove the users existing database roles before assigning new ones
                $userroles = $user->roles()->get();
                foreach ($userroles as $role) {
                    $user->retract($role);
                }
                $groups = $userldapinfo['memberof'];
                unset($groups['count']);
                // now go through groups and assign them as new roles.
                foreach ($groups as $group) {
                    // Do i need to do any other validation here? Make sure group name is CN=...?
                    $user->assign($group);
                }
            }
        }

        // We maintain a user table for permissions building and group lookup, NOT authentication and credentials
        $credentials = ['dn' => $data['dn'], 'password' => ''];
        try {
            // This should NEVER fail.
            if (!$token = JWTAuth::attempt($credentials)) {
                abort(401, 'JWT Authentication failure');
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        return response()->json(compact('token'));
    }

    // dump all the known users in our table out
    public function listusers()
    {
        $users = User::all();

        return $users;
    }

    protected function ldapinit()
    {
        if (!$this->ldap) {
            // Load the ldap library that pre-dates autoloaders
            require_once base_path().'/vendor/adldap/adldap/src/adLDAP.php';
            try {
                $this->ldap = new \adLDAP\adLDAP([
                                                    'base_dn'            => env('LDAP_BASEDN'),
                                                    'admin_username'     => env('LDAP_USER'),
                                                    'admin_password'     => env('LDAP_PASS'),
                                                    'domain_controllers' => [env('LDAP_HOST')],
                                                    'ad_port'            => env('LDAP_PORT'),
                                                    'account_suffix'     => '@'.env('LDAP_DOMAIN'),
                                                ]);
            } catch (\Exception $e) {
                abort("Exception: {$e->getMessage()}");
            }
        }
    }

    public function getLdapUserByName($username)
    {
        $this->ldapinit();
        // Search for the LDAP user by his username we copied from the certificates CN= field
        $ldapuser = $this->ldap->user()->info($username, ['*'])[0];
        // If they have unencoded certificate crap in the LDAP response, this will dick up JSON encoding
        if (isset($ldapuser['usercertificate']) && is_array($ldapuser['usercertificate'])) {
            //			unset($ldapuser["usercertificate"]);/**/
            foreach ($ldapuser['usercertificate'] as $key => $value) {
                if (\Metaclassing\Utility::isBinary($value)) {
                    $asciicert = "-----BEGIN CERTIFICATE-----\n".
                                 chunk_split(base64_encode($value), 64).
                                 "-----END CERTIFICATE-----\n";
                    $x509 = new \phpseclib\File\X509();
                    $cert = $x509->loadX509($asciicert);
                    $cn = \Metaclassing\Utility::recursiveArrayFindKeyValue(
                                \Metaclassing\Utility::recursiveArrayTypeValueSearch(
                                    $x509->getDN(),
                                    'id-at-commonName'
                                ), 'printableString'
                            );
                    $issuer = \Metaclassing\Utility::recursiveArrayFindKeyValue(
                                    \Metaclassing\Utility::recursiveArrayTypeValueSearch(
                                        $x509->getIssuerDN(),
                                        'id-at-commonName'
                                    ), 'printableString'
                                );
                    $ldapuser['usercertificate'][$key] = "Bag Attributes\n"
                                                       ."\tcn=".$cn."\n"
                                                       ."\tserial=".$cert['tbsCertificate']['serialNumber']->toString()."\n"
                                                       ."\tissuer=".$issuer."\n"
                                                       ."\tissued=".$cert['tbsCertificate']['validity']['notBefore']['utcTime']."\n"
                                                       ."\texpires=".$cert['tbsCertificate']['validity']['notAfter']['utcTime']."\n"
                                                       .$asciicert;
                }
            }/**/
        }
        // Handle any other crappy binary encoding in the response
        $ldapuser = \Metaclassing\Utility::recursiveArrayBinaryValuesToBase64($ldapuser);
        // Handle any remaining UTF8 encoded garbage before returning the user, this causes silent json_encode failures
        //$ldapuser = \Metaclassing\Utility::encodeArrayUTF8($ldapuser);
        return $ldapuser;
    }

    public function userinfo()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (env('LDAP_AUTH')) {
            $user = $this->getLdapUserByName($user->username);
        }

        return response()->json($user);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => 'required|max:255',
            'dn'       => 'required|max:255|unique:users',
            'password' => 'required|min:0',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return User
     */
    protected function create(array $data)
    {
        // Again, users we track are for LDAP linkage, NOT authentication.
        return User::create([
            'username' => $data['username'],
            'dn'       => $data['dn'],
            'email'    => $data['email'],
            'password' => bcrypt(''),
        ]);
    }
}
