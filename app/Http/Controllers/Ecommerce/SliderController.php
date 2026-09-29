<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Resources\SliderResource;

use App\Http\Requests\SliderRequest;
use App\Models\Slider;

class SliderController extends Controller
{
    public function index()
    {
        try {

            $data = Slider::latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Sliders fetched successfully.',
                'data' => SliderResource::collection($data),
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Slider fetch failed.', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    public function show()
    {
        try {

            $data = Slider::latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Sliders fetched successfully.',
                'data' => SliderResource::collection($data),
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Slider fetch failed.', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    public function store(SliderRequest $request)
    {
        try {

            return DB::transaction(function () use ($request) {

                $data = $request->validated();

                if ($request->hasFile('image')) {
                    $data['image'] = $this->storeSliderImage($request->file('image'));
                }

                $slider = Slider::create($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Slider created successfully.',
                    'data' => new SliderResource($slider),
                ],201);

            });

        } catch (\Throwable $e) {

            Log::error('Slider creation failed.', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    private function storeSliderImage(UploadedFile $image): string
    {
        if (! $image->isValid()) {
            throw new \RuntimeException('Uploaded image is invalid.');
        }

        $filename = sprintf(
            'slider_%s_%s.%s',
            now()->format('YmdHis'),
            Str::random(12),
            strtolower($image->getClientOriginalExtension())
        );

        return $image->storeAs('sliders', $filename, 'public');
    }

    public function delete($id)
    {
        try {

            DB::transaction(function () use ($id) {

                $slider = Slider::find($id);

                if (! $slider) {
                    abort(404, 'Slider not found.');
                }

                // Delete image if exists
                if ($slider->image && Storage::disk('public')->exists($slider->image)) {
                    Storage::disk('public')->delete($slider->image);
                }

                // Delete record
                $slider->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Slider deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Slider delete failed.', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Something went wrong.',
            ], 500);
        }
    }
}
