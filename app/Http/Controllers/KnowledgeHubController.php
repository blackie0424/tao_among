<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeHubController extends Controller
{
    public function index($id, Request $request)
    {
        return Inertia::render('knowledge', ['id'=>$id]);
    }
}
