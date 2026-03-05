<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;

/**
 * AdminController
 * Admin routes will extend this controller for subdomain / name prefix
 * These are routes used in the admin backend, for ModuleControllers
 */
#[Group(subdomain: 'admin', middleware: ["auth"])]
class AdminController extends Controller
{
}
