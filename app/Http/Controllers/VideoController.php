<?php

namespace App\Http\Controllers;

use App\Video;
use Illuminate\Http\Request;
use \App\Category;
use Illuminate\Support\Facades\URL;
use Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Storage;
use Auth;

class VideoController extends Controller{

    public function create(){
        $items 	= Category::get(array('category_name', 'cat_id'));
        return view('admin.video.new')->withTitle('DevTv - Add New Video')->withCategories($items);
    }

    public function store(Request $request){

        $niceNames = array(
            'video_title' => 'Video Title',
            'video_desc' => 'Video Description',
            'video_details' => 'Video Details',
            'video_category' => 'Video Category',
            'video_tags' => 'Video Tags',
            'video_duration' => 'Video Duration',
            'video_image' => 'Video Image',
        );

        $this->validate($request, [
            'video_title' => 'required|min:3',
            'video_desc' => 'required|max:255',
            'video_details' => 'required|min:7',
            'video_category' => 'required',
            'video_tags' => 'required',
            'video_image' => 'required|mimes:jpg,jpeg,bmp,png|between:1,7000',
            'embed_code' => 'required_if:video_location,',
            'video_duration' => ['required', 'regex:/^(?:(?:([01]?\d|2[0-3]):)?([0-5]?\d):)?([0-5]?\d)$/'],
        ], [], $niceNames);



        $videoImage = Input::file('video_image');
        $videoCoverUrl = $this->uploadVideoCover($videoImage);
        $duration = $this->computeDuration($request->input('video_duration'));

        $video = new Video;
        $video->video_title = $request->input('video_title');
        $video->video_cover_location = $videoCoverUrl;
        $video->video_details = $request->input('video_details');
        $video->video_desc = $request->input('video_desc');
        $video->video_category = $request->input('video_category');
        $video->video_tags = $request->input('video_tags');
        $video->video_duration = $duration;
        $video->video_access = $request->input('video_access');
        $video->video_type = $request->input('video_type');
        $video->video_source = $this->getVideoSource($request->input('video_location'), $request->input('embed_code'));
        $video->featured = $request->input('featured');
        $video->active = $request->input('active');
        $video->created_by = Auth::user()->id;
        $video->save();

        return redirect()->back()->with('info', 'Your Video has been added successfully');

    }

    private function getVideoSource($file, $embed){
        return $file == "" ? $embed : $file;
    }
    private function computeDuration($duration){
        $duration_arr = explode(':', $duration);
        $duration_arr[0] = array_has($duration_arr, 0) ? $duration_arr[0] : '00';
        $duration_arr[1] = array_has($duration_arr, 1) ? $duration_arr[1] : '00';
        $duration_arr[2] = array_has($duration_arr, 2) ? $duration_arr[2] : '00';
        return implode(':', $duration_arr);
    }

    private function uploadVideoCover($videoImage){
        $destinationPath = public_path(). "/uploads/" . time() . "/";
        $filename = $videoImage->getClientOriginalName();
        $videoImage->move($destinationPath, $filename);
        return URL::asset("/uploads/" . time() . "/" . $filename);
    }

    public function uploadFiles(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'mimetypes:video/avi,video/mp4,video/ogg,video/webm,video/x-msvideo,video/x-flv|max:1048900'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid Video File',
                'message' => $validator->messages()->first(),
                'code' => 400
            ], 400);

        }


        $file = $request->file('file');
        $fileName = time() . '/' . $request->file('file')->getClientOriginalName();

        $status = Storage::disk('s3')->put($fileName, file_get_contents($file));
        $fileUrl = "https://s3-" . env('S3_REGION') . ".amazonaws.com/" . env('S3_BUCKET') . "/" . $fileName;
        if ($status) {
            return response()->json([
                'success' => true,
                'message' => $fileUrl,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Internal Error',
                'code' => 400
            ], 400);
        }

    }
}