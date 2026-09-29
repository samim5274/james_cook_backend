<?php

namespace App\Http\Controllers\Notice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

use Session;
use App\Models\Notice;

class NoticeController extends Controller
{
    public $date;

    public function __construct()
    {
        $this->date = Carbon::now()->format('Y-m-d');
    }

    public function index()
    {
        try {
            $notice = Notice::with('user')
                // ->whereDate('publish_date', '<=', now()->toDateString())
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Notice fetched successfully.',
                'data' => $notice,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders. Please try again later.',
            ], 500);
        }
    }

    public function userNotice()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ], 401);
        }

        try {
            $notice = Notice::with('user')
                ->whereDate('publish_date', '<=', Carbon::today())
                ->where('is_active', true)
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Notice fetched successfully.',
                'data' => $notice,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders. Please try again later.',
            ], 500);
        }
    }

    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'publish_date' => 'nullable|date',
            'notice_type'  => 'required|string',
            'is_active'    => 'required',
            'attachment'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {

            $data = new Notice();
            $data->title        = $request->title;
            $data->description  = $request->description ?? null;
            $data->publish_date = $request->publish_date ?? now()->toDateString();
            $data->notice_type  = $request->notice_type;
            $data->is_active    = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $data->user_id      = Auth::id();

            // file upload
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                // original extension safe
                $extension = $file->getClientOriginalExtension();
                // unique file name
                $filename = Str::uuid() . '.' . $extension;
                // folder structure (year/month)
                $folder = 'notices/' . date('Y') . '/' . date('m');
                // store in public disk
                $path = $file->storeAs($folder, $filename, 'public');
                // DB তে full path save করো (BEST PRACTICE)
                $data->attachment = $path;
            }

            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Notice created successfully',
                'data'    => $data
            ], 201);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                // 'error'   => $e->getMessage(), // debug এ , production এ remove
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $notice = Notice::find($id);

            if (!$notice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice not found.'
                ], 404);
            }

            // Delete attachment from storage
            if ($notice->attachment) {
                $filePath = 'notices/' . $notice->attachment;
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            $notice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notice deleted successfully.'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function viewNotice($id){
        try {
            $notice = Notice::with('user')->find($id);

            if (!$notice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice not found.',
                ], 404);
            }

            $notice->update([
                'is_read' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notice fetched successfully.',
                'data'    => $notice,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notice.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateNotice(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'publish_date' => 'nullable|date',
            'notice_type'  => 'required|string',
            'is_active'    => 'required',
            'attachment'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $notice = Notice::find($id);

            if (!$notice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice not found.'
                ], 404);
            }

            $notice->title        = $request->title;
            $notice->description  = $request->description ?? null;
            $notice->publish_date = $request->publish_date ?? now()->toDateString();
            $notice->notice_type  = $request->notice_type;
            $notice->is_active    = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);

            if ($request->hasFile('attachment')) {
                // remove old file
                if ($notice->attachment) {
                    Storage::disk('public')->delete('notices/' . $notice->attachment);
                }

                $file     = $request->file('attachment');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('notices', $filename, 'public');
                $notice->attachment = $filename;
            }

            $notice->save();

            return response()->json([
                'success' => true,
                'message' => 'Notice updated successfully.',
                'data'    => $notice
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function attachView($file){
        $path = public_path('notices/' . $file);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        // View the file in browser (PDF, image, doc etc.)
        return response()->file($path);
    }



    public function show(){
        $company = Company::first();
        $notices = Notice::Where('is_active', 1)->get();
        return view('notice.show-all-notice', compact('notices','company'));
    }
}
