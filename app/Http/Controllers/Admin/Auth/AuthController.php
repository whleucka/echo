<?php

namespace App\Http\Controllers\Admin\Auth;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;

/**
 * AuthController
 * Auth routes will extend this controller for subdomain / name prefix
 */
#[Group(subdomain: 'admin', namePrefix: 'auth')]
class AuthController extends Controller
{
}
