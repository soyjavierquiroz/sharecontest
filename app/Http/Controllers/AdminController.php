<?php
namespace App\Http\Controllers;
use App\Models\Submission;
use Illuminate\Http\Request;
class AdminController extends Controller { public function index(Request $request) { $query = Submission::with('contest'); if ($request->filled('status')) $query->where('status',$request->string('status')); if ($request->filled('platform')) $query->where('platform',$request->string('platform')); $submissions = $query->orderByDesc('views')->orderByDesc('likes')->orderByDesc('created_at')->paginate(30)->withQueryString(); $stats = ['total'=>Submission::count(),'valid'=>Submission::where('status','valid')->count(),'review_required'=>Submission::where('status','review_required')->count(),'invalid'=>Submission::where('status','invalid')->count(),'tiktok'=>Submission::where('platform','tiktok')->count(),'instagram'=>Submission::where('platform','instagram')->count(),'facebook'=>Submission::where('platform','facebook')->count()]; return view('admin.index', compact('submissions','stats')); } }
